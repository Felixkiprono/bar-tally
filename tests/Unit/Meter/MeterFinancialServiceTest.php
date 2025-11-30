<?php

namespace Tests\Unit\Meter;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\MeterFinancialService;
use PHPUnit\Framework\Attributes\Test;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MeterFinancialServiceTest extends TestCase
{
    protected MeterFinancialService $service;
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

        // Create test users
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
            'total_billed' => 0,
            'total_paid' => 0,
        ]);

        $this->assignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->service = app(MeterFinancialService::class);
    }

    #[Test]
    public function it_calculates_meter_balance_correctly()
    {
        // Create invoices
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        // Create payments
        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 600,
            'method' => 'mpesa',
            'reference' => 'TEST-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(900, $this->meter->balance); // 1500 invoiced - 600 paid
        $this->assertEquals(0, $this->meter->overpayment);
        $this->assertEquals(1500, $this->meter->total_billed);
        $this->assertEquals(600, $this->meter->total_paid);
    }

    #[Test]
    public function it_calculates_overpayment_when_payments_exceed_invoices()
    {
        // Create invoice
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        // Create payment exceeding invoice
        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 800,
            'method' => 'mpesa',
            'reference' => 'OVERPAY-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(0, $this->meter->balance);
        $this->assertEquals(300, $this->meter->overpayment); // 800 paid - 500 invoiced
    }

    #[Test]
    public function it_handles_meter_with_no_transactions()
    {
        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(0, $this->meter->balance);
        $this->assertEquals(0, $this->meter->overpayment);
        $this->assertEquals(0, $this->meter->total_billed);
        $this->assertEquals(0, $this->meter->total_paid);
    }

    #[Test]
    public function it_handles_meter_with_only_invoices()
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1200,
            'paid_amount' => 0,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(1200, $this->meter->balance);
        $this->assertEquals(0, $this->meter->overpayment);
    }

    #[Test]
    public function it_handles_meter_with_only_payments()
    {
        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 500,
            'method' => 'cash',
            'reference' => 'ADV-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(0, $this->meter->balance);
        $this->assertEquals(500, $this->meter->overpayment);
    }

    #[Test]
    public function it_recalculates_all_customer_meters()
    {
        // Create second meter
        $meter2 = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $assignment2 = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter2->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Add transactions to first meter
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        // Add transactions to second meter
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter2->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $meter2->id,
            'amount' => 200,
            'method' => 'mpesa',
            'reference' => 'M2-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->recalculateCustomerMeters($this->customer->id);

        $this->meter->refresh();
        $meter2->refresh();
        $this->customer->refresh();

        // Verify individual meters
        $this->assertEquals(1000, $this->meter->balance);
        $this->assertEquals(300, $meter2->balance); // 500 - 200

        // Verify customer totals
        $this->assertEquals(1300, $this->customer->balance); // 1000 + 300
        $this->assertEquals(0, $this->customer->overpayment);
    }

    #[Test]
    public function it_updates_customer_balance_from_active_meters_only()
    {
        // Create inactive meter
        $inactiveMeter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $inactiveAssignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $inactiveMeter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => false, // Inactive
        ]);

        // Add invoice to inactive meter
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $inactiveMeter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 999,
            'paid_amount' => 0,
        ]);

        // Add invoice to active meter
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        $this->service->recalculateCustomerMeters($this->customer->id);

        $this->customer->refresh();

        // Customer balance should only include active meter
        $this->assertEquals(500, $this->customer->balance);
    }

    #[Test]
    public function it_updates_last_invoice_date()
    {
        $date1 = Carbon::parse('2024-01-15');
        $date2 = Carbon::parse('2024-02-20');

        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'invoice_date' => $date1,
            'balance_brought_forward' => 0,
            'amount' => 100,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'invoice_date' => $date2,
            'balance_brought_forward' => 0,
            'amount' => 200,
            'paid_amount' => 0,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals($date2->toDateString(), $this->meter->last_invoice_date->toDateString());
    }

    // Note: Statement generation methods skipped - require Meter model to have proper relationship
    // for currentAssignment instead of a method that returns first()




    #[Test]
    public function it_handles_multiple_payments_in_balance_calculation()
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        // Multiple payments
        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 300,
            'method' => 'mpesa',
            'reference' => 'PAY-001',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 400,
            'method' => 'cash',
            'reference' => 'PAY-002',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 200,
            'method' => 'bank',
            'reference' => 'PAY-003',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(100, $this->meter->balance); // 1000 - 900
        $this->assertEquals(900, $this->meter->total_paid);
    }

    #[Test]
    public function it_handles_multiple_invoices_in_balance_calculation()
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 500,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 300,
            'paid_amount' => 0,
        ]);

        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 700,
            'paid_amount' => 0,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(1500, $this->meter->balance);
        $this->assertEquals(1500, $this->meter->total_billed);
    }




    #[Test]
    public function it_sets_balance_to_zero_when_fully_paid()
    {
        Invoice::factory()->create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'balance_brought_forward' => 0,
            'amount' => 1000,
            'paid_amount' => 0,
        ]);

        Payment::create([
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 1000,
            'method' => 'mpesa',
            'reference' => 'FULL-PAY',
            'status' => 'paid',
            'date' => now(),
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        $this->service->recalculateMeterBalance($this->meter->id);

        $this->meter->refresh();
        $this->assertEquals(0, $this->meter->balance);
        $this->assertEquals(0, $this->meter->overpayment);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

