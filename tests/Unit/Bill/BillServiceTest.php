<?php

namespace Tests\Unit\Bill;

use Tests\TestCase;
use App\Models\Bill;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\MeterConfiguration;
use App\Services\BillService;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;

class BillServiceTest extends TestCase
{
    protected BillService $service;
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
        $this->customer = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create meter configurations
        MeterConfiguration::factory()->create([
            'code' => 'METER_READING',
            'amount' => 50,
            'tenant_id' => $this->tenant->id,
        ]);

        MeterConfiguration::factory()->create([
            'code' => 'SERVICE_COST',
            'amount' => 100,
            'tenant_id' => $this->tenant->id,
        ]);

        MeterConfiguration::factory()->create([
            'code' => 'SERVICE_FEE',
            'amount' => 150,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create meter and assignment
        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'last_reading' => 100,
        ]);

        $this->assignment = MeterAssignment::factory()->create([
            'meter_id' => $this->meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->service = app(BillService::class);
    }

    #[Test]
    public function it_generates_bills_from_meter_reading_with_same_reference()
    {
        // Create a meter reading
        $meterReading = MeterReading::create([
            'meter_id' => $this->meter->id,
            'reading_value' => 200, // 100 units consumed (200 - 100)
            'reading_date' => now(),
            'reader_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Generate bills from meter reading
        $bills = $this->service->generateRegularReadingBill($meterReading);

        // Should create 2 bills (consumption + service cost)
        $this->assertCount(2, $bills);

        // Both bills should have the same reference
        $firstBillRef = $bills->first()->bill_ref;
        $this->assertNotNull($firstBillRef);
        
        foreach ($bills as $bill) {
            $this->assertEquals($firstBillRef, $bill->bill_ref);
            $this->assertMatchesRegularExpression('/^\d{4}-[A-Z]{3}-\d{3}$/', $bill->bill_ref);
        }
    }

    #[Test]
    public function it_generates_unique_reference_for_each_meter_reading()
    {
        // Create first meter reading
        $reading1 = MeterReading::create([
            'meter_id' => $this->meter->id,
            'reading_value' => 200,
            'reading_date' => now(),
            'reader_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $bills1 = $this->service->generateRegularReadingBill($reading1);

        // Update meter's last reading
        $this->meter->last_reading = 200;
        $this->meter->save();

        // Create second meter reading
        $reading2 = MeterReading::create([
            'meter_id' => $this->meter->id,
            'reading_value' => 300,
            'reading_date' => now()->addDay(),
            'reader_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $bills2 = $this->service->generateRegularReadingBill($reading2);

        // Both readings should generate bills
        $this->assertNotEmpty($bills1);
        $this->assertNotEmpty($bills2);

        // References should be different
        $ref1 = $bills1->first()->bill_ref;
        $ref2 = $bills2->first()->bill_ref;
        
        $this->assertNotEquals($ref1, $ref2);
    }

    #[Test]
    public function it_uses_provided_reference_when_given()
    {
        $customReference = '2025-JAN-999';

        $bill = $this->service->generateServiceFeeBill($this->assignment, $customReference);

        $this->assertNotNull($bill);
        $this->assertEquals($customReference, $bill->bill_ref);
    }

    #[Test]
    public function it_generates_reference_when_not_provided()
    {
        $bill = $this->service->generateServiceFeeBill($this->assignment);

        $this->assertNotNull($bill);
        $this->assertNotNull($bill->bill_ref);
        $this->assertMatchesRegularExpression('/^\d{4}-[A-Z]{3}-\d{3}$/', $bill->bill_ref);
    }

    #[Test]
    public function it_shares_reference_across_batch_when_provided()
    {
        $sharedReference = '2025-OCT-888';

        // Create multiple meter readings
        $reading1 = MeterReading::create([
            'meter_id' => $this->meter->id,
            'reading_value' => 200,
            'reading_date' => now(),
            'reader_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $reading2 = MeterReading::create([
            'meter_id' => $this->meter->id,
            'reading_value' => 300,
            'reading_date' => now()->addDay(),
            'reader_id' => $this->admin->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Update meter's last reading after first bill
        $this->meter->last_reading = 200;
        $this->meter->save();

        // Generate bills with the same reference
        $bills1 = $this->service->generateRegularReadingBill($reading1, $sharedReference);
        
        // Update meter's last reading after first bill
        $this->meter->last_reading = 300;
        $this->meter->save();
        
        $bills2 = $this->service->generateRegularReadingBill($reading2, $sharedReference);

        // All bills should share the same reference
        foreach ($bills1 as $bill) {
            $this->assertEquals($sharedReference, $bill->bill_ref);
        }

        foreach ($bills2 as $bill) {
            $this->assertEquals($sharedReference, $bill->bill_ref);
        }
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

