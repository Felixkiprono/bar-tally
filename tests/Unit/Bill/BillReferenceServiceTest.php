<?php

namespace Tests\Unit\Bill;

use Tests\TestCase;
use App\Models\Bill;
use App\Models\User;
use App\Models\Tenant;
use App\Models\MeterAssignment;
use App\Models\Meter;
use App\Services\Bills\BillReferenceService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Carbon\Carbon;

class BillReferenceServiceTest extends TestCase
{
    use WithFaker;

    protected BillReferenceService $service;
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::factory()->create();

        // Create admin and customer users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'role' => 'customer',
            'tenant_id' => $this->tenant->id,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->service = new BillReferenceService();
    }

    #[Test]
    public function it_generates_reference_in_correct_format()
    {
        $reference = $this->service->generateReference($this->tenant->id);

        // Should match YYYY-MMM-### format (e.g., 2024-JAN-001)
        $this->assertMatchesRegularExpression('/^\d{4}-[A-Z]{3}-\d{3}$/', $reference);
    }

    #[Test]
    public function it_generates_reference_with_current_year_and_month()
    {
        $reference = $this->service->generateReference($this->tenant->id);

        $expectedYearMonth = strtoupper(now()->format('Y-M'));
        $this->assertStringStartsWith($expectedYearMonth, $reference);
    }

    #[Test]
    public function it_starts_sequence_at_001_for_new_month()
    {
        $reference = $this->service->generateReference($this->tenant->id);

        $parts = explode('-', $reference);
        $sequenceNumber = end($parts);

        $this->assertEquals('001', $sequenceNumber);
    }

    #[Test]
    public function it_increments_reference_correctly_within_same_month()
    {
        $yearMonth = strtoupper(now()->format('Y-M'));

        // Create first bill manually with reference
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        Bill::create([
            'bill_ref' => $yearMonth . '-001',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Generate next reference
        $nextReference = $this->service->generateReference($this->tenant->id);

        $this->assertEquals($yearMonth . '-002', $nextReference);
    }

    #[Test]
    public function it_increments_to_003_after_002()
    {
        $yearMonth = strtoupper(now()->format('Y-M'));

        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create bills with references 001 and 002
        Bill::create([
            'bill_ref' => $yearMonth . '-001',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        Bill::create([
            'bill_ref' => $yearMonth . '-002',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Generate next reference
        $nextReference = $this->service->generateReference($this->tenant->id);

        $this->assertEquals($yearMonth . '-003', $nextReference);
    }

    #[Test]
    public function it_validates_correct_reference_format()
    {
        $validReferences = [
            '2024-JAN-001',
            '2024-DEC-999',
            '2023-MAR-123',
            '2025-OCT-050',
        ];

        foreach ($validReferences as $reference) {
            $isValid = $this->service->validateReferenceFormat($reference);
            $this->assertTrue($isValid, "Reference '{$reference}' should be valid");
        }
    }

    #[Test]
    public function it_rejects_invalid_reference_formats()
    {
        $invalidReferences = [
            'INVALID-REF',
            '2024-JAN-1', // Missing leading zeros
            '24-JAN-001', // Wrong year format
            '2024-JANUARY-001', // Full month name
            '2024-jan-001', // Lowercase month
            '2024-JAN-1234', // Too many digits
            'JAN-2024-001', // Wrong order
        ];

        foreach ($invalidReferences as $reference) {
            $isValid = $this->service->validateReferenceFormat($reference);
            $this->assertFalse($isValid, "Reference '{$reference}' should be invalid");
        }
    }

    #[Test]
    public function it_gets_next_reference_number_correctly()
    {
        $yearMonth = strtoupper(now()->format('Y-M'));

        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create bill with reference 001
        Bill::create([
            'bill_ref' => $yearMonth . '-001',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $nextNumber = $this->service->getNextReferenceNumber($yearMonth, $this->tenant->id);

        $this->assertEquals(2, $nextNumber);
    }

    #[Test]
    public function it_returns_1_for_first_reference_number()
    {
        $yearMonth = strtoupper(now()->format('Y-M'));

        $nextNumber = $this->service->getNextReferenceNumber($yearMonth, $this->tenant->id);

        $this->assertEquals(1, $nextNumber);
    }

    #[Test]
    public function it_isolates_references_by_tenant()
    {
        $yearMonth = strtoupper(now()->format('Y-M'));

        // Create second tenant
        $tenant2 = Tenant::factory()->create();
        $customer2 = User::factory()->create([
            'role' => 'customer',
            'tenant_id' => $tenant2->id,
        ]);

        $meter1 = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment1 = MeterAssignment::factory()->create([
            'meter_id' => $meter1->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $meter2 = Meter::factory()->create(['tenant_id' => $tenant2->id]);
        $meterAssignment2 = MeterAssignment::factory()->create([
            'meter_id' => $meter2->id,
            'customer_id' => $customer2->id,
            'tenant_id' => $tenant2->id,
        ]);

        // Create bill for tenant 1
        Bill::create([
            'bill_ref' => $yearMonth . '-005',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment1->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Generate reference for tenant 2 - should start at 001, not 006
        $tenant2Reference = $this->service->generateReference($tenant2->id);

        $this->assertEquals($yearMonth . '-001', $tenant2Reference);

        // Generate reference for tenant 1 - should be 006
        $tenant1Reference = $this->service->generateReference($this->tenant->id);

        $this->assertEquals($yearMonth . '-006', $tenant1Reference);
    }

    #[Test]
    public function it_checks_reference_uniqueness_for_customer_and_tenant()
    {
        $reference = strtoupper(now()->format('Y-M')) . '-001';

        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create bill
        Bill::create([
            'bill_ref' => $reference,
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Check uniqueness - should be false (not unique)
        $isUnique = $this->service->isReferenceUnique(
            $reference,
            $this->customer->id,
            $this->tenant->id
        );

        $this->assertFalse($isUnique);
    }

    #[Test]
    public function it_returns_true_for_unique_reference()
    {
        $reference = strtoupper(now()->format('Y-M')) . '-999';

        $isUnique = $this->service->isReferenceUnique(
            $reference,
            $this->customer->id,
            $this->tenant->id
        );

        $this->assertTrue($isUnique);
    }

    #[Test]
    public function it_gets_existing_references_for_tenant()
    {
        $yearMonth = strtoupper(now()->format('Y-M'));

        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create multiple bills
        Bill::create([
            'bill_ref' => $yearMonth . '-001',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        Bill::create([
            'bill_ref' => $yearMonth . '-002',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        $existingReferences = $this->service->getExistingReferences($this->tenant->id);

        $this->assertCount(2, $existingReferences);
        $this->assertContains($yearMonth . '-001', $existingReferences);
        $this->assertContains($yearMonth . '-002', $existingReferences);
    }

    #[Test]
    public function it_filters_existing_references_by_year_month()
    {
        $currentYearMonth = strtoupper(now()->format('Y-M'));
        $previousMonth = strtoupper(now()->subMonth()->format('Y-M'));

        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create bill for current month
        Bill::create([
            'bill_ref' => $currentYearMonth . '-001',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Create bill for previous month
        Bill::create([
            'bill_ref' => $previousMonth . '-001',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now()->subMonth(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Get only current month references
        $currentMonthReferences = $this->service->getExistingReferences($this->tenant->id, $currentYearMonth);

        $this->assertCount(1, $currentMonthReferences);
        $this->assertContains($currentYearMonth . '-001', $currentMonthReferences);
        $this->assertNotContains($previousMonth . '-001', $currentMonthReferences);
    }

    #[Test]
    public function it_handles_invalid_reference_format_in_next_number_calculation()
    {
        $yearMonth = strtoupper(now()->format('Y-M'));

        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Create bill with invalid format
        Bill::create([
            'bill_ref' => 'INVALID-FORMAT',
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'tenant_id' => $this->tenant->id,
        ]);

        // Should return 1 (start fresh) when invalid format is encountered
        $nextNumber = $this->service->getNextReferenceNumber($yearMonth, $this->tenant->id);

        $this->assertEquals(1, $nextNumber);
    }

    #[Test]
    public function it_pads_sequence_numbers_with_leading_zeros()
    {
        $reference = $this->service->generateReference($this->tenant->id);

        $parts = explode('-', $reference);
        $sequenceNumber = end($parts);

        // Should be 3 digits with leading zeros
        $this->assertEquals(3, strlen($sequenceNumber));
        $this->assertMatchesRegularExpression('/^\d{3}$/', $sequenceNumber);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

