# Bill Tests Documentation

This document details all tests related to billing functionality in the Hydra Billing system.

## Test Files

### 1. BillCreationServiceTest (`tests/Unit/Bill/BillCreationServiceTest.php`)
**Purpose:** Tests individual bill creation, validation, and reference generation

**Test Coverage: 21 tests**

#### Bill Creation Tests
- ✅ **it creates a bill with all required fields**
  - Tests bill creation with complete data
  - Validates all required fields are set
  - Ensures bill is persisted to database

- ✅ **it creates bills with different bill types**
  - Tests multiple bill types: WATER, ELECTRICITY, FIXED_CHARGE, CONNECTION_FEE
  - Validates bill_type enum handling
  - Ensures type-specific logic works

- ✅ **it creates bill with meter assignment**
  - Tests bill-meter assignment linkage
  - Validates meter_assignment_id is set
  - Ensures relationship integrity

- ✅ **it creates bill with optional notes**
  - Tests notes field is optional
  - Validates notes are stored when provided
  - Ensures NULL handling for empty notes

#### Reference Generation Tests
- ✅ **it generates bill reference automatically if not provided**
  - Tests auto-generation of bill references
  - Validates reference format
  - Ensures uniqueness

- ✅ **it uses provided reference when given**
  - Tests manual reference specification
  - Validates custom references are accepted
  - Ensures flexibility for external references

- ✅ **it validates bill reference format**
  - Tests reference format validation
  - Validates format: YYYY-MM-NNNN
  - Ensures invalid formats are rejected

#### Validation Tests
- ✅ **it requires customer id**
  - Tests customer_id is mandatory
  - Validates exception on missing customer
  - Ensures data integrity

- ✅ **it requires bill type**
  - Tests bill_type is mandatory
  - Validates exception on missing type
  - Ensures business rule enforcement

- ✅ **it requires amount**
  - Tests amount field is mandatory
  - Validates exception on missing amount
  - Ensures financial data completeness

- ✅ **it requires rate used**
  - Tests rate_used is mandatory
  - Validates rate tracking requirement
  - Ensures audit trail for pricing

- ✅ **it requires total amount**
  - Tests total_amount is mandatory
  - Validates calculated totals
  - Ensures financial accuracy

- ✅ **it validates amount is numeric**
  - Tests numeric validation for amount
  - Validates type enforcement
  - Ensures data type safety

- ✅ **it validates rate used is numeric**
  - Tests numeric validation for rate_used
  - Validates type enforcement
  - Ensures data type safety

- ✅ **it validates total amount is numeric**
  - Tests numeric validation for total_amount
  - Validates type enforcement
  - Ensures data type safety

#### Calculation Tests
- ✅ **it calculates total amount correctly**
  - Tests total_amount = (amount × rate_used) + other charges
  - Validates calculation accuracy
  - Ensures financial correctness

#### Default Values Tests
- ✅ **it applies default status when not provided**
  - Tests status defaults to "pending"
  - Validates automatic status assignment
  - Ensures consistent initialization

- ✅ **it defaults status to pending**
  - Tests explicit default status = "pending"
  - Validates initial bill state
  - Ensures workflow starts correctly

#### Duplicate Detection Tests
- ✅ **it detects duplicate bills for same customer and reference**
  - Tests duplicate prevention logic
  - Validates uniqueness constraint
  - Ensures no double-billing

- ✅ **it does not detect duplicate for different customer**
  - Tests references are unique per customer
  - Validates tenant isolation
  - Ensures references can repeat across customers

#### Multi-tenancy Tests
- ✅ **it sets tenant id correctly**
  - Tests tenant_id is set on all bills
  - Validates multi-tenancy compliance
  - Ensures data segregation

- ✅ **it validates bill data successfully**
  - Tests comprehensive validation logic
  - Validates all business rules
  - Ensures data quality

---

### 2. BillBatchServiceTest (`tests/Unit/Bill/BillBatchServiceTest.php`)
**Purpose:** Tests bulk bill creation and batch processing

**Test Coverage: 19 tests**

#### Batch Generation Tests
- ✅ **it creates bills for all meter assignments when customers selected**
  - Tests batch bill generation for multiple customers
  - Validates all assignments are processed
  - Ensures completeness of batch

- ✅ **it creates bills with invoices when create invoice is true**
  - Tests invoice auto-generation during batch
  - Validates bills are immediately invoiced
  - Ensures workflow efficiency

- ✅ **it processes multiple meter assignments for single customer**
  - Tests customer with multiple meters
  - Validates separate bills per meter
  - Ensures correct assignment tracking

- ✅ **it handles customers with no active meter assignments**
  - Tests graceful handling of inactive meters
  - Validates skipping of inactive assignments
  - Ensures robustness

- ✅ **it handles multiple customers with invoice creation**
  - Tests batch processing with auto-invoicing
  - Validates invoice generation per customer
  - Ensures scalability

#### Batch Statistics Tests
- ✅ **it generates batch summary with meter assignment statistics**
  - Tests batch result summary generation
  - Validates statistics: total, success, failed
  - Ensures reporting completeness

- ✅ **it includes invoice statistics in batch summary**
  - Tests invoice counts in summary
  - Validates invoices_created tracking
  - Ensures comprehensive reporting

- ✅ **it calculates success rate in batch summary**
  - Tests success_rate calculation
  - Validates percentage accuracy
  - Ensures business metrics

- ✅ **it tracks batch results correctly**
  - Tests result tracking per bill
  - Validates success/failure recording
  - Ensures audit trail

#### Duplicate Handling Tests
- ✅ **it skips duplicate bills in batch**
  - Tests duplicate detection during batch
  - Validates bills are not duplicated
  - Ensures idempotency

#### Error Handling Tests
- ✅ **it handles partial batch success**
  - Tests batch continues on individual failures
  - Validates partial success tracking
  - Ensures resilience

- ✅ **it handles empty customer list**
  - Tests graceful handling of empty input
  - Validates no errors on empty batch
  - Ensures robustness

#### Reference Management Tests
- ✅ **it maintains reference consistency across batch**
  - Tests all bills in batch share reference
  - Validates reference group tracking
  - Ensures batch identification

- ✅ **it validates reference format in batch processing**
  - Tests reference validation during batch
  - Validates format enforcement
  - Ensures data quality

- ✅ **it generates reference automatically if not provided in batch**
  - Tests auto-generation for batch
  - Validates unique batch references
  - Ensures convenience

#### Invoice Integration Tests
- ✅ **it handles invoice generation when open invoice already exists**
  - Tests behavior with existing open invoices
  - Validates bills added to existing invoice
  - Ensures invoice consolidation

#### Multi-tenancy Tests
- ✅ **it isolates batch processing by tenant**
  - Tests tenant isolation during batch
  - Validates no cross-tenant processing
  - Ensures data segregation

- ✅ **it processes only active meter assignments**
  - Tests filtering of inactive assignments
  - Validates status = "active" requirement
  - Ensures business rule enforcement

---

### 3. BillReferenceServiceTest (`tests/Unit/Bill/BillReferenceServiceTest.php`)
**Purpose:** Tests bill reference generation and validation

**Test Coverage: 16 tests**

#### Reference Generation Tests
- ✅ **it generates reference in correct format**
  - Tests format: YYYY-MM-NNNN (e.g., 2025-10-0001)
  - Validates format consistency
  - Ensures readability

- ✅ **it generates reference with current year and month**
  - Tests year-month from current date
  - Validates temporal accuracy
  - Ensures proper dating

- ✅ **it starts sequence at 001 for new month**
  - Tests sequence resets monthly
  - Validates initial sequence = 001
  - Ensures logical numbering

- ✅ **it increments reference correctly within same month**
  - Tests sequential numbering: 0001, 0002, 0003...
  - Validates increment logic
  - Ensures uniqueness

- ✅ **it increments to 003 after 002**
  - Tests specific increment case
  - Validates sequential integrity
  - Ensures no gaps

- ✅ **it pads sequence numbers with leading zeros**
  - Tests zero-padding to 4 digits
  - Validates: 1 → 0001, 12 → 0012, 123 → 0123
  - Ensures format consistency

#### Reference Validation Tests
- ✅ **it validates correct reference format**
  - Tests valid formats pass validation
  - Validates: 2025-10-0001, 2024-01-9999
  - Ensures acceptance of correct formats

- ✅ **it rejects invalid reference formats**
  - Tests invalid formats fail validation
  - Validates rejection of: "ABC-123", "2025-1-1", "invalid"
  - Ensures data quality

- ✅ **it handles invalid reference format in next number calculation**
  - Tests graceful handling of malformed references
  - Validates error handling
  - Ensures robustness

#### Reference Numbering Tests
- ✅ **it gets next reference number correctly**
  - Tests retrieval of next sequence number
  - Validates max(existing) + 1 logic
  - Ensures correct ordering

- ✅ **it returns 1 for first reference number**
  - Tests initial reference number
  - Validates starting point = 1
  - Ensures bootstrap works

#### Multi-tenancy Tests
- ✅ **it isolates references by tenant**
  - Tests tenant-specific reference sequences
  - Validates no cross-tenant numbering
  - Ensures data segregation

- ✅ **it gets existing references for tenant**
  - Tests retrieval of tenant's references
  - Validates tenant filtering
  - Ensures isolation

- ✅ **it filters existing references by year month**
  - Tests temporal filtering of references
  - Validates year-month grouping
  - Ensures monthly sequences

#### Uniqueness Tests
- ✅ **it checks reference uniqueness for customer and tenant**
  - Tests uniqueness validation
  - Validates per-customer, per-tenant uniqueness
  - Ensures no duplicates

- ✅ **it returns true for unique reference**
  - Tests unique reference detection
  - Validates positive case
  - Ensures correct logic

---

### 4. BillServiceTest (`tests/Unit/Bill/BillServiceTest.php`)
**Purpose:** Tests meter reading bill generation

**Test Coverage: 5 tests**

#### Meter Reading Bill Tests
- ✅ **it generates bills from meter reading with same reference**
  - Tests bill generation from meter reading
  - Validates all readings → bills
  - Ensures reference sharing

- ✅ **it generates unique reference for each meter reading**
  - Tests unique references per reading batch
  - Validates reference generation
  - Ensures traceability

- ✅ **it uses provided reference when given**
  - Tests manual reference specification
  - Validates custom reference usage
  - Ensures flexibility

- ✅ **it generates reference when not provided**
  - Tests auto-generation fallback
  - Validates automatic references
  - Ensures convenience

- ✅ **it shares reference across batch when provided**
  - Tests batch reference sharing
  - Validates all bills use same reference
  - Ensures batch grouping

---

## Bill Data Structure

### Bill Model Fields
```php
// Core Fields
'customer_id'          => foreign key (User) - Required
'meter_assignment_id'  => foreign key (MeterAssignment) - Nullable
'meter_reading_id'     => foreign key (MeterReading) - Nullable

// Bill Details
'bill_type'    => enum ['WATER', 'ELECTRICITY', 'FIXED_CHARGE', 'CONNECTION_FEE', 'PENALTY', 'OTHER']
'reference'    => string - Bill reference (YYYY-MM-NNNN)
'amount'       => decimal - Units consumed or fixed amount
'rate_used'    => decimal - Rate at time of billing
'total_amount' => decimal - Calculated: amount × rate_used
'status'       => enum ['pending', 'invoiced', 'cancelled', 'paid']

// Optional Fields
'notes'        => text - Additional information

// Dates
'bill_date'    => date - When bill was created
'period_start' => date - Billing period start
'period_end'   => date - Billing period end

// Multi-tenancy
'tenant_id'    => foreign key (Tenant)
'created_by'   => foreign key (User)
```

### Bill Calculation Logic
```php
// Basic calculation
total_amount = amount × rate_used

// Example: Water bill
amount = units_consumed = 150 units
rate_used = 50 per unit
total_amount = 150 × 50 = 7,500

// Example: Fixed charge
amount = 1
rate_used = 500 (monthly charge)
total_amount = 1 × 500 = 500

// Example: Connection fee
amount = 1
rate_used = 1500 (one-time fee)
total_amount = 1 × 1500 = 1,500
```

---

## Key Business Rules

### Bill Creation
1. **Customer Requirement:** Every bill must be linked to a customer
2. **Reference Format:** Bills use format YYYY-MM-NNNN (monthly sequence)
3. **Status Lifecycle:** pending → invoiced → paid (or cancelled)
4. **Rate Tracking:** Rate at time of billing is frozen in `rate_used`

### Bill Types
1. **WATER/ELECTRICITY:** Consumption-based bills from meter readings
   - Amount = units consumed
   - Rate = per-unit rate at billing time
   - Linked to meter_reading_id

2. **FIXED_CHARGE:** Regular fixed charges (rent, service fees)
   - Amount = 1 (quantity)
   - Rate = fixed charge amount
   - No meter reading needed

3. **CONNECTION_FEE:** One-time connection charges
   - Amount = 1 (quantity)
   - Rate = connection fee amount
   - Linked to meter_assignment_id

4. **PENALTY:** Late payment or other penalties
   - Amount = 1 (quantity)
   - Rate = penalty amount
   - Manual creation

5. **OTHER:** Miscellaneous charges
   - Flexible amount and rate
   - Manual creation

### Bill Reference Rules
1. **Format:** YYYY-MM-NNNN (e.g., 2025-10-0001)
2. **Monthly Reset:** Sequence resets each month
3. **Tenant Isolation:** References are tenant-specific
4. **Batch Grouping:** Bills in same batch share reference
5. **Zero Padding:** Sequence always 4 digits (0001-9999)

### Bill Status Transitions
```
pending → invoiced    (when added to invoice)
pending → cancelled   (when cancelled before invoicing)
invoiced → paid       (when invoice fully paid)
```

### Bill Cancellation Rules
1. **Only Pending Bills:** Only bills with status = "pending" can be cancelled
2. **Meter Reading Bills:** Bills from meter readings CANNOT be cancelled
   - Must correct the meter reading and regenerate
3. **Invoiced Bills:** Bills already invoiced cannot be cancelled
   - Must cancel or correct the invoice instead
4. **Detachment:** Cancelled bills are detached from any open invoices

---

## Batch Processing

### Batch Bill Generation Flow
```php
// 1. Select customers and period
$customers = ['customer_1', 'customer_2', 'customer_3'];
$periodStart = '2025-10-01';
$periodEnd = '2025-10-31';

// 2. Generate reference for batch
$reference = '2025-10-0015';

// 3. For each customer's active meter assignments
foreach ($customers as $customer) {
    $assignments = MeterAssignment::active()
        ->where('customer_id', $customer->id)
        ->get();
    
    foreach ($assignments as $assignment) {
        // 4. Get meter reading for period
        $reading = MeterReading::forPeriod($assignment->meter_id, $periodStart, $periodEnd)->first();
        
        // 5. Create bill
        Bill::create([
            'customer_id' => $customer->id,
            'meter_assignment_id' => $assignment->id,
            'meter_reading_id' => $reading?->id,
            'bill_type' => 'WATER',
            'reference' => $reference,
            'amount' => $reading?->consumption ?? 0,
            'rate_used' => $assignment->rate,
            'total_amount' => $reading?->consumption * $assignment->rate,
            'status' => 'pending',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
    }
}

// 6. Optionally generate invoices
if ($createInvoices) {
    foreach ($customers as $customer) {
        InvoiceService::generateInvoiceFromBills($customer->id);
    }
}
```

### Batch Summary Structure
```php
[
    'total' => 150,              // Total bills attempted
    'success' => 145,            // Successfully created
    'failed' => 5,               // Failed to create
    'success_rate' => 96.67,     // Percentage
    'invoices_created' => 48,    // Invoices generated (if applicable)
    'reference' => '2025-10-0015', // Batch reference
]
```

---

## Test Data Setup

### Standard Test Setup
```php
protected function setUp(): void
{
    parent::setUp();
    
    // Create tenant & users
    $this->tenant = Tenant::factory()->create();
    $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
    $this->customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    
    // Create meter & assignment
    $this->meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->assignment = MeterAssignment::factory()->create([
        'meter_id' => $this->meter->id,
        'customer_id' => $this->customer->id,
        'rate' => 50.00,
        'tenant_id' => $this->tenant->id,
    ]);
    
    // Mock Auth
    Auth::shouldReceive('user')->andReturn($this->admin);
    Auth::shouldReceive('id')->andReturn($this->admin->id);
}
```

### Creating Test Bills
```php
// Water bill from meter reading
$bill = Bill::factory()->create([
    'customer_id' => $customer->id,
    'meter_assignment_id' => $assignment->id,
    'meter_reading_id' => $reading->id,
    'bill_type' => 'WATER',
    'reference' => '2025-10-0001',
    'amount' => 150.00,          // units
    'rate_used' => 50.00,        // per unit
    'total_amount' => 7500.00,   // 150 × 50
    'status' => 'pending',
    'tenant_id' => $tenant->id,
]);

// Fixed charge bill
$bill = Bill::factory()->create([
    'customer_id' => $customer->id,
    'bill_type' => 'FIXED_CHARGE',
    'reference' => '2025-10-0002',
    'amount' => 1,
    'rate_used' => 500.00,
    'total_amount' => 500.00,
    'status' => 'pending',
    'tenant_id' => $tenant->id,
]);

// Connection fee bill
$bill = Bill::factory()->create([
    'customer_id' => $customer->id,
    'meter_assignment_id' => $assignment->id,
    'bill_type' => 'CONNECTION_FEE',
    'reference' => '2025-10-0003',
    'amount' => 1,
    'rate_used' => 1500.00,
    'total_amount' => 1500.00,
    'status' => 'pending',
    'tenant_id' => $tenant->id,
]);
```

---

## Running Bill Tests

```bash
# Run all bill tests
php artisan test --filter=BillCreationServiceTest
php artisan test --filter=BillBatchServiceTest
php artisan test --filter=BillReferenceServiceTest
php artisan test --filter=BillServiceTest

# Run specific test
php artisan test --filter=it_creates_bills_with_different_bill_types

# Run all bill-related tests
php artisan test --filter=Bill

# Run with coverage
php artisan test --filter=Bill --coverage
```

---

## Test Maintenance

### When Adding New Bill Features
1. Add test to appropriate test class
2. Use `#[Test]` attribute (not `@test` docblock)
3. Follow Arrange → Act → Assert pattern
4. Always set tenant_id on all models
5. Use factories for model generation
6. Test both success and failure cases
7. Validate business rules

### Common Pitfalls
- ❌ Not setting tenant_id (breaks multi-tenancy)
- ❌ Using invalid reference format
- ❌ Forgetting to test duplicate detection
- ❌ Not testing bill status transitions
- ❌ Skipping validation tests
- ❌ Not testing batch scenarios
- ❌ Forgetting meter assignment linkage

---

## Coverage Summary

| Area | Test Count | Coverage |
|------|-----------|----------|
| Bill Creation | 10 | ✅ Complete |
| Bill Validation | 11 | ✅ Complete |
| Reference Generation | 16 | ✅ Complete |
| Batch Processing | 19 | ✅ Complete |
| Meter Reading Bills | 5 | ✅ Complete |
| Status Management | 5 | ✅ Complete |
| **Total** | **61** | **✅ Comprehensive** |

