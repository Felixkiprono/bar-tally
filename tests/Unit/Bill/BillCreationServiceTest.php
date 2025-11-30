<?php

namespace Tests\Unit\Bill;

use Tests\TestCase;
use App\Models\Bill;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Account;
use App\Models\MeterAssignment;
use App\Models\Meter;
use App\Services\Bills\BillCreationService;
use App\Services\Bills\BillReferenceService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;

class BillCreationServiceTest extends TestCase
{
    use WithFaker;

    protected BillCreationService $service;
    protected BillReferenceService $referenceService;
    protected User $customer;
    protected User $admin;
    protected Tenant $tenant;
    protected MeterAssignment $meterAssignment;

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

        // Create required accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);

        // Create meter and assignment
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->meterAssignment = MeterAssignment::factory()->create([
            'meter_id' => $meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Mock authentication
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize services with dependency injection
        $this->referenceService = new BillReferenceService();
        $invoiceService = app(\App\Services\Invoice\InvoiceService::class);
        $this->service = new BillCreationService($this->referenceService, $invoiceService);
    }

    #[Test]
    public function it_creates_a_bill_with_all_required_fields()
    {
        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals($this->customer->id, $bill->customer_id);
        $this->assertEquals($this->meterAssignment->id, $bill->meter_assignment_id);
        $this->assertEquals('WATER_USAGE', $bill->bill_type);
        $this->assertEquals(50, $bill->amount);
        $this->assertEquals(100, $bill->rate_used);
        $this->assertEquals(5000, $bill->total_amount);
        $this->assertEquals('pending', $bill->status);
        $this->assertEquals($this->tenant->id, $bill->tenant_id);
        $this->assertNotNull($bill->bill_ref);
    }

    #[Test]
    public function it_creates_bills_with_different_bill_types()
    {
        $billTypes = ['SERVICE_FEE', 'CONNECTION_FEE', 'WATER_USAGE'];

        foreach ($billTypes as $billType) {
            $billData = [
                'customer_id' => $this->customer->id,
                'meter_assignment_id' => $this->meterAssignment->id,
                'bill_type' => $billType,
                'amount' => 1,
                'rate_used' => 500,
                'total_amount' => 500,
                'status' => 'pending',
                'generation_date' => now(),
            ];

            $bill = $this->service->createSingleBill($billData, null, false);

            $this->assertEquals($billType, $bill->bill_type);
        }

        $this->assertEquals(count($billTypes), Bill::count());
    }

    #[Test]
    public function it_generates_bill_reference_automatically_if_not_provided()
    {
        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertNotNull($bill->bill_ref);
        $this->assertMatchesRegularExpression('/^\d{4}-[A-Z]{3}-\d{3}$/', $bill->bill_ref);
    }

    #[Test]
    public function it_uses_provided_reference_when_given()
    {
        $reference = strtoupper(now()->format('Y-M')) . '-001';

        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $bill = $this->service->createSingleBill($billData, $reference, false);

        $this->assertEquals($reference, $bill->bill_ref);
    }

    #[Test]
    public function it_validates_bill_reference_format()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid reference format');

        $invalidReference = 'INVALID-REF';

        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $this->service->createSingleBill($billData, $invalidReference, false);
    }

    #[Test]
    public function it_requires_customer_id()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'customer_id' is required");

        $billData = [
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_requires_bill_type()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'bill_type' is required");

        $billData = [
            'customer_id' => $this->customer->id,
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_requires_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'amount' is required");

        $billData = [
            'customer_id' => $this->customer->id,
            'bill_type' => 'WATER_USAGE',
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_requires_rate_used()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'rate_used' is required");

        $billData = [
            'customer_id' => $this->customer->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'total_amount' => 5000,
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_requires_total_amount()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'total_amount' is required");

        $billData = [
            'customer_id' => $this->customer->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_applies_default_status_when_not_provided()
    {
        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertEquals('pending', $bill->status);
        $this->assertNotNull($bill->generation_date);
    }

    #[Test]
    public function it_validates_amount_is_numeric()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'amount' must be numeric");

        $billData = [
            'customer_id' => $this->customer->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 'not-a-number',
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_validates_rate_used_is_numeric()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'rate_used' must be numeric");

        $billData = [
            'customer_id' => $this->customer->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 'not-a-number',
            'total_amount' => 5000,
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_validates_total_amount_is_numeric()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Field 'total_amount' must be numeric");

        $billData = [
            'customer_id' => $this->customer->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 'not-a-number',
            'status' => 'pending',
        ];

        $this->service->createSingleBill($billData, null, false);
    }

    #[Test]
    public function it_calculates_total_amount_correctly()
    {
        $amount = 50;
        $rateUsed = 100;
        $expectedTotal = $amount * $rateUsed;

        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => $amount,
            'rate_used' => $rateUsed,
            'total_amount' => $expectedTotal,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertEquals($expectedTotal, $bill->total_amount);
    }

    #[Test]
    public function it_detects_duplicate_bills_for_same_customer_and_reference()
    {
        $reference = strtoupper(now()->format('Y-M')) . '-001';

        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        // Create first bill
        $this->service->createSingleBill($billData, $reference);

        // Check for duplicate
        $isDuplicate = $this->service->checkForDuplicate(
            $this->customer->id,
            $reference,
            $this->tenant->id
        );

        $this->assertTrue($isDuplicate);
    }

    #[Test]
    public function it_does_not_detect_duplicate_for_different_customer()
    {
        $reference = strtoupper(now()->format('Y-M')) . '-001';

        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        // Create first bill
        $this->service->createSingleBill($billData, $reference);

        // Create different customer
        $differentCustomer = User::factory()->create([
            'role' => 'customer',
            'tenant_id' => $this->tenant->id,
        ]);

        // Check for duplicate with different customer
        $isDuplicate = $this->service->checkForDuplicate(
            $differentCustomer->id,
            $reference,
            $this->tenant->id
        );

        $this->assertFalse($isDuplicate);
    }

    #[Test]
    public function it_creates_bill_with_meter_assignment()
    {
        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertEquals($this->meterAssignment->id, $bill->meter_assignment_id);
        $this->assertNotNull($bill->meterAssignment);
        $this->assertEquals($this->meterAssignment->meter_id, $bill->meterAssignment->meter_id);
    }

    #[Test]
    public function it_defaults_status_to_pending()
    {
        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertEquals('pending', $bill->status);
    }

    #[Test]
    public function it_sets_tenant_id_correctly()
    {
        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertEquals($this->tenant->id, $bill->tenant_id);
    }

    #[Test]
    public function it_validates_bill_data_successfully()
    {
        $validData = [
            'customer_id' => $this->customer->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
        ];

        $result = $this->service->validateBillData($validData);

        $this->assertEquals($validData, $result);
    }

    #[Test]
    public function it_creates_bill_with_optional_notes()
    {
        $notes = 'Test bill notes';

        $billData = [
            'customer_id' => $this->customer->id,
            'meter_assignment_id' => $this->meterAssignment->id,
            'bill_type' => 'WATER_USAGE',
            'amount' => 50,
            'rate_used' => 100,
            'total_amount' => 5000,
            'status' => 'pending',
            'generation_date' => now(),
            'notes' => $notes,
        ];

        $bill = $this->service->createSingleBill($billData, null, false);

        $this->assertEquals($notes, $bill->notes);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}

