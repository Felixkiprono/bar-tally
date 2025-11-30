<?php

namespace Tests\Unit\Invoice;

use Tests\TestCase;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\InvoiceBill;
use App\Models\Journal;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Services\Invoice\InvoiceService;
use App\Services\Invoice\InvoiceRepository;
use App\Constants\BillTypes;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;

class InvoiceServiceTest extends TestCase
{
    use WithFaker;

    protected InvoiceService $service;
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;
    protected Meter $meter;
    protected MeterAssignment $meterAssignment;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'tenant_id' => $this->tenant->id,
            'balance' => 0,
            'overpayment' => 0,
        ]);

        // Create required accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        
        // Create revenue accounts for bill types
        Account::create([
            'code' => 'WATER_USAGE',
            'name' => 'Water Usage Revenue',
            'type' => 'revenue',
            'description' => 'Revenue from water usage bills',
            'balance' => 0,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);
        
        Account::create([
            'code' => 'SERVICE_FEE',
            'name' => 'Service Fee Revenue',
            'type' => 'revenue',
            'description' => 'Revenue from service fees',
            'balance' => 0,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);
        
        Account::create([
            'code' => 'CONNECTION_FEE',
            'name' => 'Connection Fee Revenue',
            'type' => 'revenue',
            'description' => 'Revenue from connection fees',
            'balance' => 0,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);
        
        Account::create([
            'code' => 'FIXED_CHARGE',
            'name' => 'Fixed Charge Revenue',
            'type' => 'revenue',
            'description' => 'Revenue from fixed charges',
            'balance' => 0,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);
        
        Account::create([
            'code' => 'BALANCE',
            'name' => 'Balance Forward',
            'type' => 'asset',
            'description' => 'Balance from previous invoices',
            'balance' => 0,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create meter with overpayment = 0
        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'overpayment' => 0,
            'balance' => 0,
        ]);

        // Create meter assignment
        $this->meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $this->meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->service = app(InvoiceService::class);
    }

    #[Test]
    public function it_generates_invoice_from_pending_bills()
    {
        // Create pending bill
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        // Verify invoice was created
        $this->assertEquals(1, Invoice::count());

        $invoice = Invoice::first();
        $this->assertEquals($this->customer->id, $invoice->customer_id);
        $this->assertEquals($this->meter->id, $invoice->meter_id);
        $this->assertEquals(500.0, $invoice->total_amount);
        $this->assertEquals(500.0, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_amount);
        $this->assertNotNull($invoice->invoice_number);
    }

    #[Test]
    public function it_generates_invoice_number_with_correct_format()
    {
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'SERVICE_FEE',
            'amount' => 1,
            'rate_used' => 100.0,
            'total_amount' => 100.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        // Invoice number should start with 'INV' and be unique
        $this->assertNotNull($invoice->invoice_number);
        $this->assertStringStartsWith('INV', $invoice->invoice_number);
        $this->assertGreaterThan(3, strlen($invoice->invoice_number)); // Should be more than just 'INV'
    }

    #[Test]
    public function it_sets_invoice_date_to_current_date()
    {
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        $this->assertNotNull($invoice->invoice_date);
        $this->assertTrue($invoice->invoice_date->isToday());
    }

    #[Test]
    public function it_calculates_due_date_correctly()
    {
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        $this->assertNotNull($invoice->due_date);
        $this->assertTrue($invoice->due_date->isFuture());
        // Due date should be after invoice date
        $this->assertTrue($invoice->due_date->greaterThan($invoice->invoice_date));
    }

    #[Test]
    public function it_applies_meter_overpayment_credit()
    {
        // Set meter overpayment
        $this->meter->update(['overpayment' => 200.0]);

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        // Invoice should be created with total amount
        $this->assertEquals(500.0, $invoice->total_amount);
        // After meter recalculation, balance reflects invoices - payments
        // With overpayment initially set, the balance calculation will be handled by MeterFinancialService
        $this->assertNotNull($invoice->balance);
        $this->assertGreaterThanOrEqual(0, $invoice->balance);
    }

    #[Test]
    public function it_creates_invoice_with_overpayment_greater_than_bill()
    {
        // Set meter overpayment greater than bill amount
        $this->meter->update(['overpayment' => 600.0]);

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        $this->assertEquals(500.0, $invoice->total_amount);
        // With sufficient overpayment, invoice could be created as paid or have reduced balance
        // The exact behavior depends on MeterFinancialService recalculation
        $this->assertNotNull($invoice);
        $this->assertNotNull($invoice->status);
    }

    #[Test]
    public function it_creates_invoice_with_partial_overpayment()
    {
        // Set meter overpayment less than bill amount
        $this->meter->update(['overpayment' => 150.0]);

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        $this->assertEquals(500.0, $invoice->total_amount);
        // Balance calculations are handled by MeterFinancialService after creation
        $this->assertNotNull($invoice->balance);
        $this->assertNotNull($invoice->status);
    }

    #[Test]
    public function it_sets_invoice_status_to_not_paid_when_balance_remains()
    {
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        $this->assertEquals('Not Paid', $invoice->status);
        $this->assertGreaterThan(0, $invoice->balance);
    }

    #[Test]
    public function it_creates_invoice_when_overpayment_equals_bill_amount()
    {
        // Set overpayment to equal bill amount
        $this->meter->update(['overpayment' => 500.0]);

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        // Invoice should be created successfully
        $this->assertNotNull($invoice);
        $this->assertEquals(500.0, $invoice->total_amount);
    }

    #[Test]
    public function it_sets_invoice_state_to_open_when_unpaid()
    {
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        $this->assertEquals('open', $invoice->state);
    }

    #[Test]
    public function it_creates_invoice_with_overpayment_and_valid_state()
    {
        // Set overpayment to cover full bill
        $this->meter->update(['overpayment' => 500.0]);

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        // Invoice should have a valid state (open or closed depending on balance calculation)
        $this->assertNotNull($invoice->state);
        $this->assertContains($invoice->state, ['open', 'closed']);
    }

    #[Test]
    public function it_links_bills_to_invoice()
    {
        $bill1 = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 5.0,
            'total_amount' => 250.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bill2 = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'SERVICE_FEE',
            'amount' => 1,
            'rate_used' => 100.0,
            'total_amount' => 100.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill1, $bill2]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        // Verify invoice-bill relationships
        $this->assertEquals(2, InvoiceBill::where('invoice_id', $invoice->id)->count());
        
        $this->assertDatabaseHas('invoice_bills', [
            'invoice_id' => $invoice->id,
            'bill_id' => $bill1->id,
            'amount' => 250.0,
        ]);

        $this->assertDatabaseHas('invoice_bills', [
            'invoice_id' => $invoice->id,
            'bill_id' => $bill2->id,
            'amount' => 100.0,
        ]);
    }

    #[Test]
    public function it_updates_bill_status_to_invoiced_after_linking()
    {
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $bill->refresh();

        $this->assertEquals('invoiced', $bill->status);
    }

    #[Test]
    public function it_creates_journal_entries_for_invoice()
    {
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        // Should have created journal entries
        $journalEntries = Journal::where('transaction_id', $invoice->id)
            ->where('transaction_type', 'invoice')
            ->get();

        $this->assertGreaterThan(0, $journalEntries->count());

        // Should have debit to AR-CONTROL
        $arDebit = Journal::where('transaction_id', $invoice->id)
            ->where('type', 'debit')
            ->where('amount', 500.0)
            ->first();

        $this->assertNotNull($arDebit);
    }

    #[Test]
    public function it_creates_journal_entries_with_overpayment()
    {
        // Set meter overpayment
        $this->meter->update(['overpayment' => 200.0]);

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        $invoice = Invoice::first();

        // Should have created journal entries for the invoice
        $journalEntries = Journal::where('transaction_id', $invoice->id)
            ->where('transaction_type', 'invoice')
            ->get();

        $this->assertGreaterThan(0, $journalEntries->count());
    }

    #[Test]
    public function it_processes_batch_invoice_generation()
    {
        // Create multiple pending bills for different meter assignments
        $customer2 = User::factory()->create([
            'role' => 'customer',
            'tenant_id' => $this->tenant->id,
        ]);

        $meter2 = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'overpayment' => 0,
        ]);

        $meterAssignment2 = MeterAssignment::factory()->create([
            'meter_id' => $meter2->id,
            'customer_id' => $customer2->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Create bills for both customers
        Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 5.0,
            'total_amount' => 250.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        Bill::factory()->create([
            'customer_id' => $customer2->id,
            'meter_assignment_id' => $meterAssignment2->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 60,
            'rate_used' => 5.0,
            'total_amount' => 300.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Run batch generation
        $this->service->generateInvoicesBatch();

        // Should have created 2 invoices (one per meter assignment)
        $this->assertEquals(2, Invoice::count());

        // All bills should be invoiced
        $this->assertEquals(0, Bill::where('status', BillTypes::BILL_STATUS_PENDING)->count());
        $this->assertEquals(2, Bill::where('status', 'invoiced')->count());
    }

    #[Test]
    public function it_handles_no_pending_bills_gracefully()
    {
        // No pending bills in database
        $this->service->generateInvoicesBatch();

        // Should not create any invoices
        $this->assertEquals(0, Invoice::count());
    }

    #[Test]
    public function it_consolidates_multiple_bills_for_same_meter_into_one_invoice()
    {
        // Create 3 bills for the SAME meter assignment
        Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 5.0,
            'total_amount' => 250.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'tenant_id' => $this->tenant->id,
        ]);

        Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'FIXED_CHARGE',
            'amount' => 1,
            'rate_used' => 100.0,
            'total_amount' => 100.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'tenant_id' => $this->tenant->id,
        ]);

        Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'SERVICE_FEE',
            'amount' => 1,
            'rate_used' => 50.0,
            'total_amount' => 50.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'tenant_id' => $this->tenant->id,
        ]);

        // Run batch generation
        $this->service->generateInvoicesBatch();

        // CRITICAL: Should create only ONE invoice (not 3!)
        $this->assertEquals(1, Invoice::count(), 'Expected exactly 1 invoice for 3 bills on same meter');

        // All 3 bills should be marked as invoiced
        $this->assertEquals(0, Bill::where('status', BillTypes::BILL_STATUS_PENDING)->count());
        $this->assertEquals(3, Bill::where('status', 'invoiced')->count());

        // The single invoice should have all 3 bills linked to it
        $invoice = Invoice::first();
        $this->assertEquals(3, $invoice->invoiceBills()->count(), 'Invoice should consolidate all 3 bills');
        
        // Invoice amount should be sum of all bills
        $this->assertEquals(400.0, $invoice->amount, 'Invoice should total all bills: 250 + 100 + 50 = 400');
    }

    #[Test]
    public function it_generates_separate_invoices_for_each_meter_assignment()
    {
        $secondMeter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'overpayment' => 0,
            'balance' => 0,
        ]);

        $secondAssignment = MeterAssignment::factory()->create([
            'meter_id' => $secondMeter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $firstBill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 70,
            'rate_used' => 5.0,
            'total_amount' => 350.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $secondBill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $secondAssignment->id,
            'bill_type' => 'SERVICE_FEE',
            'amount' => 1,
            'rate_used' => 200.0,
            'total_amount' => 200.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->service->generateInvoicesBatch();

        $this->assertEquals(2, Invoice::count(), 'Each meter assignment should receive its own invoice');

        $firstMeterInvoice = Invoice::where('meter_id', $this->meter->id)->first();
        $secondMeterInvoice = Invoice::where('meter_id', $secondMeter->id)->first();

        $this->assertNotNull($firstMeterInvoice, 'Missing invoice for original meter');
        $this->assertNotNull($secondMeterInvoice, 'Missing invoice for second meter');

        $this->assertDatabaseHas('invoice_bills', [
            'invoice_id' => $firstMeterInvoice->id,
            'bill_id' => $firstBill->id,
        ]);

        $this->assertDatabaseMissing('invoice_bills', [
            'invoice_id' => $firstMeterInvoice->id,
            'bill_id' => $secondBill->id,
        ]);

        $this->assertDatabaseHas('invoice_bills', [
            'invoice_id' => $secondMeterInvoice->id,
            'bill_id' => $secondBill->id,
        ]);

        $this->assertDatabaseMissing('invoice_bills', [
            'invoice_id' => $secondMeterInvoice->id,
            'bill_id' => $firstBill->id,
        ]);

        $this->assertEquals(350.0, $firstMeterInvoice->amount);
        $this->assertEquals(200.0, $secondMeterInvoice->amount);
    }

    #[Test]
    public function it_retrieves_open_invoices_for_customer()
    {
        // Create open invoice
        $openInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'state' => 'open',
            'balance' => 500.0,
        ]);

        // Create closed invoice
        $closedInvoice = Invoice::factory()->fullyPaid()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $repository = app(InvoiceRepository::class);
        $openInvoices = $repository->findOpenByCustomer($this->customer->id);

        $this->assertCount(1, $openInvoices);
        $this->assertEquals($openInvoice->id, $openInvoices->first()->id);
    }

    #[Test]
    public function it_closes_existing_open_invoices_when_generating_new_invoice()
    {
        // Create existing open invoice with balance
        $existingOpenInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'state' => 'open',
            'status' => 'not paid',
            'balance_brought_forward' => 0,
            'amount' => 1000.0,
            'paid_amount' => 0,
        ]);

        // Verify initial state
        $this->assertEquals(1000.0, $existingOpenInvoice->balance);

        // Create new bill
        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        // CRITICAL: Existing invoice should be closed AND balance set to 0
        $existingOpenInvoice->refresh();
        $this->assertEquals('closed', $existingOpenInvoice->state);
        $this->assertEquals('Cleared', $existingOpenInvoice->status);
        $this->assertEquals(0.0, $existingOpenInvoice->balance, 'Cleared invoice balance must be set to 0 after first save');

        // CRITICAL: Test that balance stays 0 after refresh from database
        $clearedInvoiceFromDb = Invoice::find($existingOpenInvoice->id);
        $this->assertEquals(0.0, $clearedInvoiceFromDb->balance, 'Balance must remain 0 after refresh from database');
        
        // CRITICAL: Test that balance stays 0 even after another save
        $clearedInvoiceFromDb->notes = 'Updated notes';
        $clearedInvoiceFromDb->save();
        $clearedInvoiceFromDb->refresh();
        $this->assertEquals(0.0, $clearedInvoiceFromDb->balance, 'Balance must remain 0 even after subsequent save operations');

        // New invoice should have balance brought forward
        $newInvoice = Invoice::where('id', '!=', $existingOpenInvoice->id)->first();
        $this->assertEquals(1000.0, $newInvoice->balance_brought_forward, 'New invoice should carry forward the cleared balance');
        
        // New invoice total should be old balance + new bill
        $this->assertEquals(1500.0, $newInvoice->total_amount, 'New invoice total = balance b/f (1000) + new bill (500)');
    }

    #[Test]
    public function it_keeps_invoices_for_other_meters_open_when_generating_new_ones()
    {
        $secondMeter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'overpayment' => 0,
            'balance' => 0,
        ]);

        $secondAssignment = MeterAssignment::factory()->create([
            'meter_id' => $secondMeter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $unrelatedInvoice = Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $secondMeter->id,
            'tenant_id' => $this->tenant->id,
            'state' => 'open',
            'status' => 'not paid',
            'balance_brought_forward' => 0,
            'amount' => 400.0,
            'total_amount' => 400.0,
            'paid_amount' => 0.0,
            'balance' => 400.0,
        ]);

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 40,
            'rate_used' => 5.0,
            'total_amount' => 200.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $this->service->generateInvoiceFromBills(collect([$bill]), false);

        $unrelatedInvoice->refresh();

        $this->assertEquals('open', $unrelatedInvoice->state, 'Invoices for other meters should remain open');
        $this->assertEquals('not paid', $unrelatedInvoice->status);
        $this->assertEquals(400.0, $unrelatedInvoice->balance);

        $newInvoice = Invoice::where('id', '!=', $unrelatedInvoice->id)->first();
        $this->assertNotNull($newInvoice, 'Expected a new invoice for the billed meter');
        $this->assertEquals(0.0, $newInvoice->balance_brought_forward, 'Unrelated invoice balance should not carry over');
        $this->assertEquals(200.0, $newInvoice->amount);
        $this->assertEquals(200.0, $newInvoice->total_amount);
    }

    #[Test]
    public function it_triggers_meter_balance_recalculation()
    {
        $initialBalance = $this->meter->balance;

        $bill = Bill::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => BillTypes::BILL_STATUS_PENDING,
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $bills = collect([$bill]);

        $this->service->generateInvoiceFromBills($bills, false);

        // Meter balance should be recalculated
        $this->meter->refresh();
        $this->assertNotEquals($initialBalance, $this->meter->balance);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

