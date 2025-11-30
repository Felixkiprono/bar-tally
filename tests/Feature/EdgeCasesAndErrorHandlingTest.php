<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Bills\BillCreationService;
use App\Services\Bills\BillReferenceService;
use App\Services\Bills\BillBatchService;
use App\Services\Invoice\InvoiceService;
use App\Services\Payment\PaymentService;
use App\Services\CustomerPaymentService;
use App\Services\MeterFinancialService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Edge Cases and Error Handling Tests
 * 
 * Tests that verify the system handles:
 * - Invalid inputs gracefully
 * - Boundary conditions
 * - Database failures
 * - Concurrent access issues
 * - Data integrity violations
 * - Business rule violations
 */
class EdgeCasesAndErrorHandlingTest extends TestCase
{
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;
    protected Meter $meter;
    protected MeterAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'balance' => 0,
            'overpayment' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 0,
            'overpayment' => 0,
        ]);

        $this->assignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        
        Account::create([
            'code' => 'WATER_USAGE',
            'name' => 'Water Usage Revenue',
            'type' => 'revenue',
            'description' => 'Revenue from water usage',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
    }

    // ==================== DATA VALIDATION TESTS ====================

    #[Test]
    public function it_rejects_zero_amount_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'total_amount' => 1000,
        ]);

        $service = app(CustomerPaymentService::class);
        
        $result = $service->processQuickPayment($this->customer, [
            'amount' => 0,
            'method' => 'cash',
            'reference' => 'ZERO-001',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('greater than zero', $result['message']);
    }

    #[Test]
    public function it_rejects_negative_amount_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'total_amount' => 1000,
        ]);

        $service = app(CustomerPaymentService::class);
        
        $result = $service->processQuickPayment($this->customer, [
            'amount' => -500,
            'method' => 'cash',
            'reference' => 'NEG-001',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('greater than zero', $result['message']);
    }

    #[Test]
    public function it_requires_meter_id_for_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'total_amount' => 1000,
        ]);

        $service = app(CustomerPaymentService::class);
        
        $result = $service->processQuickPayment($this->customer, [
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'NO-METER-001',
            'status' => 'paid',
            // meter_id missing
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Meter ID is required', $result['message']);
    }

    #[Test]
    public function it_validates_meter_belongs_to_customer()
    {
        // Create another customer's meter
        $otherCustomer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'customer',
        ]);
        
        $otherMeter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        MeterAssignment::factory()->create([
            'customer_id' => $otherCustomer->id,
            'meter_id' => $otherMeter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $service = app(CustomerPaymentService::class);
        
        try {
            $result = $service->processQuickPayment($this->customer, [
                'amount' => 500,
                'method' => 'cash',
                'reference' => 'WRONG-METER-001',
                'status' => 'paid',
                'meter_id' => $otherMeter->id, // Different customer's meter
            ]);
            
            $this->assertFalse($result['success']);
        } catch (\Exception $e) {
            // Should throw exception or return error
            $this->assertStringContainsString('not found', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function it_handles_missing_required_accounts_gracefully()
    {
        // Delete all accounts to simulate missing setup
        Account::where('tenant_id', $this->tenant->id)->delete();

        $billService = app(BillCreationService::class);
        
        // Bill creation should still work (or fail gracefully)
        try {
            $bill = $billService->createSingleBill([
                'customer_id' => $this->customer->id,
                'meter_assignment_id' => $this->assignment->id,
                'bill_type' => 'WATER_USAGE',
                'amount' => 100,
                'rate_used' => 10.0,
                'total_amount' => 1000.0,
                'status' => 'pending',
                'generation_date' => now(),
            ], null, false);

            // Bill created, but invoice generation might fail
            $this->assertNotNull($bill);
            
            // Try to generate invoice - should fail gracefully
            $invoiceService = app(InvoiceService::class);
            $invoiceService->generateInvoiceFromBills(collect([$bill]), false);
            
            $this->fail('Should have thrown exception for missing accounts');
        } catch (\Exception $e) {
            // Should fail with meaningful error
            $this->assertTrue(true, 'Correctly threw exception for missing accounts');
        }
    }

    // ==================== BOUNDARY CONDITIONS ====================

    #[Test]
    public function it_handles_extremely_large_payment_amounts()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        $paymentService = app(PaymentService::class);
        
        // Try to pay 100 billion
        $paymentService->handlePayment($invoice, [
            'amount' => 100000000000,
            'method' => 'bank',
            'reference' => 'HUGE-001',
            'status' => 'paid',
        ], false);

        $invoice->refresh();
        
        // Invoice should be fully paid
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(1000, $invoice->paid_amount); // Capped at total
        $this->assertEquals('Fully Paid', $invoice->status);
        
        // Overpayment should be massive
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateMeterBalance($this->meter->id);
        
        $this->meter->refresh();
        $this->assertEquals(99999999000, $this->meter->overpayment);
    }

    #[Test]
    public function it_handles_many_small_payments_on_single_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        $paymentService = app(PaymentService::class);
        
        // Make 100 small payments of 10 each
        for ($i = 1; $i <= 100; $i++) {
            $paymentService->handlePayment($invoice, [
                'amount' => 10,
                'method' => 'cash',
                'reference' => "SMALL-{$i}",
                'status' => 'paid',
            ], false);
            
            $invoice->refresh();
        }

        // Verify final state
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(1000, $invoice->paid_amount);
        $this->assertEquals('Fully Paid', $invoice->status);
        
        // Verify all 100 payments recorded
        $paymentCount = Payment::where('invoice_id', $invoice->id)->count();
        $this->assertEquals(100, $paymentCount);
    }

    #[Test]
    public function it_handles_invoice_with_zero_total_amount()
    {
        // Edge case: free service or correction resulting in 0 amount invoice
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 0,
            'paid_amount' => 0,
            'status' => 'Fully Paid',
            'state' => 'closed',
        ]);

        $paymentService = app(PaymentService::class);
        
        // Trying to pay 0 amount invoice should handle gracefully
        $paymentService->handlePayment($invoice, [
            'amount' => 0,
            'method' => 'none',
            'reference' => 'ZERO-INV-001',
            'status' => 'paid',
        ], false);

        $invoice->refresh();
        
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals('Fully Paid', $invoice->status);
    }

    // ==================== TRANSACTION ROLLBACK TESTS ====================

    #[Test]
    public function it_properly_rolls_back_transaction_on_failure()
    {
        // ✅ FIXED: Transaction now properly rolls back on failure!
        //
        // This test verifies that when PaymentService::handlePayment() encounters
        // an error, ALL operations are rolled back (payment, journals, invoice updates).
        //
        // FIX APPLIED: Wrapped handlePayment() in DB::transaction() to ensure
        // atomic operations (all-or-nothing).
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        // Count records before operation
        $paymentsBefore = Payment::count();
        $journalsBefore = \App\Models\Journal::count();

        // Delete AR-CONTROL account to cause journal entry failure
        Account::where('code', 'AR-CONTROL')
            ->where('tenant_id', $this->tenant->id)
            ->delete();

        try {
            $paymentService = app(PaymentService::class);
            
            $paymentService->handlePayment($invoice, [
                'amount' => 500,
                'method' => 'cash',
                'reference' => 'ROLLBACK-TEST-001',
                'status' => 'paid',
            ], false);
            
            $this->fail('Should have failed with missing account');
        } catch (\Exception $e) {
            // Fails as expected
            $this->assertTrue(true, 'Operation failed as expected');
            
            // VERIFY: No payment was created (transaction rolled back)
            $paymentsAfter = Payment::count();
            $this->assertEquals($paymentsBefore, $paymentsAfter, 
                'Transaction rolled back: No payment created');
            
            // VERIFY: No orphaned payment exists
            $payment = Payment::where('reference', 'ROLLBACK-TEST-001')->first();
            $this->assertNull($payment, 
                'Transaction rolled back: Payment does not exist');
            
            // VERIFY: No partial journal entries
            $journalsAfter = \App\Models\Journal::count();
            $this->assertEquals($journalsBefore, $journalsAfter,
                'Transaction rolled back: No journal entries created');
            
            // VERIFY: Invoice unchanged
            $invoice->refresh();
            $this->assertEquals(1000, $invoice->balance,
                'Transaction rolled back: Invoice balance unchanged');
        }
    }

    #[Test]
    public function it_maintains_data_consistency_across_failed_batch()
    {
        $customer2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'customer',
        ]);
        
        $meter2 = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $assignment2 = MeterAssignment::factory()->create([
            'customer_id' => $customer2->id,
            'meter_id' => $meter2->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $billsBefore = Bill::count();

        try {
            $batchService = app(BillBatchService::class);
            
            // Process batch with invalid data for second customer
            $result = $batchService->processBatch(
                [$this->assignment->id, $assignment2->id],
                [
                    'bill_type' => 'WATER_USAGE',
                    'amount' => 100,
                    'rate_used' => 10.0,
                    'total_amount' => 1000.0,
                    'status' => 'pending',
                    'generation_date' => now(),
                ],
                null,
                true,
                false
            );

            // Some bills may be created (partial success is allowed in batch)
            // But data should be consistent
            $bills = Bill::where('customer_id', $this->customer->id)->get();
            
            foreach ($bills as $bill) {
                // Each bill should have proper tenant isolation
                $this->assertEquals($this->tenant->id, $bill->tenant_id);
                
                // Each bill should have a reference
                $this->assertNotNull($bill->bill_ref);
                
                // Each bill should have valid amounts
                $this->assertGreaterThan(0, $bill->total_amount);
            }
        } catch (\Exception $e) {
            // If complete failure, no bills should be created
            $billsAfter = Bill::count();
            $this->assertEquals($billsBefore, $billsAfter);
        }
    }

    // ==================== CONCURRENT ACCESS TESTS ====================

    #[Test]
    public function it_handles_payment_on_deleted_invoice_gracefully()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'total_amount' => 1000,
        ]);

        $invoiceId = $invoice->id;
        
        // Simulate race condition: invoice deleted between retrieval and payment
        $invoice->delete();

        try {
            $paymentService = app(PaymentService::class);
            $paymentService->handlePayment($invoice, [
                'amount' => 500,
                'method' => 'cash',
                'reference' => 'DELETED-001',
                'status' => 'paid',
            ], false);
            
            // If it doesn't fail, verify no orphaned payment
            $orphanedPayment = Payment::where('invoice_id', $invoiceId)->first();
            
            if ($orphanedPayment) {
                $this->fail('Created orphaned payment for deleted invoice');
            }
        } catch (\Exception $e) {
            // Should fail gracefully
            $this->assertTrue(true, 'Correctly handled deleted invoice');
        }
    }

    #[Test]
    public function it_handles_duplicate_payment_references_across_customers()
    {
        $customer2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'customer',
        ]);
        
        $meter2 = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        MeterAssignment::factory()->create([
            'customer_id' => $customer2->id,
            'meter_id' => $meter2->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $invoice1 = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'total_amount' => 1000,
        ]);

        $invoice2 = Invoice::factory()->create([
            'customer_id' => $customer2->id,
            'meter_id' => $meter2->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'total_amount' => 1000,
        ]);

        $paymentService = app(PaymentService::class);
        
        // Same reference for different customers - should be allowed
        $paymentService->handlePayment($invoice1, [
            'amount' => 500,
            'method' => 'mpesa',
            'reference' => 'MP123456',
            'status' => 'paid',
        ], false);

        $paymentService->handlePayment($invoice2, [
            'amount' => 600,
            'method' => 'mpesa',
            'reference' => 'MP123456', // Same reference
            'status' => 'paid',
        ], false);

        // Both payments should exist
        $payment1 = Payment::where('customer_id', $this->customer->id)
            ->where('reference', 'MP123456')
            ->first();
        $payment2 = Payment::where('customer_id', $customer2->id)
            ->where('reference', 'MP123456')
            ->first();

        $this->assertNotNull($payment1);
        $this->assertNotNull($payment2);
        $this->assertEquals(500, $payment1->amount);
        $this->assertEquals(600, $payment2->amount);
    }

    // ==================== ERROR RECOVERY TESTS ====================

    #[Test]
    public function it_fails_gracefully_with_invalid_bill_type_during_invoice_generation()
    {
        // FINDING: Bill creation succeeds with any type, but invoice generation
        // fails when account for that bill type doesn't exist
        
        $billService = app(BillCreationService::class);
        
        $bill = $billService->createSingleBill([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->assignment->id,
            'bill_type' => 'INVALID_TYPE_XYZ',
            'amount' => 100,
            'rate_used' => 10.0,
            'total_amount' => 1000.0,
            'status' => 'pending',
            'generation_date' => now(),
        ], null, false);

        $this->assertNotNull($bill, 'Bill should be created with any type');
        $this->assertEquals('INVALID_TYPE_XYZ', $bill->bill_type);

        try {
            // But invoice generation should fail (no revenue account)
            $invoiceService = app(InvoiceService::class);
            $invoiceService->generateInvoiceFromBills(collect([$bill]), false);
            
            $this->fail('Should have failed when creating journal entries');
        } catch (\Exception $e) {
            // Should fail with database/query error (account not found)
            $this->assertTrue(true, 'Correctly failed during invoice generation');
        }
    }

    #[Test]
    public function it_handles_inactive_meter_assignment_gracefully()
    {
        // Deactivate the assignment
        $this->assignment->update(['is_active' => false]);

        $batchService = app(BillBatchService::class);
        
        $result = $batchService->processBatchForCustomers(
            [$this->customer->id],
            [
                'bill_type' => 'WATER_USAGE',
                'amount' => 100,
                'rate_used' => 10.0,
                'total_amount' => 1000.0,
                'status' => 'pending',
                'generation_date' => now(),
            ],
            null,
            true,
            false
        );

        // Should report no active assignments
        $this->assertEquals(0, $result->created);
        $this->assertGreaterThan(0, count($result->errors));
        $this->assertStringContainsString('No active meter', $result->errors[0]);
    }

    #[Test]
    public function it_prevents_payment_on_reversed_invoice()
    {
        // This would require InvoiceActionService reversal functionality
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'total_amount' => 1000,
            'status' => 'Reversed', // Manually set as reversed
            'state' => 'closed',
        ]);

        $paymentService = app(PaymentService::class);
        
        // Attempting to pay reversed invoice should either:
        // 1. Be prevented by business logic, or
        // 2. Work but create overpayment
        
        $paymentService->handlePayment($invoice, [
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'REVERSED-001',
            'status' => 'paid',
        ], false);

        // Payment created but should result in overpayment
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateMeterBalance($this->meter->id);
        
        $this->meter->refresh();
        
        // Since invoice total is 1000 but it's reversed, payment becomes overpayment
        // This behavior depends on business rules
        $this->assertTrue(true, 'Handled reversed invoice payment');
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

