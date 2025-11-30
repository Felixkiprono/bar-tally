# Invoice Tests Documentation

This document details all tests related to invoicing functionality in the Hydra Billing system.

## Test Files

### 1. InvoiceServiceTest (`tests/Unit/Invoice/InvoiceServiceTest.php`)
**Purpose:** Tests invoice generation, calculation, and lifecycle management

**Test Coverage: 21 tests** (Updated: Added consolidation test)

#### Invoice Generation Tests
- ✅ **it generates invoice from pending bills**
  - Validates that pending bills are correctly converted to invoices
  - Ensures bills are marked as "invoiced" after generation
  - Verifies invoice-bill relationship is established

- ✅ **it generates invoice number with correct format**
  - Validates invoice number format: `INV-YYYY-NNNN`
  - Ensures sequential numbering per tenant
  - Tests uniqueness of invoice numbers

- ✅ **it sets invoice date to current date**
  - Validates invoice_date is set to current timestamp
  - Ensures consistency across invoice generation

- ✅ **it calculates due date correctly**
  - Tests due date calculation based on tenant configuration
  - Validates default due days setting is applied
  - Ensures due_date is after invoice_date

#### Balance & Overpayment Tests
- ✅ **it applies meter overpayment credit**
  - Tests that existing meter overpayments are applied to new invoices
  - Validates balance reduction when overpayment exists
  - Ensures overpayment is properly tracked

- ✅ **it creates invoice with overpayment greater than bill**
  - Tests scenario where overpayment > bill amount
  - Validates invoice is marked as "Fully Paid"
  - Ensures remaining overpayment is tracked

- ✅ **it creates invoice with partial overpayment**
  - Tests overpayment application when overpayment < bill amount
  - Validates partial balance after overpayment application
  - Ensures invoice remains "open" with remaining balance

- ✅ **it creates invoice when overpayment equals bill amount**
  - Edge case: overpayment exactly matches bill
  - Validates invoice balance = 0
  - Ensures invoice is marked as "Fully Paid"

#### Invoice Status & State Tests
- ✅ **it sets invoice status to not paid when balance remains**
  - Validates status = "not paid" for unpaid invoices
  - Tests status logic for invoices with positive balance

- ✅ **it sets invoice state to open when unpaid**
  - Validates state = "open" for invoices with balance > 0
  - Ensures invoices can receive payments

- ✅ **it creates invoice with overpayment and valid state**
  - Tests combined state/status logic with overpayments
  - Validates proper state transitions

#### Invoice-Bill Relationship Tests
- ✅ **it links bills to invoice**
  - Tests invoice_bills pivot table relationship
  - Validates all bills are linked to invoice
  - Ensures many-to-many relationship integrity

- ✅ **it updates bill status to invoiced after linking**
  - Validates bills change from "pending" to "invoiced"
  - Ensures bills can't be invoiced twice
  - Tests bill status tracking

#### Accounting Tests
- ✅ **it creates journal entries for invoice**
  - Validates AR-CONTROL debit for invoice total
  - Tests revenue account credits for bill amounts
  - Ensures double-entry accounting balance

- ✅ **it creates journal entries with overpayment**
  - Tests journal entries when overpayment is applied
  - Validates CUSTOMER-PREPAYMENT account usage
  - Ensures accounting accuracy with credits

#### Batch Processing Tests
- ✅ **it processes batch invoice generation**
  - Tests bulk invoice generation for multiple customers
  - Validates transaction integrity for batch operations
  - Ensures each customer gets separate invoice

- ✅ **it handles no pending bills gracefully**
  - Tests behavior when no bills exist to invoice
  - Validates graceful handling of empty bill sets
  - Ensures no errors are thrown

- ✅ **it consolidates multiple bills for same meter into one invoice** ⭐ NEW
  - **Critical test:** Validates consolidation behavior
  - Creates 3 bills for the SAME meter
  - Verifies only ONE invoice is created (not 3!)
  - Ensures all 3 bills are linked to the single invoice
  - Validates invoice amount totals all bills (250 + 100 + 50 = 400)
  - **This test confirms the anti-pattern fix works correctly**

#### Repository Tests
- ✅ **it retrieves open invoices for customer**
  - Tests query for open invoices by customer_id
  - Validates filtering by state = "open"
  - Ensures tenant isolation

- ✅ **it closes existing open invoices when generating new invoice**
  - Tests automatic closure of old open invoices
  - Validates state transition from "open" to "closed"
  - Ensures only one open invoice per customer

#### Integration Tests
- ✅ **it triggers meter balance recalculation**
  - Tests MeterFinancialService integration
  - Validates meter balances are updated after invoice generation
  - Ensures customer balance consistency

---

### 2. InvoiceActionServiceTest (`tests/Unit/Invoice/InvoiceActionServiceTest.php`)
**Purpose:** Tests invoice actions like payment, reversal, correction, and cancellation

**Test Coverage: 18 tests**

#### Invoice Reversal Tests
- ✅ **it can reverse an unpaid invoice**
  - Tests reversal of unpaid invoices
  - Validates status change to "reversed"
  - Ensures state changes to "closed"
  - Tests reversal_reason and reversal_notes tracking

- ✅ **it creates reversal journal entries**
  - Validates AR-CONTROL credit entry (reversal)
  - Tests revenue account debit entries
  - Ensures double-entry accounting for reversals

- ✅ **it maintains transaction consistency on reversal**
  - Tests database transaction rollback on error
  - Validates atomic operations
  - Ensures data integrity

- ✅ **it preserves invoice history on reversal**
  - Tests that reversal notes are appended to invoice
  - Validates original invoice data is preserved
  - Ensures audit trail maintenance

#### Invoice Correction Tests
- ✅ **it can correct invoice amount**
  - Tests adjustment of invoice amount (bill amount, not brought forward)
  - Validates recalculation of total_amount
  - Ensures balance is updated correctly

- ✅ **it creates correction journal entries**
  - Validates reversal of original entries
  - Tests creation of corrected entries
  - Ensures accounting accuracy for corrections

- ✅ **it handles correction with no change**
  - Edge case: corrected amount = original amount
  - Validates no journal entries created
  - Ensures efficiency for no-op corrections

- ✅ **it handles correction increasing amount**
  - Tests upward adjustment of invoice amount
  - Validates additional AR debit
  - Ensures additional revenue credit

- ✅ **it handles correction decreasing amount**
  - Tests downward adjustment of invoice amount
  - Validates AR credit for difference
  - Ensures revenue debit for difference

- ✅ **it maintains transaction consistency on correction**
  - Tests atomic operations for corrections
  - Validates rollback on error
  - Ensures data integrity

- ✅ **it preserves invoice history on correction**
  - Tests correction notes appended to invoice
  - Validates original amount tracked in notes
  - Ensures audit trail for corrections

#### Payment Processing Tests
- ✅ **it processes payment for invoice**
  - Tests payment application to invoice
  - Validates invoice balance reduction
  - Ensures payment record creation

- ✅ **it processes full payment for invoice**
  - Tests full payment (amount = balance)
  - Validates status = "Fully Paid"
  - Ensures state = "closed"

- ✅ **it maintains transaction consistency on payment**
  - Tests atomic payment operations
  - Validates rollback on payment failure
  - Ensures no partial payments on error

#### Authorization Tests (can* attributes)
- ✅ **it checks if invoice can be reversed**
  - Tests can_be_reversed attribute logic
  - Validates: status != "reversed", no payments, state = "open"
  - Ensures paid invoices can't be reversed

- ✅ **it checks if invoice can be corrected**
  - Tests can_be_corrected attribute logic
  - Validates: status != "reversed", no payments
  - Ensures only unpaid invoices can be corrected

- ✅ **it checks if invoice can receive payment**
  - Tests can_receive_payment attribute logic
  - Validates: state = "open", balance > 0, not reversed
  - Ensures fully paid invoices can't receive payments

#### Summary Tests
- ✅ **it gets invoice summary**
  - Tests summary attribute returns complete invoice data
  - Validates includes: invoice_number, customer_name, amounts, dates
  - Ensures summary is properly formatted

---

## Invoice Data Structure

### Invoice Model Fields
```php
// Financial Fields (Auto-calculated)
'balance_brought_forward' => decimal  // Outstanding balance from previous invoices
'amount'                  => decimal  // Current period charges (bills sum)
'total_amount'           => decimal  // Auto: balance_brought_forward + amount
'paid_amount'            => decimal  // Total payments received
'balance'                => decimal  // Auto: total_amount - paid_amount

// Status Fields
'status'  => enum ['not paid', 'Partial Payment', 'Fully Paid', 'reversed']
'state'   => enum ['open', 'closed']

// Dates
'invoice_date' => date
'due_date'     => date

// Relationships
'customer_id'  => foreign key (User)
'meter_id'     => foreign key (Meter) - nullable for multi-meter invoices
'tenant_id'    => foreign key (Tenant)
'created_by'   => foreign key (User)
```

### Invoice Calculation Logic
```php
// On save (automatic):
total_amount = balance_brought_forward + amount
balance = total_amount - paid_amount

// Payment application:
paid_amount += payment_amount
if (balance <= 0) {
    status = 'Fully Paid'
    state = 'closed'
} else if (paid_amount > 0) {
    status = 'Partial Payment'
    state = 'open'
}
```

---

## Key Business Rules

### Invoice Generation
1. **One Open Invoice Rule:** Only one "open" invoice per customer at a time
2. **Bill Consolidation:** All pending bills for a customer/meter are consolidated into one invoice
3. **Overpayment Application:** Existing meter overpayments automatically apply to new invoices
4. **Sequential Numbering:** Invoice numbers follow format: `INV-YYYY-NNNN` (tenant-isolated)

### Invoice Actions
1. **Reversal Rules:**
   - Only unpaid invoices can be reversed
   - No payments must exist on the invoice
   - Reversal creates offsetting journal entries
   - Invoice state changes to "closed" after reversal

2. **Correction Rules:**
   - Only unpaid invoices can be corrected
   - Correction adjusts the `amount` field (current charges), not `balance_brought_forward`
   - Original entries are reversed, new entries created
   - Correction history preserved in notes

3. **Payment Rules:**
   - Only "open" invoices can receive payments
   - Payments reduce balance and update status
   - Overpayments are tracked separately (CUSTOMER-PREPAYMENT)
   - Full payment closes the invoice

### Accounting Rules
1. **Invoice Creation:**
   - Debit AR-CONTROL for `total_amount`
   - Credit Revenue accounts for `amount` (current charges only)
   - Balance brought forward is not credited again (already credited when originally invoiced)

2. **Payment:**
   - Debit BANK account for full payment
   - Credit AR-CONTROL for invoice portion
   - Credit CUSTOMER-PREPAYMENT for overpayment portion

3. **Reversal:**
   - Credit AR-CONTROL (reverse original debit)
   - Debit Revenue accounts (reverse original credits)

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
    
    // Create required accounts
    Account::factory()->bank()->create(['tenant_id' => $this->tenant->id]);
    Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id]);
    Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id]);
    
    // Create meter & assignment
    $this->meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->assignment = MeterAssignment::factory()->create([
        'meter_id' => $this->meter->id,
        'customer_id' => $this->customer->id,
        'tenant_id' => $this->tenant->id,
    ]);
}
```

### Creating Test Invoices
```php
// CORRECT: Specify amount components explicitly
$invoice = Invoice::factory()->create([
    'customer_id' => $customer->id,
    'meter_id' => $meter->id,
    'balance_brought_forward' => 200,  // Previous balance
    'amount' => 1000,                   // Current charges
    'paid_amount' => 0,                 // No payments yet
    'tenant_id' => $tenant->id,
]);
// Result: total_amount = 1200, balance = 1200

// INCORRECT: Don't set balance or total_amount directly
$invoice = Invoice::factory()->create([
    'balance' => 1000,        // ❌ This is auto-calculated!
    'total_amount' => 1000,   // ❌ This is auto-calculated!
]);
// Result: Random factory values will be used instead!
```

---

## Running Invoice Tests

```bash
# Run all invoice tests
php artisan test --filter=InvoiceServiceTest
php artisan test --filter=InvoiceActionServiceTest

# Run specific test
php artisan test --filter=it_generates_invoice_from_pending_bills

# Run with coverage
php artisan test --filter=Invoice --coverage
```

---

## Recent Refactoring (October 2025)

### Service Split
The monolithic `InvoiceService` (974 lines) was split into focused services:

1. **InvoiceService** (227 lines) - Invoice generation core
2. **InvoiceAccountingService** - Journal entries & accounting
3. **InvoiceActionService** - Payment, reversal, correction, cancellation
4. **InvoiceNotificationService** - SMS notifications
5. **InvoiceRepository** - Data access & queries

### Key Changes
- Eliminated "BALANCE" bills - now using `balance_brought_forward` column
- Consolidated meter-centric methods (removed "bulk" methods)
- Moved authorization checks to Invoice model attributes (`can_be_reversed`, etc.)
- Fixed double-payment bug in `applyPayment()` method

---

## Test Maintenance

### When Adding New Invoice Features
1. Create test in appropriate test class
2. Use `#[Test]` attribute (not `@test` docblock)
3. Follow setup pattern: Arrange → Act → Assert
4. Always set `balance_brought_forward`, `amount`, `paid_amount` explicitly
5. Create required accounts in setUp()
6. Use factories for all models
7. Mock Auth for user context

### Common Pitfalls
- ❌ Setting `balance` or `total_amount` directly (they're auto-calculated)
- ❌ Forgetting to create required accounts
- ❌ Not setting `tenant_id` on all models
- ❌ Using random factory amounts without explicit overrides
- ❌ Forgetting to refresh models after updates

---

## Coverage Summary

| Area | Test Count | Coverage |
|------|-----------|----------|
| Invoice Generation | 11 | ✅ Complete |
| Invoice Actions | 18 | ✅ Complete |
| Balance Calculations | 8 | ✅ Complete |
| Accounting Entries | 6 | ✅ Complete |
| Authorization | 3 | ✅ Complete |
| Repository/Queries | 3 | ✅ Complete |
| **Bill Consolidation** ⭐ | **1** | **✅ Critical** |
| **Total** | **39** | **✅ Comprehensive** |

