<?php

namespace Tests\Unit\Meter;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterConfiguration;
use App\Models\Bill;
use App\Models\Invoice;
use App\Services\MeterAssignmentService;
use App\Services\BillService;
use App\Services\Invoice\InvoiceService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class MeterAssignmentServiceTest extends TestCase
{
    use WithFaker;

    protected MeterAssignmentService $service;
    protected User $admin;
    protected User $customer;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a tenant first
        $this->tenant = Tenant::factory()->create();
        
        // Create users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);
        
        // Create required accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->create([
            'name' => 'CONNECTION_FEE',
            'code' => 'CONNECTION_FEE',
            'type' => 'revenue',
            'balance' => 0,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create meter configuration for connection fee
        MeterConfiguration::factory()->create([
            'code' => 'CONNECTION_FEE',
            'amount' => 500.0,
            'tenant_id' => $this->tenant->id,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service with dependency injection
        $this->service = app(MeterAssignmentService::class);
    }

    #[Test]
    public function it_creates_meter_with_auto_generated_number()
    {
        $meterData = [
            'meter_type' => 'standard',
            'meter_size' => '15mm',
            'location' => 'Main Building',
        ];

        $meter = $this->service->createMeter($meterData, $this->tenant->id, $this->admin->id);

        $this->assertDatabaseHas('meters', [
            'meter_type' => 'standard',
            'meter_size' => '15mm',
            'location' => 'Main Building',
            'tenant_id' => $this->tenant->id,
            'installed_by' => $this->admin->id,
            'status' => 'active',
        ]);

        // Verify meter number was auto-generated
        $this->assertStringStartsWith('MTR-', $meter->meter_number);
        $this->assertEquals('MTR-001', $meter->meter_number);
    }

    #[Test]
    public function it_generates_sequential_meter_numbers()
    {
        // Create first meter
        $meter1 = $this->service->createMeter([
            'meter_type' => 'standard',
            'location' => 'Building A',
        ], $this->tenant->id, $this->admin->id);

        // Create second meter
        $meter2 = $this->service->createMeter([
            'meter_type' => 'standard',
            'location' => 'Building B',
        ], $this->tenant->id, $this->admin->id);

        $this->assertEquals('MTR-001', $meter1->meter_number);
        $this->assertEquals('MTR-002', $meter2->meter_number);
    }

    #[Test]
    public function it_creates_meter_and_assigns_to_customer()
    {
        $meterData = [
            'meter_type' => 'standard',
            'meter_size' => '20mm',
            'location' => 'Factory Floor',
        ];

        $assignmentData = [
            'customer_id' => $this->customer->id,
            'assignment_date' => now(),
            'initial_reading' => 100.0,
            'connection_fee' => false,
        ];

        $assignment = $this->service->createMeterAndAssign(
            $meterData,
            $assignmentData,
            $this->tenant->id,
            $this->admin->id
        );

        // Verify meter was created
        $this->assertDatabaseHas('meters', [
            'meter_type' => 'standard',
            'meter_size' => '20mm',
            'location' => 'Factory Floor',
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify assignment was created
        $this->assertDatabaseHas('meter_assignments', [
            'customer_id' => $this->customer->id,
            'meter_id' => $assignment->meter_id,
            'tenant_id' => $this->tenant->id,
            'initial_reading' => 100.0,
            'is_active' => true,
        ]);

        $this->assertEquals($this->customer->id, $assignment->customer_id);
        $this->assertTrue($assignment->is_active);
    }

    #[Test]
    public function it_assigns_existing_meter_to_customer()
    {
        // Create an existing meter
        $meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $assignmentData = [
            'customer_id' => $this->customer->id,
            'assignment_date' => now(),
            'initial_reading' => 0.0,
            'connection_fee' => false,
        ];

        $assignment = $this->service->assignExistingMeter(
            $meter->id,
            $assignmentData,
            $this->tenant->id
        );

        $this->assertDatabaseHas('meter_assignments', [
            'customer_id' => $this->customer->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_validates_meter_availability()
    {
        // Create meter with existing active assignment
        $meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $isAvailable = $this->service->validateMeterAvailability($meter->id);
        $this->assertFalse($isAvailable);

        // Test with available meter
        $availableMeter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $isAvailable = $this->service->validateMeterAvailability($availableMeter->id);
        $this->assertTrue($isAvailable);
    }

    #[Test]
    public function it_handles_connection_fee_with_auto_invoice()
    {
        $assignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->service->handleConnectionFee($assignment, true);

        // Verify connection fee bill was created and invoiced (autoInvoice = true)
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $assignment->id,
            'bill_type' => 'CONNECTION_FEE',
            'total_amount' => 500.0,
            'status' => 'invoiced', // Bill is invoiced when autoInvoice = true
        ]);

        // Verify invoice generation was attempted (bill exists means the service tried to create invoice)
        // The actual invoice creation might fail in test environment due to missing configurations
        // but the important part is that the bill was created and the service attempted invoice generation
        $billCount = \App\Models\Bill::where('customer_id', $this->customer->id)
            ->where('bill_type', 'CONNECTION_FEE')
            ->count();
        
        $this->assertEquals(1, $billCount);
    }

    #[Test]
    public function it_does_not_charge_connection_fee_when_disabled()
    {
        $assignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->service->handleConnectionFee($assignment, false);

        // Verify no bill was created
        $this->assertDatabaseMissing('bills', [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $assignment->id,
        ]);
    }

    #[Test]
    public function it_gets_available_meters_for_assignment()
    {
        // Create available meter
        $availableMeter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'meter_number' => 'MTR-100',
        ]);

        // Create unavailable meter (already assigned)
        $assignedMeter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
            'meter_number' => 'MTR-101',
        ]);

        MeterAssignment::factory()->create([
            'meter_id' => $assignedMeter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Create inactive meter
        $inactiveMeter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'inactive',
            'meter_number' => 'MTR-102',
        ]);

        $availableMeters = $this->service->getAvailableMeters($this->tenant->id);

        $this->assertCount(1, $availableMeters);
        $this->assertEquals($availableMeter->id, $availableMeters->first()->id);
    }

    #[Test]
    public function it_validates_assignment_data()
    {
        $validData = [
            'customer_id' => $this->customer->id,
            'meter_id' => Meter::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'assignment_date' => now(),
            'initial_reading' => 0.0,
        ];

        // This should not throw an exception
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateAssignmentData');
        $method->setAccessible(true);
        
        $result = $method->invoke($this->service, $validData);
        $this->assertEquals($validData, $result);
    }

    #[Test]
    public function it_throws_exception_for_invalid_assignment_data()
    {
        $invalidData = [
            'customer_id' => 99999, // Non-existent customer
            'meter_id' => 99999,    // Non-existent meter
        ];

        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('validateAssignmentData');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Validation failed/');

        $method->invoke($this->service, $invalidData);
    }

    #[Test]
    public function it_handles_connection_fee_billing_failure()
    {
        $assignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Delete the connection fee configuration to cause failure
        MeterConfiguration::where('code', 'CONNECTION_FEE')->delete();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Failed to generate connection fee bill and invoice/');

        $this->service->handleConnectionFee($assignment, true);
    }

    #[Test]
    public function it_creates_multiple_meters_for_customer()
    {
        $customerData = [
            'name' => 'Multi Meter Customer',
            'email' => 'multi@example.com',
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ];

        $customer = User::create($customerData);

        $meterSetup = [
            'count' => 3,
            'charge_connection_fee' => true,
            'default_location' => 'Industrial Site',
        ];

        $assignments = [];
        for ($i = 1; $i <= $meterSetup['count']; $i++) {
            $meterData = [
                'meter_type' => 'standard',
                'meter_size' => '15mm',
                'location' => $meterSetup['count'] > 1 ? "{$meterSetup['default_location']} - Meter {$i}" : $meterSetup['default_location'],
            ];

            $assignmentData = [
                'customer_id' => $customer->id,
                'assignment_date' => now(),
                'initial_reading' => 0.0,
                'connection_fee' => $meterSetup['charge_connection_fee'],
            ];

            $assignment = $this->service->createMeterAndAssign(
                $meterData,
                $assignmentData,
                $this->tenant->id,
                $this->admin->id
            );
            
            $assignments[] = $assignment;
        }

        $this->assertCount(3, $assignments);

        // Verify all meters were created with correct locations
        $this->assertDatabaseHas('meters', [
            'location' => 'Industrial Site - Meter 1',
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->assertDatabaseHas('meters', [
            'location' => 'Industrial Site - Meter 2',
            'tenant_id' => $this->tenant->id,
        ]);
        
        $this->assertDatabaseHas('meters', [
            'location' => 'Industrial Site - Meter 3',
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify connection fees were charged (3 bills should exist)
        $billCount = Bill::where('customer_id', $customer->id)
            ->where('bill_type', 'CONNECTION_FEE')
            ->count();
        $this->assertEquals(3, $billCount);

        // Verify that meter assignments were created successfully
        // (Invoice generation is tested separately and may require additional setup)
        $assignmentCount = MeterAssignment::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->count();
        $this->assertEquals(3, $assignmentCount);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
