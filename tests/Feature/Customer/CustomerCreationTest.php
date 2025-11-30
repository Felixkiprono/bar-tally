<?php

namespace Tests\Feature\Customer;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterConfiguration;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Configuration;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;

class CustomerCreationTest extends TestCase
{
    use WithFaker;

    protected User $admin;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a tenant first
        $this->tenant = Tenant::factory()->create();
        
        // Create admin user with tenant
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        
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
        MeterConfiguration::factory()->connectionFee()->create(['tenant_id' => $this->tenant->id]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
    }

    #[Test]
    public function it_creates_customer_through_filament_resource()
    {
        $customerData = [
            'name' => 'Integration Test Customer',
            'email' => 'integration@example.com',
            'telephone' => '0712345678',
            'location' => 'Test Location',
            'id_number' => '12345678',
            'status' => 'active',
            'number_of_meters' => 2,
            'charge_connection_fee' => true,
            'default_meter_location' => 'Main Office',
            'Rate' => 120.0,
        ];

        // Simulate the form submission process
        $customer = User::create([
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'telephone' => $customerData['telephone'],
            'location' => $customerData['location'],
            'id_number' => $customerData['id_number'],
            'status' => $customerData['status'],
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('password'),
        ]);

        // Simulate the afterCreate hook processing
        $customerService = app(\App\Services\CustomerService::class);
        
        // Setup meters
        $meterSetup = [
            'count' => $customerData['number_of_meters'],
            'charge_connection_fee' => $customerData['charge_connection_fee'],
            'default_location' => $customerData['default_meter_location'],
        ];
        
        $assignments = $customerService->setupCustomerMeters($customer, $meterSetup);
        
        // Setup custom rate
        $customerService->setupCustomerRate($customer, $customerData['Rate']);

        // Verify customer was created
        $this->assertDatabaseHas('users', [
            'name' => 'Integration Test Customer',
            'email' => 'integration@example.com',
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify meters were created
        $this->assertEquals(2, count($assignments));
        $this->assertDatabaseHas('meters', [
            'location' => 'Main Office - Meter 1',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('meters', [
            'location' => 'Main Office - Meter 2',
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify meter assignments
        $this->assertEquals(2, MeterAssignment::where('customer_id', $customer->id)->count());

        // Verify connection fee bills were created
        $this->assertEquals(2, Bill::where('customer_id', $customer->id)
            ->where('bill_type', 'CONNECTION_FEE')
            ->count());

        // Verify custom rate was set
        $key = "CUSTOMER_CONFIG_RATE_{$this->tenant->id}_{$customer->id}";
        $this->assertDatabaseHas('configurations', [
            'key' => $key,
            'value' => 120.0,
            'context' => 'customer_settings',
        ]);
    }

    #[Test]
    public function it_creates_customer_without_meters()
    {
        $customerData = [
            'name' => 'No Meter Customer',
            'email' => 'nometerr@example.com',
            'telephone' => '0723456789',
            'location' => 'Remote Location',
            'status' => 'active',
            'number_of_meters' => 0, // No meters requested
        ];

        $customer = User::create([
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'telephone' => $customerData['telephone'],
            'location' => $customerData['location'],
            'status' => $customerData['status'],
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('password'),
        ]);

        // Verify customer was created
        $this->assertDatabaseHas('users', [
            'name' => 'No Meter Customer',
            'email' => 'nometerr@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);

        // Verify no meters were created
        $this->assertEquals(0, MeterAssignment::where('customer_id', $customer->id)->count());
        $this->assertEquals(0, Bill::where('customer_id', $customer->id)->count());
        $this->assertEquals(0, Invoice::where('customer_id', $customer->id)->count());
    }

    #[Test]
    public function it_creates_customer_with_single_meter_no_connection_fee()
    {
        $customerData = [
            'name' => 'Single Meter Customer',
            'email' => 'single@example.com',
            'telephone' => '0734567890',
            'location' => 'Single Location',
            'status' => 'active',
            'number_of_meters' => 1,
            'charge_connection_fee' => false,
            'default_meter_location' => 'Warehouse',
        ];

        $customer = User::create([
            'name' => $customerData['name'],
            'email' => $customerData['email'],
            'telephone' => $customerData['telephone'],
            'location' => $customerData['location'],
            'status' => $customerData['status'],
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
            'password' => bcrypt('password'),
        ]);

        $customerService = app(\App\Services\CustomerService::class);
        
        $meterSetup = [
            'count' => $customerData['number_of_meters'],
            'charge_connection_fee' => $customerData['charge_connection_fee'],
            'default_location' => $customerData['default_meter_location'],
        ];
        
        $assignments = $customerService->setupCustomerMeters($customer, $meterSetup);

        // Verify customer and meter creation
        $this->assertDatabaseHas('users', ['email' => 'single@example.com']);
        $this->assertEquals(1, count($assignments));
        
        // Verify meter location (single meter should not have number suffix)
        $this->assertDatabaseHas('meters', [
            'location' => 'Warehouse',
            'tenant_id' => $this->tenant->id,
        ]);

        // Verify no connection fee bills were created
        $this->assertEquals(0, Bill::where('customer_id', $customer->id)->count());
        $this->assertEquals(0, Invoice::where('customer_id', $customer->id)->count());
    }

    #[Test]
    public function it_handles_meter_creation_with_auto_generated_numbers()
    {
        $customer1 = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);

        $customer2 = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);

        $customerService = app(\App\Services\CustomerService::class);
        
        // Create meters for first customer
        $meterSetup1 = [
            'count' => 2,
            'charge_connection_fee' => false,
            'default_location' => 'Building A',
        ];
        $assignments1 = $customerService->setupCustomerMeters($customer1, $meterSetup1);

        // Create meters for second customer
        $meterSetup2 = [
            'count' => 1,
            'charge_connection_fee' => false,
            'default_location' => 'Building B',
        ];
        $assignments2 = $customerService->setupCustomerMeters($customer2, $meterSetup2);

        // Verify sequential meter numbers were generated
        $meters = Meter::where('tenant_id', $this->tenant->id)
            ->orderBy('meter_number')
            ->pluck('meter_number')
            ->toArray();

        $this->assertEquals(['MTR-001', 'MTR-002', 'MTR-003'], $meters);
    }

    #[Test]
    public function it_validates_meter_assignment_data()
    {
        $meterAssignmentService = app(\App\Services\MeterAssignmentService::class);

        // Test with invalid customer
        $invalidData = [
            'customer_id' => 99999,
            'meter_id' => 99999,
        ];

        $reflection = new \ReflectionClass($meterAssignmentService);
        $method = $reflection->getMethod('validateAssignmentData');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessageMatches('/Validation failed/');

        $method->invoke($meterAssignmentService, $invalidData);
    }
}
