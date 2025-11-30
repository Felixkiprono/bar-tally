<?php

namespace Tests\Unit\Payment;

use Tests\TestCase;
use App\Models\User;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Account;
use App\Models\Journal;
use App\Services\CustomerPaymentService;
use App\Services\Payment\PaymentService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class CustomerPaymentServiceTest extends TestCase
{
    use WithFaker;

    protected CustomerPaymentService $service;
    protected PaymentService $paymentService;
    protected User $customer;
    protected User $admin;
    protected \App\Models\Tenant $tenant;
    protected \App\Models\Meter $meter;
    protected \App\Models\MeterAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a tenant first
        $this->tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'database' => 'test_tenant_db',
        ]);
        
        // Create test users with tenant first (needed for created_by)
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'balance' => 0,
            'overpayment' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        
        // Create meter and assignment for meter-centric billing
        $this->meter = \App\Models\Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 0,
            'overpayment' => 0,
        ]);
        
        $this->assignment = \App\Models\MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        
        // Create test accounts with tenant and created_by
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->paymentService = app(PaymentService::class);
        $this->service = new CustomerPaymentService($this->paymentService);
    }

    #[Test]
    public function it_gets_latest_unpaid_invoice_correctly()
    {
        // Create multiple invoices for the customer and meter
        $oldInvoice = Invoice::factory()->fullyPaid()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'invoice_date' => now()->subDays(10),
            'tenant_id' => $this->tenant->id,
        ]);

        $latestInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'invoice_date' => now()->subDays(2),
            'balance_brought_forward' => 0,
            'amount' => 1500,
            // balance will be auto-calculated as 0 + 1500 - 0 = 1500
            'paid_amount' => 0,
            'status' => 'not paid',
            'tenant_id' => $this->tenant->id,
        ]);

        $newerButPaidInvoice = Invoice::factory()->fullyPaid()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'invoice_date' => now()->subDay(),
            'tenant_id' => $this->tenant->id,
        ]);

        $result = $this->service->getLatestUnpaidInvoiceForMeter($this->customer->id, $this->meter->id);

        $this->assertNotNull($result);
        $this->assertEquals($latestInvoice->id, $result->id);
        $this->assertEquals(1500, $result->balance);
    }

    #[Test]
    public function it_returns_null_when_no_unpaid_invoices_exist()
    {
        // Create only paid invoices
        Invoice::factory()->fullyPaid()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
        ]);

        $result = $this->service->getLatestUnpaidInvoiceForMeter($this->customer->id, $this->meter->id);

        $this->assertNull($result);
    }

    #[Test]
    public function it_gets_customer_payment_context_with_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 2500,
            'paid_amount' => 0,
            'invoice_number' => 'INV-001',
            'status' => 'not paid',
        ]);

        $context = $this->service->getCustomerPaymentContext($this->customer, $this->meter->id);

        $this->assertTrue($context['has_unpaid_invoice']);
        $this->assertEquals($invoice->id, $context['latest_invoice']->id);
        $this->assertEquals(2500, $context['suggested_amount']);
        $this->assertEquals($this->meter->id, $context['meter']->id);
    }

    #[Test]
    public function it_gets_customer_payment_context_without_invoice()
    {
        $context = $this->service->getCustomerPaymentContext($this->customer, $this->meter->id);

        $this->assertFalse($context['has_unpaid_invoice']);
        $this->assertNull($context['latest_invoice']);
        $this->assertEquals(0, $context['suggested_amount']);
        $this->assertEquals($this->meter->id, $context['meter']->id);
    }

    #[Test]
    public function it_processes_full_payment_against_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1500,
            'paid_amount' => 0,
            'status' => 'not paid',
            'state' => 'open',
        ]);

        $paymentData = [
            'amount' => 1500,
            'method' => 'mpesa',
            'reference' => 'MP123456',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        $result = $this->service->processQuickPayment($this->customer, $paymentData);

        // Verify result
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Payment of KES 1,500.00 applied to Invoice', $result['message']);

        // Verify invoice was updated
        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(1500, $invoice->paid_amount);
        $this->assertEquals('Fully Paid', $invoice->status);
        $this->assertEquals('closed', $invoice->state);

        // Verify payment record was created
        $payment = Payment::where('customer_id', $this->customer->id)
            ->where('invoice_id', $invoice->id)
            ->first();
        
        $this->assertNotNull($payment);
        $this->assertEquals(1500, $payment->amount);
        $this->assertEquals('mpesa', $payment->method);
        $this->assertEquals('MP123456', $payment->reference);
    }

    #[Test]
    public function it_processes_partial_payment_against_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 2000,
            'paid_amount' => 0,
            'status' => 'not paid',
            'state' => 'open',
        ]);

        $paymentData = [
            'amount' => 800,
            'method' => 'bank',
            'reference' => 'BNK789',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        $result = $this->service->processQuickPayment($this->customer, $paymentData);

        // Verify result
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Payment of KES 800.00 applied to Invoice', $result['message']);

        // Verify invoice was updated
        $invoice->refresh();
        $this->assertEquals(1200, $invoice->balance); // 2000 - 800
        $this->assertEquals(800, $invoice->paid_amount);
        $this->assertEquals('Partial Payment', $invoice->status);
        $this->assertEquals('open', $invoice->state);

        // Verify payment record
        $payment = Payment::where('customer_id', $this->customer->id)
            ->where('invoice_id', $invoice->id)
            ->first();
        
        $this->assertNotNull($payment);
        $this->assertEquals(800, $payment->amount);
    }

    #[Test]
    public function it_processes_overpayment_against_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
            'status' => 'not paid',
            'state' => 'open',
        ]);

        $paymentData = [
            'amount' => 1500,
            'method' => 'cash',
            'reference' => 'CASH001',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        $result = $this->service->processQuickPayment($this->customer, $paymentData);

        // Verify result
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('KES 1,000.00 to Invoice', $result['message']);
        $this->assertStringContainsString('KES 500.00 as overpayment', $result['message']);

        // Verify invoice was fully paid
        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(1000, $invoice->paid_amount);
        $this->assertEquals('Fully Paid', $invoice->status);
        $this->assertEquals('closed', $invoice->state);

        // Verify customer overpayment was updated
        $this->customer->refresh();
        $this->assertEquals(500, $this->customer->overpayment);

        // Verify payment record
        $payment = Payment::where('customer_id', $this->customer->id)
            ->where('invoice_id', $invoice->id)
            ->first();
        
        $this->assertNotNull($payment);
        $this->assertEquals(1500, $payment->amount);
    }

    #[Test]
    public function it_processes_advance_payment_when_no_invoice_exists()
    {
        $paymentData = [
            'amount' => 2000,
            'method' => 'mpesa',
            'reference' => 'MP999888',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        $result = $this->service->processQuickPayment($this->customer, $paymentData);

        // Verify result
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('Advance payment of KES 2,000.00', $result['message']);

        // Verify customer overpayment was updated
        $this->customer->refresh();
        $this->assertEquals(2000, $this->customer->overpayment);

        // Verify payment record was created (no invoice_id)
        $payment = Payment::where('customer_id', $this->customer->id)
            ->whereNull('invoice_id')
            ->first();
        
        $this->assertNotNull($payment);
        $this->assertEquals(2000, $payment->amount);
        $this->assertEquals('mpesa', $payment->method);
        $this->assertEquals('MP999888', $payment->reference);
        $this->assertStringContainsString('Advance payment', $payment->description);

        // Verify journal entries were created
        $bankJournal = Journal::where('payment_id', $payment->id)
            ->where('type', 'debit')
            ->first();
        $this->assertNotNull($bankJournal);
        $this->assertEquals(2000, $bankJournal->amount);

        $prepaymentJournal = Journal::where('payment_id', $payment->id)
            ->where('type', 'credit')
            ->first();
        $this->assertNotNull($prepaymentJournal);
        $this->assertEquals(2000, $prepaymentJournal->amount);
    }

    #[Test]
    public function it_handles_payment_processing_errors_gracefully()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1500,
            'paid_amount' => 0,
        ]);

        // Mock PaymentService to throw an exception
        $mockPaymentService = Mockery::mock(PaymentService::class);
        $mockPaymentService->shouldReceive('handlePayment')
            ->once()
            ->andThrow(new \Exception('Payment processing failed'));

        // Inject the mock service
        $service = new CustomerPaymentService($mockPaymentService);

        $paymentData = [
            'amount' => 1500,
            'method' => 'mpesa',
            'reference' => 'MP123456',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        $result = $service->processQuickPayment($this->customer, $paymentData);

        // Verify error handling
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Payment processing failed', $result['error']);

        // Verify invoice was not modified
        $invoice->refresh();
        $this->assertEquals(1500, $invoice->balance);
        $this->assertEquals('not paid', $invoice->status);
    }

    #[Test]
    public function it_validates_payment_amounts()
    {
        $paymentData = [
            'amount' => 0, // Invalid amount
            'method' => 'mpesa',
            'reference' => 'MP123456',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        $result = $this->service->processQuickPayment($this->customer, $paymentData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Payment amount must be greater than zero', $result['error']);
    }

    #[Test]
    public function it_handles_missing_accounts_gracefully()
    {
        // Delete the required accounts
        Account::where('code', 'BANK-001')->delete();
        Account::where('code', 'CUSTOMER-PREPAYMENT')->delete();

        $paymentData = [
            'amount' => 1000,
            'method' => 'mpesa',
            'reference' => 'MP123456',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        $result = $this->service->processQuickPayment($this->customer, $paymentData);

        $this->assertTrue($result['success']);
        
        // Verify accounts were created
        $this->assertDatabaseHas('accounts', ['code' => 'BANK-001']);
        $this->assertDatabaseHas('accounts', ['code' => 'CUSTOMER-PREPAYMENT']);
    }

    #[Test]
    public function it_maintains_database_consistency_with_transactions()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        $originalInvoiceBalance = $invoice->balance;
        $originalCustomerBalance = $this->customer->balance;
        $originalCustomerOverpayment = $this->customer->overpayment;

        try {
            // Process a payment that will be rolled back
            $paymentData = [
                'amount' => 1500,
                'method' => 'mpesa',
                'reference' => 'MP123456',
                'meter_id' => $this->meter->id,
                'send_sms' => false,
            ];
            $this->service->processQuickPayment($this->customer, $paymentData);

            // Manually throw an exception to trigger the rollback in the service
            throw new \Exception('Simulating a failure after payment processing.');
        } catch (\Exception $e) {
            // Catch the exception to prevent the test from failing
        }

        $invoice->refresh();
        $this->customer->refresh();

        // Assert that the database state has been rolled back
        $this->assertEquals($originalInvoiceBalance, $invoice->balance);
        $this->assertEquals($originalCustomerBalance, $this->customer->balance);
        $this->assertEquals($originalCustomerOverpayment, $this->customer->overpayment);

        // Assert that no payment record was created
        $this->assertDatabaseMissing('payments', [
            'reference' => 'MP123456',
        ]);
    }

    #[Test]
    public function it_can_pay_invoice_directly()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
            'status' => 'not paid',
            'state' => 'open',
        ]);

        $paymentData = [
            'amount' => 1000,
            'method' => 'cash',
            'reference' => 'CASH123',
            'status' => 'paid',
            'meter_id' => $this->meter->id,
            'send_sms' => false,
        ];

        // Use reflection to test the protected method
        $reflection = new \ReflectionClass(CustomerPaymentService::class);
        $method = $reflection->getMethod('payInvoice');
        $method->setAccessible(true);
        $method->invokeArgs($this->service, [$invoice, $paymentData]);

        $invoice->refresh();

        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(1000, $invoice->paid_amount);
        $this->assertEquals('Fully Paid', $invoice->status);
        $this->assertEquals('closed', $invoice->state);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
