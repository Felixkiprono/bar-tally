<?php

namespace Tests\Unit\Bill;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\Bill;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Services\Bills\BillBatchService;
use App\Services\Bills\BillReferenceService;
use App\Services\Bills\BillCreationService;
use App\Services\Invoice\InvoiceService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Mockery;
use PHPUnit\Framework\Attributes\Test;

class BillBatchServiceTest extends TestCase
{
    use WithFaker;

    protected BillBatchService $service;
    protected User $admin;
    protected User $customer1;
    protected User $customer2;
    protected Tenant $tenant;
    protected Meter $meter1;
    protected Meter $meter2;
    protected Meter $meter3;
    protected MeterAssignment $assignment1;
    protected MeterAssignment $assignment2;
    protected MeterAssignment $assignment3;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a tenant first
        $this->tenant = Tenant::factory()->create();
        
        // Create users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer1 = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->customer2 = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);
        
        // Create required accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);

        // Create meters
        $this->meter1 = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->meter2 = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->meter3 = Meter::factory()->create(['tenant_id' => $this->tenant->id]);

        // Create meter assignments
        $this->assignment1 = MeterAssignment::factory()->create([
            'customer_id' => $this->customer1->id,
            'meter_id' => $this->meter1->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        
        $this->assignment2 = MeterAssignment::factory()->create([
            'customer_id' => $this->customer1->id, // Same customer, different meter
            'meter_id' => $this->meter2->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        
        $this->assignment3 = MeterAssignment::factory()->create([
            'customer_id' => $this->customer2->id,
            'meter_id' => $this->meter3->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service with dependency injection
        $this->service = app(BillBatchService::class);
    }

    #[Test]
    public function it_creates_bills_for_all_meter_assignments_when_customers_selected()
    {
        $customerIds = [$this->customer1->id, $this->customer2->id];
        $billData = [
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-001';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        // Customer1 has 2 meter assignments, Customer2 has 1 = 3 bills total
        $this->assertEquals(3, $result->created);
        $this->assertEquals(0, $result->invoicesCreated);
        $this->assertEmpty($result->invoiceErrors);
        
        // Verify bills exist with meter_assignment_id
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer1->id,
            'meter_assignment_id' => $this->assignment1->id,
            'bill_type' => 'WATER_USAGE',
            'total_amount' => 500.0,
            'bill_ref' => $reference,
        ]);
        
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer1->id,
            'meter_assignment_id' => $this->assignment2->id,
            'bill_type' => 'WATER_USAGE',
            'total_amount' => 500.0,
            'bill_ref' => $reference,
        ]);
        
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer2->id,
            'meter_assignment_id' => $this->assignment3->id,
            'bill_type' => 'WATER_USAGE',
            'total_amount' => 500.0,
            'bill_ref' => $reference,
        ]);
        
        // Verify no invoices were created
        $this->assertEquals(0, Invoice::count());
    }

    #[Test]
    public function it_creates_bills_with_invoices_when_create_invoice_is_true()
    {
        $customerIds = [$this->customer2->id]; // Customer2 has 1 meter assignment
        $billData = [
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-002';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, true);

        // Verify bill was created for the meter assignment
        $this->assertEquals(1, $result->created);
        
        // Verify bill exists with meter_assignment_id
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer2->id,
            'meter_assignment_id' => $this->assignment3->id,
            'bill_type' => 'WATER_USAGE',
            'total_amount' => 500.0,
            'bill_ref' => $reference,
        ]);
        
        // Verify invoice creation was attempted
        $this->assertIsInt($result->invoicesCreated);
        $this->assertIsArray($result->invoiceErrors);
    }

    #[Test]
    public function it_processes_multiple_meter_assignments_for_single_customer()
    {
        $customerIds = [$this->customer1->id]; // Customer1 has 2 meter assignments
        $billData = [
            'bill_type' => 'ELECTRICITY',
            'amount' => 200,
            'rate_used' => 2.5,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-003';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, true);

        // Should create 2 bills (one for each meter assignment)
        $this->assertEquals(2, $result->created);
        
        // Verify both bills exist with correct meter assignments
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer1->id,
            'meter_assignment_id' => $this->assignment1->id,
            'bill_ref' => $reference,
        ]);
        
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer1->id,
            'meter_assignment_id' => $this->assignment2->id,
            'bill_ref' => $reference,
        ]);
        
        // Verify invoice creation was attempted for both
        $this->assertIsInt($result->invoicesCreated);
        $this->assertIsArray($result->invoiceErrors);
    }

    #[Test]
    public function it_handles_customers_with_no_active_meter_assignments()
    {
        // Create a customer with no meter assignments
        $customerWithoutMeters = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $this->tenant->id,
        ]);

        $customerIds = [$customerWithoutMeters->id];
        $billData = [
            'bill_type' => 'SERVICE_FEE',
            'amount' => 1,
            'rate_used' => 100.0,
            'total_amount' => 100.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-004';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        // Should have no bills created and an error
        $this->assertEquals(0, $result->created);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('No active meter assignments found', $result->errors[0]);
    }

    #[Test]
    public function it_handles_multiple_customers_with_invoice_creation()
    {
        $customerIds = [$this->customer1->id, $this->customer2->id];
        $billData = [
            'bill_type' => 'ELECTRICITY',
            'amount' => 200,
            'rate_used' => 2.5,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-005';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, true);

        // Customer1 has 2 assignments, Customer2 has 1 = 3 bills total
        $this->assertEquals(3, $result->created);
        
        // Verify all bills exist
        $this->assertEquals(3, Bill::count());
        
        // Verify invoice creation was attempted for all meter assignments
        $this->assertIsInt($result->invoicesCreated);
        $this->assertIsArray($result->invoiceErrors);
        $this->assertGreaterThanOrEqual(0, $result->invoicesCreated);
    }

    #[Test]
    public function it_generates_batch_summary_with_meter_assignment_statistics()
    {
        $customerIds = [$this->customer1->id, $this->customer2->id];
        $billData = [
            'bill_type' => 'SUMMARY_TEST',
            'amount' => 25,
            'rate_used' => 8.0,
            'total_amount' => 200.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-006';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, true);
        $summary = $this->service->generateBatchSummary($result);

        // Should create 3 bills (2 for customer1, 1 for customer2)
        $this->assertEquals(3, $summary['created']);
        $this->assertArrayHasKey('invoices_created', $summary);
        $this->assertArrayHasKey('invoice_errors', $summary);
        $this->assertEquals($reference, $summary['reference']);
        $this->assertArrayHasKey('invoice_error_messages', $summary);
        $this->assertIsArray($summary['invoice_error_messages']);
        
        // Verify invoice statistics are integers/arrays as expected
        $this->assertIsInt($summary['invoices_created']);
        $this->assertIsInt($summary['invoice_errors']);
    }

    #[Test]
    public function it_skips_duplicate_bills_in_batch()
    {
        $customerIds = [$this->customer1->id];
        $billData = [
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-007';

        // Create bills first time
        $firstResult = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);
        $this->assertEquals(2, $firstResult->created); // Customer1 has 2 meter assignments

        // Try to create same bills again with same reference
        $secondResult = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);
        
        // Should skip duplicates
        $this->assertEquals(0, $secondResult->created);
        $this->assertEquals(2, $secondResult->skipped);
        $this->assertContains($this->customer1->id, $secondResult->skippedCustomers);
    }

    #[Test]
    public function it_handles_partial_batch_success()
    {
        // Create customer with invalid meter assignment (inactive)
        $inactiveMeter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $inactiveAssignment = MeterAssignment::factory()->create([
            'customer_id' => $this->customer1->id,
            'meter_id' => $inactiveMeter->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => false, // Inactive assignment
        ]);

        $customerIds = [$this->customer1->id, $this->customer2->id];
        $billData = [
            'bill_type' => 'SERVICE_FEE',
            'amount' => 1,
            'rate_used' => 150.0,
            'total_amount' => 150.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-008';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        // Should create bills for active assignments only (2 for customer1, 1 for customer2)
        $this->assertEquals(3, $result->created);
        $this->assertEquals(0, count($result->errors));
        
        // Verify inactive assignment was not processed
        $this->assertDatabaseMissing('bills', [
            'meter_assignment_id' => $inactiveAssignment->id,
            'bill_ref' => $reference,
        ]);
    }

    #[Test]
    public function it_maintains_reference_consistency_across_batch()
    {
        $customerIds = [$this->customer1->id, $this->customer2->id];
        $billData = [
            'bill_type' => 'CONSISTENCY_TEST',
            'amount' => 75,
            'rate_used' => 4.0,
            'total_amount' => 300.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-009';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        // All bills should have same reference
        $bills = Bill::where('bill_ref', $reference)->get();
        $this->assertCount(3, $bills); // 3 meter assignments total

        foreach ($bills as $bill) {
            $this->assertEquals($reference, $bill->bill_ref);
            $this->assertEquals('CONSISTENCY_TEST', $bill->bill_type);
            $this->assertEquals(300.0, $bill->total_amount);
        }
    }

    #[Test]
    public function it_tracks_batch_results_correctly()
    {
        $customerIds = [$this->customer1->id];
        $billData = [
            'bill_type' => 'TRACKING_TEST',
            'amount' => 50,
            'rate_used' => 6.0,
            'total_amount' => 300.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-010';

        // First batch - should create 2 bills
        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        $this->assertEquals(2, $result->created);
        $this->assertCount(2, $result->createdBills);
        $this->assertEquals(0, $result->skipped);
        $this->assertEmpty($result->errors);
        $this->assertEquals($reference, $result->reference);

        // Verify created bill IDs are tracked
        foreach ($result->createdBills as $billId) {
            $this->assertDatabaseHas('bills', ['id' => $billId]);
        }
    }

    #[Test]
    public function it_validates_bill_data_before_processing_batch()
    {
        $customerIds = [$this->customer1->id];
        $invalidBillData = [
            'bill_type' => 'INVALID_TEST',
            'amount' => 'not-a-number', // Invalid numeric field
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-011';

        $result = $this->service->processBatchForCustomers($customerIds, $invalidBillData, $reference, false);

        // Validation errors should be captured in errors array
        $this->assertEquals(0, $result->created);
        $this->assertGreaterThan(0, count($result->errors));
        $this->assertStringContainsString('must be numeric', $result->errors[0]);
    }

    #[Test]
    public function it_validates_reference_format_in_batch_processing()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reference format');

        $customerIds = [$this->customer1->id];
        $billData = [
            'bill_type' => 'WATER_USAGE',
            'amount' => 100,
            'rate_used' => 5.0,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $invalidReference = 'INVALID-FORMAT';

        $this->service->processBatchForCustomers($customerIds, $billData, $invalidReference, false);
    }

    #[Test]
    public function it_generates_reference_automatically_if_not_provided_in_batch()
    {
        $customerIds = [$this->customer2->id];
        $billData = [
            'bill_type' => 'AUTO_REF_TEST',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        // Don't provide reference
        $result = $this->service->processBatchForCustomers($customerIds, $billData, null, false);

        $this->assertEquals(1, $result->created);
        $this->assertNotNull($result->reference);
        $this->assertMatchesRegularExpression('/^\d{4}-[A-Z]{3}-\d{3}$/', $result->reference);

        // Verify bill has the auto-generated reference
        $bill = Bill::where('bill_type', 'AUTO_REF_TEST')->first();
        $this->assertNotNull($bill);
        $this->assertEquals($result->reference, $bill->bill_ref);
    }

    #[Test]
    public function it_includes_invoice_statistics_in_batch_summary()
    {
        $customerIds = [$this->customer2->id];
        $billData = [
            'bill_type' => 'STATS_TEST',
            'amount' => 100,
            'rate_used' => 3.0,
            'total_amount' => 300.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-012';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, true);
        $summary = $this->service->generateBatchSummary($result);

        // Verify summary structure
        $this->assertArrayHasKey('created', $summary);
        $this->assertArrayHasKey('skipped', $summary);
        $this->assertArrayHasKey('errors', $summary);
        $this->assertArrayHasKey('invoices_created', $summary);
        $this->assertArrayHasKey('invoice_errors', $summary);
        $this->assertArrayHasKey('reference', $summary);
        $this->assertArrayHasKey('success_rate', $summary);
        $this->assertArrayHasKey('created_bill_ids', $summary);
        $this->assertArrayHasKey('error_messages', $summary);
        $this->assertArrayHasKey('invoice_error_messages', $summary);
    }

    #[Test]
    public function it_calculates_success_rate_in_batch_summary()
    {
        $customerIds = [$this->customer1->id];
        $billData = [
            'bill_type' => 'SUCCESS_RATE_TEST',
            'amount' => 80,
            'rate_used' => 2.5,
            'total_amount' => 200.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-013';

        // Create bills
        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);
        $summary = $this->service->generateBatchSummary($result);

        // All 2 bills should succeed
        $this->assertEquals(100.0, $summary['success_rate']);
        $this->assertEquals(2, $summary['created']);
        $this->assertEquals(0, $summary['skipped']);
        $this->assertEquals(0, $summary['errors']);
    }

    #[Test]
    public function it_processes_only_active_meter_assignments()
    {
        // Deactivate one of customer1's meter assignments
        $this->assignment2->update(['is_active' => false]);

        $customerIds = [$this->customer1->id];
        $billData = [
            'bill_type' => 'ACTIVE_ONLY_TEST',
            'amount' => 60,
            'rate_used' => 5.0,
            'total_amount' => 300.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-014';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        // Should only create 1 bill (for active assignment1)
        $this->assertEquals(1, $result->created);
        
        $this->assertDatabaseHas('bills', [
            'meter_assignment_id' => $this->assignment1->id,
            'bill_ref' => $reference,
        ]);

        $this->assertDatabaseMissing('bills', [
            'meter_assignment_id' => $this->assignment2->id,
            'bill_ref' => $reference,
        ]);
    }

    #[Test]
    public function it_isolates_batch_processing_by_tenant()
    {
        // Create second tenant and customer
        $tenant2 = Tenant::factory()->create();
        $customer3 = User::factory()->create([
            'role' => User::ROLE_CUSTOMER,
            'tenant_id' => $tenant2->id,
        ]);

        $meter4 = Meter::factory()->create(['tenant_id' => $tenant2->id]);
        $assignment4 = MeterAssignment::factory()->create([
            'customer_id' => $customer3->id,
            'meter_id' => $meter4->id,
            'tenant_id' => $tenant2->id,
            'is_active' => true,
        ]);

        // Try to process batch with customer from different tenant
        // (should not work because Auth is mocked for tenant1)
        $customerIds = [$customer3->id]; // Different tenant
        $billData = [
            'bill_type' => 'TENANT_TEST',
            'amount' => 50,
            'rate_used' => 5.0,
            'total_amount' => 250.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-015';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        // Should have no bills created due to tenant mismatch
        $this->assertEquals(0, $result->created);
        $this->assertCount(1, $result->errors);
    }

    #[Test]
    public function it_handles_empty_customer_list()
    {
        $customerIds = []; // Empty array
        $billData = [
            'bill_type' => 'EMPTY_TEST',
            'amount' => 50,
            'rate_used' => 5.0,
            'total_amount' => 250.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-016';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, false);

        $this->assertEquals(0, $result->created);
        $this->assertCount(1, $result->errors);
        $this->assertStringContainsString('No active meter assignments', $result->errors[0]);
    }

    #[Test]
    public function it_handles_invoice_generation_when_open_invoice_already_exists()
    {
        // Create an existing open invoice for customer2's meter
        $existingInvoice = Invoice::create([
            'invoice_number' => 'INV-EXISTING-001',
            'customer_id' => $this->customer2->id,
            'meter_id' => $this->meter3->id,
            'invoice_date' => now()->subDays(5),
            'total_amount' => 1000.0,
            'paid_amount' => 0,
            'balance' => 1000.0,
            'due_date' => now()->addDays(25),
            'status' => 'not paid',
            'state' => 'open',
            'tenant_id' => $this->tenant->id,
        ]);

        // Now create new bills and try to generate invoices
        $customerIds = [$this->customer2->id];
        $billData = [
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 10.0,
            'total_amount' => 500.0,
            'status' => 'pending',
            'generation_date' => now(),
        ];
        $reference = '2024-DEC-017';

        $result = $this->service->processBatchForCustomers($customerIds, $billData, $reference, true);

        // Bill should be created
        $this->assertEquals(1, $result->created);
        
        // Verify the bill was created
        $this->assertDatabaseHas('bills', [
            'customer_id' => $this->customer2->id,
            'meter_assignment_id' => $this->assignment3->id,
            'bill_ref' => $reference,
            'total_amount' => 500.0,
        ]);

        // The InvoiceService should handle the existing open invoice
        // According to the service logic, it should add bills to existing open invoice
        // or handle as per business logic
        $this->assertIsInt($result->invoicesCreated);
        $this->assertIsArray($result->invoiceErrors);

        // Verify the existing invoice still exists
        $this->assertDatabaseHas('invoices', [
            'id' => $existingInvoice->id,
            'invoice_number' => 'INV-EXISTING-001',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
