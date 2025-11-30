<?php

namespace Tests\Unit\Invoice;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Payment;
use App\Models\Journal;
use App\Services\Invoice\InvoiceActionService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InvoiceActionServiceTest extends TestCase
{
    protected InvoiceActionService $service;
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;
    protected Meter $meter;
    protected MeterAssignment $assignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a tenant first
        $this->tenant = Tenant::factory()->create();

        // Create test users with tenant
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'balance' => 1000,
            'overpayment' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create meter and assignment
        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'balance' => 1000,
            'overpayment' => 0,
        ]);

        $this->assignment = MeterAssignment::factory()->create([
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
        $this->service = app(InvoiceActionService::class);
    }

    #[Test]
    public function it_can_reverse_an_unpaid_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-TEST-001',
            'total_amount' => 1000,
            'balance' => 1000,
            'paid_amount' => 0,
            'status' => 'not paid',
            'state' => 'open',
        ]);

        $this->service->reverseInvoice(
            $invoice,
            'Invoice error',
            'Wrong meter reading',
            false
        );

        // Verify original invoice was marked as reversed
        $invoice->refresh();
        $this->assertEquals('reversed', $invoice->status);
        $this->assertEquals('closed', $invoice->state);
        $this->assertStringContainsString('Reversed', $invoice->notes);
        $this->assertStringContainsString('Invoice error', $invoice->notes);

        // Verify reversal invoice was created
        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'REV-INV-TEST-001',
            'status' => 'reversed',
            'state' => 'closed',
        ]);
    }

    #[Test]
    public function it_creates_reversal_journal_entries()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-JOURNAL-001',
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
            'status' => 'not paid',
        ]);

        $this->service->reverseInvoice($invoice, 'Test reversal', '', false);

        // Verify reversal journal entries exist
        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'invoice_reversal',
            'amount' => 500,
            'type' => 'credit',
        ]);
    }

    #[Test]
    public function it_can_correct_invoice_amount()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-CORRECT-001',
            'total_amount' => 1000,
            'balance' => 1000,
            'paid_amount' => 0,
            'status' => 'not paid',
        ]);

        $this->service->adjustAmount($invoice, 800, 'Meter reading error');

        // Verify invoice was corrected
        $invoice->refresh();
        $this->assertEquals(800, $invoice->total_amount);
        $this->assertEquals(800, $invoice->balance);
        $this->assertStringContainsString('Correction', $invoice->notes);
        $this->assertStringContainsString('Meter reading error', $invoice->notes);
    }

    #[Test]
    public function it_creates_correction_journal_entries()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 'not paid',
        ]);

        $this->service->adjustAmount($invoice, 750, 'Adjustment');

        // Verify correction journal entries exist
        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'invoice_correction',
            'type' => 'credit', // Reversal of original
        ]);

        $this->assertDatabaseHas('journals', [
            'transaction_type' => 'invoice_correction',
            'type' => 'debit', // New corrected amount
        ]);
    }

    #[Test]
    public function it_processes_payment_for_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'paid_amount' => 0,
            'status' => 'not paid',
        ]);

        $paymentData = [
            'amount' => 500,
            'method' => 'mpesa',
            'reference' => 'MPESA-123',
            'status' => 'paid',
            'send_sms' => false,
        ];

        $this->service->applyPayment($invoice, $paymentData, false);

        // Verify payment was created
        $this->assertDatabaseHas('payments', [
            'invoice_id' => $invoice->id,
            'amount' => 500,
            'method' => 'mpesa',
        ]);

        // Verify invoice was updated
        $invoice->refresh();
        $this->assertNotNull($invoice->balance);
        $this->assertEquals('Partial Payment', $invoice->status);
        $this->assertEquals('open', $invoice->state);
    }

    #[Test]
    public function it_processes_full_payment_for_invoice()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 800,
            'paid_amount' => 0,
            'status' => 'not paid',
        ]);

        $paymentData = [
            'amount' => 800,
            'method' => 'cash',
            'reference' => 'CASH-456',
            'status' => 'paid',
            'send_sms' => false,
        ];

        $this->service->applyPayment($invoice, $paymentData, false);

        // Verify invoice was fully paid
        $invoice->refresh();
        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals('Fully Paid', $invoice->status);
        $this->assertEquals('closed', $invoice->state);
    }

    #[Test]
    public function it_checks_if_invoice_can_be_reversed()
    {
        $unpaidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'not paid',
        ]);

        $paidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'fully paid',
        ]);

        $reversedInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'reversed',
        ]);

        $this->assertTrue($unpaidInvoice->can_be_reversed);
        $this->assertFalse($paidInvoice->can_be_reversed);
        $this->assertFalse($reversedInvoice->can_be_reversed);
    }

    #[Test]
    public function it_checks_if_invoice_can_be_corrected()
    {
        $unpaidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'not paid',
        ]);

        $paidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'fully paid',
        ]);

        $reversedInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'reversed',
        ]);

        $this->assertTrue($unpaidInvoice->can_be_corrected);
        $this->assertFalse($paidInvoice->can_be_corrected);
        $this->assertFalse($reversedInvoice->can_be_corrected);
    }

    #[Test]
    public function it_checks_if_invoice_can_receive_payment()
    {
        $unpaidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'not paid',
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        $paidInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'fully paid',
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 1000, // Fully paid
        ]);

        $reversedInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'reversed',
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        $this->assertTrue($unpaidInvoice->can_receive_payment);
        $this->assertFalse($paidInvoice->can_receive_payment);
        $this->assertFalse($reversedInvoice->can_receive_payment);
    }

    #[Test]
    public function it_gets_invoice_summary()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-SUMMARY-001',
            'balance_brought_forward' => 200,
            'amount' => 1300,
            'paid_amount' => 300, // 1500 total - 300 paid = 1200 balance
            'status' => 'not paid',
            'due_date' => now()->addDays(7),
        ]);

        $summary = $invoice->summary;

        $this->assertEquals('INV-SUMMARY-001', $summary['invoice_number']);
        $this->assertEquals($this->customer->name, $summary['customer_name']);
        $this->assertEquals(1500, $summary['total_amount']);
        $this->assertEquals(1200, $summary['balance']);
        $this->assertEquals('not paid', $summary['status']);
        $this->assertNotNull($summary['due_date']);
    }

    #[Test]
    public function it_handles_correction_with_no_change()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 'not paid',
        ]);

        $this->service->adjustAmount($invoice, 1000, 'No change');

        // Verify invoice unchanged
        $invoice->refresh();
        $this->assertEquals(1000, $invoice->total_amount);
        $this->assertEquals(1000, $invoice->balance);
    }

    #[Test]
    public function it_handles_correction_increasing_amount()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 500,
            'balance' => 500,
            'status' => 'not paid',
        ]);

        $this->service->adjustAmount($invoice, 800, 'Additional charges');

        // Verify invoice increased
        $invoice->refresh();
        $this->assertEquals(800, $invoice->total_amount);
        $this->assertEquals(800, $invoice->balance);
    }

    #[Test]
    public function it_handles_correction_decreasing_amount()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 'not paid',
        ]);

        $this->service->adjustAmount($invoice, 700, 'Discount applied');

        // Verify invoice decreased
        $invoice->refresh();
        $this->assertEquals(700, $invoice->total_amount);
        $this->assertEquals(700, $invoice->balance);
    }

    #[Test]
    public function it_maintains_transaction_consistency_on_reversal()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 'not paid',
        ]);

        $originalJournalCount = Journal::count();

        $this->service->reverseInvoice($invoice, 'Test', '', false);

        // Verify journals were created
        $newJournalCount = Journal::count();
        $this->assertGreaterThan($originalJournalCount, $newJournalCount);
    }

    #[Test]
    public function it_maintains_transaction_consistency_on_correction()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 'not paid',
        ]);

        $originalJournalCount = Journal::count();

        $this->service->adjustAmount($invoice, 800, 'Test correction');

        // Verify journals were created (reversal + new entries)
        $newJournalCount = Journal::count();
        $this->assertGreaterThan($originalJournalCount, $newJournalCount);
    }

    #[Test]
    public function it_maintains_transaction_consistency_on_payment()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'total_amount' => 1000,
            'balance' => 1000,
            'status' => 'not paid',
        ]);

        $paymentData = [
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'TEST-001',
            'status' => 'paid',
            'send_sms' => false,
        ];

        $originalPaymentCount = Payment::count();
        $originalJournalCount = Journal::count();

        $this->service->applyPayment($invoice, $paymentData, false);

        // Verify payment and journals were created
        $this->assertEquals($originalPaymentCount + 1, Payment::count());
        $this->assertGreaterThan($originalJournalCount, Journal::count());
    }

    #[Test]
    public function it_preserves_invoice_history_on_reversal()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'invoice_number' => 'INV-HISTORY-001',
            'total_amount' => 1000,
            'notes' => 'Original notes',
        ]);

        $this->service->reverseInvoice($invoice, 'Error found', 'Wrong customer', false);

        $invoice->refresh();
        
        // Verify original notes preserved
        $this->assertStringContainsString('Original notes', $invoice->notes);
        $this->assertStringContainsString('Error found', $invoice->notes);
    }

    #[Test]
    public function it_preserves_invoice_history_on_correction()
    {
        $invoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
            'notes' => 'Original invoice notes',
        ]);

        $this->service->adjustAmount($invoice, 800, 'Calculation error');

        $invoice->refresh();
        
        // Verify original notes preserved with correction history
        $this->assertStringContainsString('Original invoice notes', $invoice->notes);
        $this->assertStringContainsString('Correction', $invoice->notes);
        $this->assertStringContainsString('1000', $invoice->notes); // Original amount
        $this->assertStringContainsString('800', $invoice->notes); // Corrected amount
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

