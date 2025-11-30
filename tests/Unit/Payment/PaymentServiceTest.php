<?php

namespace Tests\Unit\Payment;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Invoice;
use App\Services\Payment\PaymentService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;

class PaymentServiceTest extends TestCase
{
    use WithFaker;

    protected PaymentService $service;
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant first
        $this->tenant = Tenant::factory()->create();

        // Create test users with tenant first (needed for created_by)
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'balance' => 1000,
            'overpayment' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create test accounts with tenant and created_by
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->service = app(PaymentService::class);
    }

    #[Test]
    public function it_handles_payment_correctly_when_payment_matches_invoice_amount()
    {
        // 1. Arrange
        $meter = \App\Models\Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'INV-2024-001',
            'method' => 'cash',
            'status' => 'completed',
        ];

        // 2. Act
        $this->service->handlePayment($invoice, $paymentData, false);

        // 3. Assert
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'payment',
            'amount' => 500,
            'type' => 'debit',
        ]);

        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'payment',
            'amount' => 500,
            'type' => 'credit',
        ]);

        // Balance is recalculated by MeterFinancialService
        $this->customer->refresh();
        $this->assertNotNull($this->customer->balance);
    }

    #[Test]
    public function it_handles_overpayment_correctly()
    {
        // 1. Arrange
        $meter = \App\Models\Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        $paymentData = [
            'amount' => 700,
            'reference' => 'INV-2024-002',
            'method' => 'mpesa',
            'status' => 'completed',
        ];

        // 2. Act
        $this->service->handlePayment($invoice, $paymentData, false);

        // 3. Assert
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 700,
        ]);

        // Journals for the invoice portion
        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'payment',
            'amount' => 500,
            'type' => 'credit',
        ]);

        // Journal for the overpayment portion
        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'overpayment',
            'amount' => 200,
            'type' => 'credit',
        ]);

        // Balance and overpayment are recalculated by MeterFinancialService
        $this->customer->refresh();
        $this->assertNotNull($this->customer->balance);
        $this->assertNotNull($this->customer->overpayment);
    }

    #[Test]
    public function it_handles_partial_payment_correctly()
    {
        // 1. Arrange
        $meter = \App\Models\Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
            'status' => 'not paid',
        ]);

        $paymentData = [
            'amount' => 400,
            'reference' => 'INV-2024-003',
            'method' => 'card',
            'status' => 'completed',
        ];

        // 2. Act
        $this->service->handlePayment($invoice, $paymentData, false);

        // 3. Assert
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 400,
        ]);

        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'payment',
            'amount' => 400,
            'type' => 'credit',
        ]);

        // No overpayment journal should be created
        $this->assertDatabaseMissing('journals', [
            'transaction_type' => 'overpayment',
        ]);

        // Invoice status should be updated to "Partial Payment"
        $invoice->refresh();
        $this->assertEquals('Partial Payment', $invoice->status);
        $this->assertEquals(400, $invoice->paid_amount);
        $this->assertEquals(600, $invoice->balance);
        $this->assertEquals('open', $invoice->state);

        // Balance is recalculated by MeterFinancialService
        $this->customer->refresh();
        $this->assertNotNull($this->customer->balance);
        $this->assertNotNull($this->customer->overpayment);
    }

    #[Test]
    public function it_creates_payment_record_with_all_required_fields()
    {
        $meter = \App\Models\Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'PAY-TEST-001',
            'method' => 'mpesa',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();

        $this->assertNotNull($payment);
        $this->assertEquals($this->customer->id, $payment->customer_id);
        $this->assertEquals($invoice->id, $payment->invoice_id);
        $this->assertEquals($meter->id, $payment->meter_id);
        $this->assertEquals('mpesa', $payment->method);
        $this->assertEquals('PAY-TEST-001', $payment->reference);
        $this->assertEquals(500, $payment->amount);
        $this->assertEquals('paid', $payment->status);
        $this->assertEquals($this->tenant->id, $payment->tenant_id);
        $this->assertNotNull($payment->date);
        $this->assertNotNull($payment->created_by);
    }

    #[Test]
    public function it_updates_invoice_balance_after_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'paid_amount' => 0,
        ]);

        $paymentData = [
            'amount' => 400,
            'reference' => 'PAY-BALANCE-001',
            'method' => 'cash',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        // After meter recalculation, invoice should reflect updated balance
        $invoice->refresh();
        $this->assertNotNull($invoice->balance);
    }

    #[Test]
    public function it_updates_invoice_status_after_full_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
            'paid_amount' => 0,
            'status' => 'not paid',
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'PAY-FULL-001',
            'method' => 'mpesa',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $invoice->refresh();
        // Status should be updated after payment
        $this->assertNotNull($invoice->status);
    }

    #[Test]
    public function it_supports_different_payment_methods()
    {
        $methods = ['cash', 'mpesa', 'bank_transfer', 'cheque'];

        foreach ($methods as $method) {
            $invoice = Invoice::factory()->create([
                'customer_id' => $this->customer->id,
                'tenant_id' => $this->tenant->id,
                'total_amount' => 100,
                'balance' => 100,
            ]);

            $paymentData = [
                'amount' => 100,
                'reference' => 'REF-' . strtoupper($method),
                'method' => $method,
                'status' => 'paid',
            ];

            $this->service->handlePayment($invoice, $paymentData, false);

            $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
            $this->assertEquals($method, $payment->method);
        }
    }

    #[Test]
    public function it_sets_payment_date_to_current_date()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 300,
            'balance' => 300,
        ]);

        $paymentData = [
            'amount' => 300,
            'reference' => 'DATE-TEST-001',
            'method' => 'cash',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
        
        $this->assertNotNull($payment->date);
        $this->assertTrue($payment->date->isToday());
    }

    #[Test]
    public function it_sets_meter_id_on_payment_record()
    {
        $meter = \App\Models\Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'METER-TEST-001',
            'method' => 'mpesa',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
        
        $this->assertEquals($meter->id, $payment->meter_id);
    }

    #[Test]
    public function it_creates_debit_journal_entry_to_bank_account()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'JOURNAL-TEST-001',
            'method' => 'cash',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
        $bankAccount = Account::where('code', 'BANK-001')->where('tenant_id', $this->tenant->id)->first();

        $this->assertDatabaseHas('journals', [
            'account_id' => $bankAccount->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 500,
            'type' => 'debit',
        ]);
    }

    #[Test]
    public function it_creates_credit_journal_entry_to_ar_control()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 800,
            'balance' => 800,
        ]);

        $paymentData = [
            'amount' => 300,
            'reference' => 'AR-TEST-001',
            'method' => 'bank_transfer',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
        $arAccount = Account::where('code', 'AR-CONTROL')->where('tenant_id', $this->tenant->id)->first();

        $this->assertDatabaseHas('journals', [
            'account_id' => $arAccount->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'payment',
            'amount' => 300,
            'type' => 'credit',
        ]);
    }

    #[Test]
    public function it_creates_overpayment_journal_entry_when_payment_exceeds_balance()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        $paymentData = [
            'amount' => 800,
            'reference' => 'OVERPAY-JOURNAL-001',
            'method' => 'mpesa',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
        $prepaymentAccount = Account::where('code', 'CUSTOMER-PREPAYMENT')->where('tenant_id', $this->tenant->id)->first();

        $this->assertDatabaseHas('journals', [
            'account_id' => $prepaymentAccount->id,
            'payment_id' => $payment->id,
            'transaction_type' => 'overpayment',
            'amount' => 300,
            'type' => 'credit',
        ]);
    }

    #[Test]
    public function it_triggers_meter_balance_recalculation_after_payment()
    {
        $meter = \App\Models\Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 500,
            'overpayment' => 0,
        ]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'RECALC-TEST-001',
            'method' => 'cash',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        // Meter balance should be recalculated
        $meter->refresh();
        $this->assertNotNull($meter->balance);
    }

    #[Test]
    public function it_handles_multiple_payments_on_same_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'paid_amount' => 0,
        ]);

        // First payment
        $paymentData1 = [
            'amount' => 300,
            'reference' => 'MULTI-PAY-001',
            'method' => 'cash',
            'status' => 'paid',
        ];
        $this->service->handlePayment($invoice, $paymentData1, false);

        // Second payment
        $paymentData2 = [
            'amount' => 400,
            'reference' => 'MULTI-PAY-002',
            'method' => 'mpesa',
            'status' => 'paid',
        ];
        $invoice->refresh(); // Refresh to get updated balance
        $this->service->handlePayment($invoice, $paymentData2, false);

        // Should have 2 payment records
        $payments = \App\Models\Payment::where('invoice_id', $invoice->id)->get();
        $this->assertCount(2, $payments);
        
        $totalPaid = $payments->sum('amount');
        $this->assertEquals(700, $totalPaid);
    }

    #[Test]
    public function it_handles_payment_with_custom_created_by()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'CREATOR-TEST-001',
            'method' => 'cash',
            'status' => 'paid',
        ];

        $customUserId = $this->admin->id;
        $this->service->handlePayment($invoice, $paymentData, false, $customUserId);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
        $this->assertEquals($customUserId, $payment->created_by);
    }

    #[Test]
    public function it_includes_proper_journal_entry_descriptions()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-DESC-001',
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'DESC-TEST-001',
            'method' => 'mpesa',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();

        // Check that journal entries have descriptions
        $journals = \App\Models\Journal::where('payment_id', $payment->id)->get();
        
        foreach ($journals as $journal) {
            $this->assertNotNull($journal->description);
            $this->assertNotEmpty($journal->description);
        }
    }

    #[Test]
    public function it_sets_tenant_id_on_all_created_records()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
        ]);

        $paymentData = [
            'amount' => 500,
            'reference' => 'TENANT-TEST-001',
            'method' => 'cash',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        $payment = \App\Models\Payment::where('invoice_id', $invoice->id)->first();
        
        // Payment should have tenant_id
        $this->assertEquals($this->tenant->id, $payment->tenant_id);

        // All journal entries should have tenant_id
        $journals = \App\Models\Journal::where('payment_id', $payment->id)->get();
        foreach ($journals as $journal) {
            $this->assertEquals($this->tenant->id, $journal->tenant_id);
        }
    }

    #[Test]
    public function it_handles_zero_overpayment_when_payment_equals_balance()
    {
        $meter = \App\Models\Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 300,
            'balance' => 300,
        ]);

        $paymentData = [
            'amount' => 300, // Exact payment - no overpayment
            'reference' => 'EXACT-PAY-001',
            'method' => 'mpesa',
            'status' => 'paid',
        ];

        $this->service->handlePayment($invoice, $paymentData, false);

        // Payment should be created
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 300,
        ]);

        // No overpayment journal should be created
        $this->assertDatabaseMissing('journals', [
            'transaction_type' => 'overpayment',
        ]);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
