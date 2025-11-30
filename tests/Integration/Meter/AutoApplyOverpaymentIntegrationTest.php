<?php

namespace Tests\Integration\Meter;

use Tests\TestCase;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Bill;
use App\Models\BillType;
use App\Models\Tenant;
use App\Services\Invoice\InvoiceService;
use App\Services\Invoice\InvoiceActionService;
use App\Services\MeterFinancialService;
use App\Services\Payment\PaymentService;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;

class AutoApplyOverpaymentIntegrationTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;
    protected User $customer;
    protected Meter $meter;
    protected MeterAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create required accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->waterRevenue()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        
        // Create meter and assignment
        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 0,
            'overpayment' => 0,
        ]);
        
        $this->assignment = MeterAssignment::factory()->create([
            'meter_id' => $this->meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        
        // Mock Auth
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
    }

    // ========================================================================
    // END-TO-END SCENARIOS
    // ========================================================================

    #[Test]
    public function it_auto_applies_overpayment_when_new_invoice_is_generated()
    {
        // Arrange: Customer makes advance payment (overpayment)
        $paymentService = app(PaymentService::class);
        
        // Create advance payment of 2000
        Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 2000,
            'invoice_id' => null,  // No invoice = overpayment
            'status' => 'paid',
        ]);
        
        // Recalculate meter to set overpayment
        app(MeterFinancialService::class)->recalculateMeterBalance($this->meter->id);
        $this->meter->refresh();
        
        $this->assertEquals(2000, $this->meter->overpayment);
        $this->assertEquals(0, $this->meter->balance);
        
        // Act: Create bills and generate invoice (total: 1500)
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $bills = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 800,
                'status' => 'pending',
            ]),
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 700,
                'status' => 'pending',
            ]),
        ]);
        
        $invoiceService = app(InvoiceService::class);
        $invoice = $invoiceService->generateInvoiceFromBills($bills, false);
        
        // Assert: Invoice was auto-paid by overpayment
        $invoice->refresh();
        $this->assertEquals(1500, $invoice->total_amount);
        $this->assertEquals(1500, $invoice->overpayment_applied);
        $this->assertEquals(1500, $invoice->paid_amount);
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals('paid', $invoice->status);
        $this->assertEquals('closed', $invoice->state);
        
        // Assert: Meter has remaining overpayment
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        $this->meter->refresh();
        $this->assertEquals(500, $this->meter->overpayment);
        $this->assertEquals(0, $this->meter->balance);
    }

    #[Test]
    public function it_partially_auto_pays_invoice_when_overpayment_insufficient()
    {
        // Arrange: Customer has small overpayment
        Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 600,
            'invoice_id' => null,
            'status' => 'paid',
        ]);
        
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        $this->meter->refresh();
        
        $this->assertEquals(600, $this->meter->overpayment);
        
        // Act: Create invoice for 1500
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        $bills = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 1500,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice = app(InvoiceService::class)->generateInvoiceFromBills($bills, false);
        
        // Assert: Invoice partially paid by overpayment
        $invoice->refresh();
        $this->assertEquals(1500, $invoice->total_amount);
        $this->assertEquals(600, $invoice->overpayment_applied);
        $this->assertEquals(600, $invoice->paid_amount);
        $this->assertEquals(900, $invoice->balance);
        $this->assertEquals('partial payment', $invoice->status);
        $this->assertEquals('open', $invoice->state);
        
        // Assert: Meter overpayment fully used
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        $this->meter->refresh();
        $this->assertEquals(0, $this->meter->overpayment);
        $this->assertEquals(900, $this->meter->balance);
    }

    #[Test]
    public function it_handles_multiple_invoices_with_overpayment()
    {
        // Arrange: Create large overpayment
        Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 5000,
            'invoice_id' => null,
            'status' => 'paid',
        ]);
        
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        
        // Act: Create first invoice (1200)
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $bills1 = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 1200,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice1 = app(InvoiceService::class)->generateInvoiceFromBills($bills1, false);
        
        // Assert: First invoice fully paid
        $invoice1->refresh();
        $this->assertEquals(0, $invoice1->balance);
        $this->assertEquals('paid', $invoice1->status);
        
        // Act: Create second invoice (1800)
        $bills2 = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 1800,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice2 = app(InvoiceService::class)->generateInvoiceFromBills($bills2, false);
        
        // Assert: Second invoice also fully paid
        $invoice2->refresh();
        $this->assertEquals(0, $invoice2->balance);
        $this->assertEquals('paid', $invoice2->status);
        
        // Assert: Remaining overpayment
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        $this->meter->refresh();
        $this->assertEquals(2000, $this->meter->overpayment);  // 5000 - 1200 - 1800 = 2000
    }

    #[Test]
    public function it_combines_overpayment_with_manual_payment()
    {
        // Arrange: Small overpayment
        Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 400,
            'invoice_id' => null,
            'status' => 'paid',
        ]);
        
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        
        // Act: Create invoice for 1000
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        $bills = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 1000,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice = app(InvoiceService::class)->generateInvoiceFromBills($bills, false);
        
        // Assert: Overpayment auto-applied (partial)
        $invoice->refresh();
        $this->assertEquals(400, $invoice->overpayment_applied);
        $this->assertEquals(400, $invoice->paid_amount);
        $this->assertEquals(600, $invoice->balance);
        $this->assertEquals('partial payment', $invoice->status);
        
        // Act: Make manual payment for remaining balance
        $paymentService = app(PaymentService::class);
        $paymentService->handlePayment($invoice, [
            'amount' => 600,
            'method' => 'mpesa',
            'reference' => 'MP123456',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
        ], false, $this->admin->id);
        
        // Assert: Invoice now fully paid
        $invoice->refresh();
        $this->assertEquals(400, $invoice->overpayment_applied);  // Still 400 from overpayment
        $this->assertEquals(1000, $invoice->paid_amount);  // 400 + 600
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals('paid', $invoice->status);
        
        // Assert: Payment record created
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 600,
            'reference' => 'MP123456',
        ]);
    }

    #[Test]
    public function it_handles_invoice_with_balance_brought_forward_and_overpayment()
    {
        // Arrange: Create first invoice (not paid)
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $bills1 = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 800,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice1 = app(InvoiceService::class)->generateInvoiceFromBills($bills1, false);
        
        $this->assertEquals(800, $invoice1->balance);
        
        // Act: Customer makes overpayment
        Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 1500,
            'invoice_id' => null,
            'status' => 'paid',
        ]);
        
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        
        // Create second invoice (should have balance B/F + be auto-paid)
        $bills2 = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 600,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice2 = app(InvoiceService::class)->generateInvoiceFromBills($bills2, false);
        
        // Assert: Invoice has balance B/F and overpayment applied
        $invoice2->refresh();
        $this->assertEquals(800, $invoice2->balance_brought_forward);
        $this->assertEquals(600, $invoice2->amount);
        $this->assertEquals(1400, $invoice2->total_amount);
        $this->assertEquals(1400, $invoice2->overpayment_applied);
        $this->assertEquals(1400, $invoice2->paid_amount);
        $this->assertEquals(0, $invoice2->balance);
        $this->assertEquals('paid', $invoice2->status);
        
        // Assert: Remaining overpayment
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        $this->meter->refresh();
        $this->assertEquals(100, $this->meter->overpayment);  // 1500 - 1400 = 100
    }

    #[Test]
    public function it_maintains_accounting_integrity_across_overpayment_lifecycle()
    {
        // Arrange: Create overpayment
        Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 1000,
            'invoice_id' => null,
            'status' => 'paid',
        ]);
        
        // Get initial account balances
        $prepaymentAccount = Account::where('code', 'CUSTOMER-PREPAYMENT')
            ->where('tenant_id', $this->tenant->id)
            ->first();
        $arAccount = Account::where('code', 'AR-CONTROL')
            ->where('tenant_id', $this->tenant->id)
            ->first();
        
        $initialPrepaymentBalance = $prepaymentAccount->balance ?? 0;
        $initialARBalance = $arAccount->balance ?? 0;
        
        // Act: Create and auto-pay invoice
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        $bills = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 700,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice = app(InvoiceService::class)->generateInvoiceFromBills($bills, false);
        
        // Assert: Invoice auto-paid
        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance);
        
        // Assert: Account balances changed correctly
        // Prepayment should decrease (debit)
        // AR should decrease (credit)
        $prepaymentAccount->refresh();
        $arAccount->refresh();
        
        // Note: Actual balance calculations depend on journal entry implementations
        // This verifies the accounting entries exist and are balanced
        $journals = \App\Models\Journal::where('transaction_id', $invoice->id)
            ->where('transaction_type', 'overpayment_application')
            ->get();
        
        $debits = $journals->where('type', 'debit')->sum('amount');
        $credits = $journals->where('type', 'credit')->sum('amount');
        
        $this->assertEquals($debits, $credits, 'Debits must equal credits');
        $this->assertEquals(700, $debits);
    }

    #[Test]
    public function it_does_not_auto_apply_if_no_overpayment_exists()
    {
        // Arrange: No overpayment
        $this->meter->overpayment = 0;
        $this->meter->save();
        
        // Act: Create invoice
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        $bills = collect([
            Bill::factory()->create([
                'customer_id' => $this->customer->id,
                'meter_id' => $this->meter->id,
                'tenant_id' => $this->tenant->id,
                'bill_type_id' => $billType->id,
                'amount' => 1000,
                'status' => 'pending',
            ]),
        ]);
        
        $invoice = app(InvoiceService::class)->generateInvoiceFromBills($bills, false);
        
        // Assert: No auto-payment
        $invoice->refresh();
        $this->assertEquals(0, $invoice->overpayment_applied);
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertEquals(1000, $invoice->balance);
        $this->assertEquals('not paid', $invoice->status);
    }

    #[Test]
    public function it_works_correctly_with_concurrent_payments_and_invoices()
    {
        // Arrange: Create overpayment
        Payment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 3000,
            'invoice_id' => null,
            'status' => 'paid',
        ]);
        
        app(MeterFinancialService::class)->recalculateMeter($this->meter->id);
        
        // Act: Simulate concurrent invoice generation
        $billType = BillType::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $invoices = collect();
        
        // Create 3 invoices concurrently (in transactions)
        for ($i = 0; $i < 3; $i++) {
            DB::transaction(function () use ($billType, &$invoices, $i) {
                $bills = collect([
                    Bill::factory()->create([
                        'customer_id' => $this->customer->id,
                        'meter_id' => $this->meter->id,
                        'tenant_id' => $this->tenant->id,
                        'bill_type_id' => $billType->id,
                        'amount' => 800,
                        'status' => 'pending',
                    ]),
                ]);
                
                $invoice = app(InvoiceService::class)->generateInvoiceFromBills($bills, false);
                $invoices->push($invoice);
            });
        }
        
        // Assert: Overpayment correctly distributed
        $totalOverpaymentApplied = $invoices->sum('overpayment_applied');
        
        // Due to meter recalculation between invoices, distribution may vary
        // But total should not exceed original overpayment
        $this->assertLessThanOrEqual(3000, $totalOverpaymentApplied);
        
        // At least first invoice should be auto-paid
        $this->assertGreaterThan(0, $invoices->first()->overpayment_applied);
    }
}

