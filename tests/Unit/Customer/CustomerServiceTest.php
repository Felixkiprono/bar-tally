<?php

namespace Tests\Unit\Customer;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\Configuration;
use App\Services\CustomerService;
use App\Services\MeterAssignmentService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class CustomerServiceTest extends TestCase
{
    use WithFaker;

    protected CustomerService $service;
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

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->service = app(CustomerService::class);
    }

    #[Test]
    public function it_creates_customer_without_meters()
    {
        $customerData = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'telephone' => '0712345678',
            'location' => 'Nairobi',
            'id_number' => '12345678',
        ];

        $customer = $this->service->createCustomerWithMeters($customerData);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals('John Doe', $customer->name);
        $this->assertEquals('john@example.com', $customer->email);
    }

    #[Test]
    public function it_creates_customer_with_single_meter()
    {
        $customerData = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'telephone' => '0723456789',
            'location' => 'Mombasa',
        ];

        $meterSetup = [
            'count' => 1,
            'charge_connection_fee' => false,
            'default_location' => 'Main Building',
        ];

        $customer = $this->service->createCustomerWithMeters($customerData, $meterSetup);

        // Verify customer creation
        $this->assertDatabaseHas('users', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'role' => User::ROLE_CUSTOMER,
        ]);

        // Verify meter creation
        $this->assertDatabaseHas('meters', [
            'tenant_id' => $this->tenant->id,
            'location' => 'Main Building',
            'status' => 'active',
        ]);

        // Verify meter assignment
        $this->assertDatabaseHas('meter_assignments', [
            'customer_id' => $customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
    }

    #[Test]
    public function it_creates_customer_with_multiple_meters()
    {
        $customerData = [
            'name' => 'Bob Wilson',
            'email' => 'bob@example.com',
            'telephone' => '0734567890',
            'location' => 'Kisumu',
        ];

        $meterSetup = [
            'count' => 3,
            'charge_connection_fee' => false,
            'default_location' => 'Industrial Complex',
        ];

        $customer = $this->service->createCustomerWithMeters($customerData, $meterSetup);

        // Verify customer creation
        $this->assertDatabaseHas('users', ['email' => 'bob@example.com']);

        // Verify 3 meters were created
        $meterCount = Meter::where('tenant_id', $this->tenant->id)
            ->where('location', 'LIKE', 'Industrial Complex%')
            ->count();
        $this->assertEquals(3, $meterCount);

        // Verify 3 meter assignments were created
        $assignmentCount = MeterAssignment::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->count();
        $this->assertEquals(3, $assignmentCount);

        // Verify meter locations are numbered
        $this->assertDatabaseHas('meters', [
            'location' => 'Industrial Complex - Meter 1',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('meters', [
            'location' => 'Industrial Complex - Meter 2',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('meters', [
            'location' => 'Industrial Complex - Meter 3',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function it_creates_customer_with_custom_rate()
    {
        $customerData = [
            'name' => 'Alice Brown',
            'email' => 'alice@example.com',
            'telephone' => '0745678901',
            'Rate' => 150.0,
        ];

        $customer = $this->service->createCustomerWithMeters($customerData);

        // Verify customer creation
        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);

        // Verify custom rate configuration
        $key = "CUSTOMER_CONFIG_RATE_{$this->tenant->id}_{$customer->id}";
        $this->assertDatabaseHas('configurations', [
            'key' => $key,
            'value' => 150.0,
            'context' => 'customer_settings',
            'scope' => 'customer',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    #[Test]
    public function it_updates_existing_customer_rate()
    {
        // Create customer first
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create existing rate configuration
        $key = "CUSTOMER_CONFIG_RATE_{$this->tenant->id}_{$customer->id}";
        Configuration::create([
            'key' => $key,
            'context' => 'customer_settings',
            'scope' => 'customer',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
            'value' => 100.0,
        ]);

        // Update rate through service
        $this->service->setupCustomerRate($customer, 200.0);

        // Verify rate was updated
        $config = Configuration::where('key', $key)->first();
        $this->assertEquals(200.0, $config->value);
    }

    #[Test]
    public function it_updates_customer_data()
    {
        $customer = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        $updateData = [
            'name' => 'New Name',
            'email' => 'new@example.com',
            'location' => 'New Location',
            'Rate' => 175.0,
        ];

        $updatedCustomer = $this->service->updateCustomer($customer, $updateData);

        // Verify customer data was updated
        $this->assertEquals('New Name', $updatedCustomer->name);
        $this->assertEquals('new@example.com', $updatedCustomer->email);
        $this->assertEquals('New Location', $updatedCustomer->location);

        // Verify rate was set
        $key = "CUSTOMER_CONFIG_RATE_{$this->tenant->id}_{$customer->id}";
        $this->assertDatabaseHas('configurations', [
            'key' => $key,
            'value' => 175.0,
        ]);
    }

    #[Test]
    public function it_gets_customer_statistics()
    {
        // Create test customers
        $customer1 = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $customer2 = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);

        // Create meter assignment for customer1
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        MeterAssignment::factory()->create([
            'customer_id' => $customer1->id,
            'meter_id' => $meter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $stats = $this->service->getCustomerStats($this->tenant->id);

        $this->assertEquals(2, $stats['total_customers']);
        $this->assertEquals(1, $stats['active_customers']);
        $this->assertEquals(1, $stats['customers_with_meters']);
    }

    #[Test]
    public function it_handles_meter_creation_failure_gracefully()
    {
        $customerData = [
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'telephone' => '0712345678',
        ];

        $meterSetup = [
            'count' => 1,
            'charge_connection_fee' => false,
        ];

        // Test that the service properly handles exceptions during meter creation
        // by creating an invalid meter setup that will cause validation to fail
        $invalidMeterSetup = [
            'count' => 1,
            'charge_connection_fee' => true,
            'default_location' => 'Test Location',
        ];

        // Delete the connection fee configuration to cause failure
        \App\Models\MeterConfiguration::where('code', 'CONNECTION_FEE')->delete();

        $this->expectException(\Exception::class);

        $this->service->createCustomerWithMeters($customerData, $invalidMeterSetup);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
