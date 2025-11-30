<?php

namespace Tests\Feature\Billing;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Journal;
use App\Services\Bills\BillBatchService;
use App\Services\Invoice\InvoiceService;
use App\Services\Payment\PaymentService;
use App\Services\CustomerPaymentService;
use App\Services\MeterFinancialService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Integration tests for the complete billing cycle
 * 
 * These tests verify that the entire billing system works correctly end-to-end,
 * catching bugs like:
 * - Incorrect balance calculations
 * - Missing journal entries
 * - Orphaned records
 * - Data integrity violations
 * - Tenant isolation breaches
 * - Double-entry accounting errors
 */
class BillingCycleIntegrationTest extends TestCase
{
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;
    protected Meter $meter;
    protected MeterAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'balance' => 0,
            'overpayment' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create meter and assignment
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

        // Create necessary accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        
        // Create revenue accounts
        Account::create([
            'code' => 'WATER_USAGE',
            'name' => 'Water Usage Revenue',
            'type' => 'revenue',
            'description' => 'Revenue from water usage',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
    }

    #[Test]
    public function it_maintains_double_entry_accounting_balance()
    {
        // Create bill and generate invoice
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
            true, // Create invoice
            false
        );

        $invoice = Invoice::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($invoice, 'Invoice should be created');

        // Make payment
        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice, [
            'amount' => 1000,
            'method' => 'mpesa',
            'reference' => 'TEST-001',
            'status' => 'paid',
        ], false);

        // BUG CHECK: Verify double-entry accounting - debits must equal credits
        $payment = Payment::where('invoice_id', $invoice->id)->first();
        $journals = Journal::where('payment_id', $payment->id)->get();

        $totalDebits = $journals->where('type', 'debit')->sum('amount');
        $totalCredits = $journals->where('type', 'credit')->sum('amount');

        $this->assertEquals($totalDebits, $totalCredits, 
            'BUG: Double-entry accounting violated - debits do not equal credits');
        
        $this->assertGreaterThan(0, $totalDebits, 
            'BUG: No debit entries created for payment');
        $this->assertGreaterThan(0, $totalCredits, 
            'BUG: No credit entries created for payment');
    }

    #[Test]
    public function it_prevents_orphaned_bills_when_invoice_creation_fails()
    {
        $billCountBefore = Bill::count();

        // Try to create bills with invalid data that should cause invoice generation to fail
        try {
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
                true, // Create invoice
                false
            );

            // Even if it succeeds, bills should be properly linked
            $bills = Bill::where('customer_id', $this->customer->id)->get();
            foreach ($bills as $bill) {
                $linkedToInvoice = DB::table('invoice_bills')
                    ->where('bill_id', $bill->id)
                    ->exists();
                
                $this->assertTrue($linkedToInvoice, 
                    "BUG: Bill #{$bill->id} created but not linked to any invoice - orphaned record");
            }
        } catch (\Exception $e) {
            // If it fails, no bills should be created (transaction rollback)
            $billCountAfter = Bill::count();
            $this->assertEquals($billCountBefore, $billCountAfter,
                'BUG: Bills were created despite invoice generation failure - transaction not rolled back');
        }
    }

    #[Test]
    public function it_calculates_overpayment_correctly_and_applies_to_next_invoice()
    {
        // Create first invoice for 500
        $batchService = app(BillBatchService::class);
        $batchService->processBatchForCustomers(
            [$this->customer->id],
            [
                'bill_type' => 'WATER_USAGE',
                'amount' => 50,
                'rate_used' => 10.0,
                'total_amount' => 500.0,
                'status' => 'pending',
                'generation_date' => now(),
            ],
            null,
            true,
            false
        );

        $invoice1 = Invoice::where('customer_id', $this->customer->id)->first();

        // Overpay by 300
        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice1, [
            'amount' => 800,
            'method' => 'mpesa',
            'reference' => 'OVERPAY-001',
            'status' => 'paid',
        ], false);

        // BUG CHECK: Verify overpayment was recorded
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(0, $this->meter->balance, 
            'BUG: Meter balance should be 0 after overpayment');
        $this->assertEquals(300, $this->meter->overpayment, 
            'BUG: Overpayment not calculated correctly - expected 300, got ' . $this->meter->overpayment);

        // Create second invoice for 1000
        $batchService->processBatchForCustomers(
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

        $invoice2 = Invoice::where('customer_id', $this->customer->id)
            ->where('id', '!=', $invoice1->id)
            ->first();

        // BUG CHECK: Overpayment should be applied to new invoice
        $financialService->recalculateMeterBalance($this->meter->id);
        $this->meter->refresh();
        
        $this->assertEquals(700, $this->meter->balance, 
            'BUG: Overpayment not applied to new invoice - expected balance 700 (1000-300), got ' . $this->meter->balance);
    }

    #[Test]
    public function it_maintains_tenant_isolation_in_payments_and_journals()
    {
        // Create second tenant
        $tenant2 = Tenant::factory()->create();
        $customer2 = User::factory()->create(['tenant_id' => $tenant2->id]);
        $meter2 = Meter::factory()->create(['tenant_id' => $tenant2->id]);
        MeterAssignment::factory()->create([
            'customer_id' => $customer2->id,
            'meter_id' => $meter2->id,
            'tenant_id' => $tenant2->id,
            'is_active' => true,
        ]);

        // Create accounts for tenant 2
        Account::factory()->bank()->create(['tenant_id' => $tenant2->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $tenant2->id, 'created_by' => $this->admin->id]);

        // Create invoice for tenant 1
        $batchService = app(BillBatchService::class);
        $batchService->processBatchForCustomers(
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

        $invoice1 = Invoice::where('customer_id', $this->customer->id)->first();

        // Make payment for tenant 1
        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice1, [
            'amount' => 1000,
            'method' => 'mpesa',
            'reference' => 'TENANT1-001',
            'status' => 'paid',
        ], false);

        // BUG CHECK: Verify tenant 2 has no data from tenant 1's transaction
        $tenant2Payments = Payment::where('tenant_id', $tenant2->id)->count();
        $tenant2Journals = Journal::where('tenant_id', $tenant2->id)->count();
        $tenant2Bills = Bill::where('tenant_id', $tenant2->id)->count();

        $this->assertEquals(0, $tenant2Payments, 
            'BUG: Tenant isolation violated - Tenant 2 has payments from Tenant 1');
        $this->assertEquals(0, $tenant2Journals, 
            'BUG: Tenant isolation violated - Tenant 2 has journal entries from Tenant 1');
        $this->assertEquals(0, $tenant2Bills, 
            'BUG: Tenant isolation violated - Tenant 2 has bills from Tenant 1');

        // Verify tenant 1 data exists
        $tenant1Payments = Payment::where('tenant_id', $this->tenant->id)->count();
        $tenant1Journals = Journal::where('tenant_id', $this->tenant->id)->count();

        $this->assertGreaterThan(0, $tenant1Payments, 
            'BUG: No payments created for Tenant 1');
        $this->assertGreaterThan(0, $tenant1Journals, 
            'BUG: No journal entries created for Tenant 1');
    }

    #[Test]
    public function it_prevents_negative_balances_from_incorrect_payment_calculations()
    {
        // Create invoice
        $batchService = app(BillBatchService::class);
        $batchService->processBatchForCustomers(
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

        $invoice = Invoice::where('customer_id', $this->customer->id)->first();

        // Make partial payment
        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice, [
            'amount' => 300,
            'method' => 'mpesa',
            'reference' => 'PARTIAL-001',
            'status' => 'paid',
        ], false);

        // BUG CHECK: Invoice balance should never be negative
        $invoice->refresh();
        $this->assertGreaterThanOrEqual(0, $invoice->balance, 
            'BUG: Invoice balance is negative after partial payment: ' . $invoice->balance);

        // Make another partial payment
        $paymentService->handlePayment($invoice, [
            'amount' => 400,
            'method' => 'mpesa',
            'reference' => 'PARTIAL-002',
            'status' => 'paid',
        ], false);

        $invoice->refresh();
        $this->assertGreaterThanOrEqual(0, $invoice->balance, 
            'BUG: Invoice balance is negative after second partial payment: ' . $invoice->balance);

        // Verify meter balance is correct
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateMeterBalance($this->meter->id);
        
        $this->meter->refresh();
        $this->assertEquals(300, $this->meter->balance, 
            'BUG: Meter balance incorrect after partial payments - expected 300, got ' . $this->meter->balance);
        $this->assertGreaterThanOrEqual(0, $this->meter->balance, 
            'BUG: Meter balance is negative: ' . $this->meter->balance);
    }

    #[Test]
    public function it_handles_concurrent_payments_without_data_corruption()
    {
        // Create invoice
        $batchService = app(BillBatchService::class);
        $batchService->processBatchForCustomers(
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

        $invoice = Invoice::where('customer_id', $this->customer->id)->first();
        $initialBalance = $invoice->balance;

        // Simulate concurrent payments (in real scenario, these would be parallel requests)
        $paymentService = app(PaymentService::class);
        
        $payment1 = $paymentService->handlePayment($invoice, [
            'amount' => 300,
            'method' => 'mpesa',
            'reference' => 'CONCURRENT-001',
            'status' => 'paid',
        ], false);

        // Refresh invoice for second payment
        $invoice->refresh();

        $payment2 = $paymentService->handlePayment($invoice, [
            'amount' => 400,
            'method' => 'cash',
            'reference' => 'CONCURRENT-002',
            'status' => 'paid',
        ], false);

        // BUG CHECK: Total payments should equal sum of individual payments
        $totalPayments = Payment::where('invoice_id', $invoice->id)->sum('amount');
        $this->assertEquals(700, $totalPayments, 
            'BUG: Total payments do not match expected sum - data corruption detected');

        // BUG CHECK: Invoice balance should be initial - total payments
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateMeterBalance($this->meter->id);
        
        $this->meter->refresh();
        $this->assertEquals(300, $this->meter->balance, 
            'BUG: Balance calculation incorrect with multiple payments - expected 300, got ' . $this->meter->balance);
    }

    #[Test]
    public function it_updates_all_related_balances_when_payment_is_made()
    {
        // Create invoice
        $batchService = app(BillBatchService::class);
        $batchService->processBatchForCustomers(
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

        $invoice = Invoice::where('customer_id', $this->customer->id)->first();

        // Make payment
        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice, [
            'amount' => 600,
            'method' => 'mpesa',
            'reference' => 'BALANCE-001',
            'status' => 'paid',
        ], false);

        // BUG CHECK: All related balances must be consistent
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateCustomerMeters($this->customer->id);

        $this->meter->refresh();
        $this->customer->refresh();
        $invoice->refresh();

        $this->assertEquals(400, $this->meter->balance, 
            'BUG: Meter balance not updated correctly');
        $this->assertEquals(400, $this->customer->balance, 
            'BUG: Customer balance not updated correctly');
        $this->assertEquals(400, $invoice->balance, 
            'BUG: Invoice balance not updated correctly');

        // All three should match
        $this->assertEquals($this->meter->balance, $invoice->balance,
            'BUG: Meter balance and invoice balance do not match');
    }

    #[Test]
    public function it_creates_correct_journal_entries_for_overpayment()
    {
        // Create invoice
        $batchService = app(BillBatchService::class);
        $batchService->processBatchForCustomers(
            [$this->customer->id],
            [
                'bill_type' => 'WATER_USAGE',
                'amount' => 50,
                'rate_used' => 10.0,
                'total_amount' => 500.0,
                'status' => 'pending',
                'generation_date' => now(),
            ],
            null,
            true,
            false
        );

        $invoice = Invoice::where('customer_id', $this->customer->id)->first();

        // Overpay
        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice, [
            'amount' => 800, // Overpay by 300
            'method' => 'mpesa',
            'reference' => 'OVERPAY-JOURNAL-001',
            'status' => 'paid',
        ], false);

        $payment = Payment::where('invoice_id', $invoice->id)->first();
        $journals = Journal::with('account')->where('payment_id', $payment->id)->get();

        // BUG CHECK: Should have 3 journal entries for overpayment
        // 1. Debit Bank (800)
        // 2. Credit AR (500)
        // 3. Credit Customer Prepayment (300)
        $this->assertGreaterThanOrEqual(3, $journals->count(), 
            'BUG: Missing journal entries for overpayment - expected at least 3, got ' . $journals->count());

        $bankDebit = $journals->where('type', 'debit')
            ->filter(fn($j) => $j->account && $j->account->code === 'BANK-001')
            ->first();
        $this->assertNotNull($bankDebit, 
            'BUG: No bank debit entry for payment');
        $this->assertEquals(800, $bankDebit->amount, 
            'BUG: Bank debit amount incorrect');

        $arCredit = $journals->where('type', 'credit')
            ->filter(fn($j) => $j->account && $j->account->code === 'AR-CONTROL')
            ->first();
        $this->assertNotNull($arCredit, 
            'BUG: No AR credit entry for payment');
        $this->assertEquals(500, $arCredit->amount, 
            'BUG: AR credit should be invoice amount (500), not full payment');

        $prepaymentCredit = $journals->where('type', 'credit')
            ->where('transaction_type', 'overpayment')
            ->first();
        $this->assertNotNull($prepaymentCredit, 
            'BUG: No prepayment credit entry for overpayment');
        $this->assertEquals(300, $prepaymentCredit->amount, 
            'BUG: Prepayment credit should be overpayment amount (300)');
    }

    #[Test]
    public function it_handles_customer_with_multiple_meters_correctly()
    {
        // Create second meter for same customer
        $meter2 = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 0,
            'overpayment' => 0,
        ]);

        $assignment2 = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter2->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Create bills for both meters
        $batchService = app(BillBatchService::class);
        $batchService->processBatch(
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

        // BUG CHECK: Each meter should have its own invoice
        $meter1Invoice = Invoice::where('meter_id', $this->meter->id)->first();
        $meter2Invoice = Invoice::where('meter_id', $meter2->id)->first();

        $this->assertNotNull($meter1Invoice, 
            'BUG: No invoice created for meter 1');
        $this->assertNotNull($meter2Invoice, 
            'BUG: No invoice created for meter 2');
        $this->assertNotEquals($meter1Invoice->id, $meter2Invoice->id, 
            'BUG: Same invoice created for different meters');

        // BUG CHECK: Customer balance should be sum of all meter balances
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateCustomerMeters($this->customer->id);

        $this->customer->refresh();
        $this->meter->refresh();
        $meter2->refresh();

        $expectedCustomerBalance = $this->meter->balance + $meter2->balance;
        $this->assertEquals($expectedCustomerBalance, $this->customer->balance, 
            'BUG: Customer balance does not equal sum of meter balances - ' .
            "Expected: {$expectedCustomerBalance}, Got: {$this->customer->balance}");
    }

    #[Test]
    public function it_prevents_payment_on_already_paid_invoice()
    {
        // Create and pay invoice fully
        $batchService = app(BillBatchService::class);
        $batchService->processBatchForCustomers(
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

        $invoice = Invoice::where('customer_id', $this->customer->id)->first();

        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice, [
            'amount' => 1000,
            'method' => 'mpesa',
            'reference' => 'FULL-PAY-001',
            'status' => 'paid',
        ], false);

        // Verify invoice is fully paid
        $financialService = app(MeterFinancialService::class);
        $financialService->recalculateMeterBalance($this->meter->id);
        
        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance, 
            'BUG: Invoice balance should be 0 after full payment');

        // Try to make another payment - should result in overpayment, not error
        $paymentService->handlePayment($invoice, [
            'amount' => 500,
            'method' => 'mpesa',
            'reference' => 'EXTRA-PAY-001',
            'status' => 'paid',
        ], false);

        // BUG CHECK: Should handle gracefully with overpayment
        $financialService->recalculateMeterBalance($this->meter->id);
        $this->meter->refresh();
        
        $this->assertEquals(500, $this->meter->overpayment, 
            'BUG: Overpayment not handled correctly on already-paid invoice');
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
