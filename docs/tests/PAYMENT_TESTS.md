# Payment Tests Documentation

This document details all tests related to payment functionality in the Hydra Billing system.

## Test Files

### 1. PaymentServiceTest (`tests/Unit/Payment/PaymentServiceTest.php`)
**Purpose:** Tests payment processing, journal entries, and invoice updates

**Test Coverage: 18 tests**

#### Payment Processing Tests
- ✅ **it handles payment correctly when payment matches invoice amount**
  - Tests exact payment (payment = invoice balance)
  - Validates invoice status = "Fully Paid"
  - Ensures invoice state = "closed"
  - Verifies payment record creation

- ✅ **it handles overpayment correctly**
  - Tests payment > invoice balance
  - Validates overpayment calculation and tracking
  - Ensures CUSTOMER-PREPAYMENT account is credited
  - Verifies meter overpayment is updated

- ✅ **it handles partial payment correctly**
  - Tests payment < invoice balance
  - Validates invoice status = "Partial Payment"
  - Ensures invoice remains "open"
  - Verifies balance is reduced correctly

#### Payment Record Tests
- ✅ **it creates payment record with all required fields**
  - Validates payment model has all necessary data
  - Tests: customer_id, invoice_id, meter_id, amount, method, reference
  - Ensures tenant_id and created_by are set
  - Verifies payment date is current

- ✅ **it supports different payment methods**
  - Tests multiple payment methods: cash, mpesa, bank, card, cheque
  - Validates method is stored correctly
  - Ensures reference tracking per method

- ✅ **it sets payment date to current date**
  - Validates date field is auto-set to now()
  - Tests date consistency across payments

- ✅ **it sets meter id on payment record**
  - Critical: Tests meter_id is set for balance calculations
  - Validates meter-payment linkage
  - Ensures meter financial tracking works

- ✅ **it handles payment with custom created by**
  - Tests custom user can be set as creator
  - Validates audit trail for manual payments
  - Ensures flexibility for system payments

#### Invoice Update Tests
- ✅ **it updates invoice balance after payment**
  - Tests invoice paid_amount increases
  - Validates balance decreases
  - Ensures calculations are accurate

- ✅ **it updates invoice status after full payment**
  - Tests status change on full payment
  - Validates state transition to "closed"
  - Ensures no further payments accepted

- ✅ **it handles multiple payments on same invoice**
  - Tests sequential payments
  - Validates cumulative paid_amount
  - Ensures balance tracking across payments
  - Tests eventual full payment

#### Journal Entry Tests
- ✅ **it creates debit journal entry to bank account**
  - Validates BANK-001 account debit for full payment
  - Tests amount = payment amount
  - Ensures proper accounting entry

- ✅ **it creates credit journal entry to ar control**
  - Validates AR-CONTROL account credit
  - Tests amount = invoice portion (not overpayment)
  - Ensures receivables are reduced

- ✅ **it creates overpayment journal entry when payment exceeds balance**
  - Tests CUSTOMER-PREPAYMENT account credit
  - Validates overpayment amount calculation
  - Ensures liability is recorded

- ✅ **it includes proper journal entry descriptions**
  - Tests descriptions contain: invoice number, method, reference
  - Validates audit trail in journal entries
  - Ensures traceability

- ✅ **it handles zero overpayment when payment equals balance**
  - Edge case: payment exactly matches balance
  - Validates no overpayment entry created
  - Ensures efficiency for exact payments

#### Integration Tests
- ✅ **it triggers meter balance recalculation after payment**
  - Tests MeterFinancialService integration
  - Validates meter balance, overpayment, total_paid updated
  - Ensures customer aggregated balances sync

- ✅ **it sets tenant id on all created records**
  - Tests tenant isolation for payment, journals
  - Validates multi-tenancy compliance
  - Ensures data segregation

---

### 2. PaymentReversalServiceTest (`tests/Unit/Payment/PaymentReversalServiceTest.php`)
**Purpose:** Tests payment reversal functionality and accounting corrections

**Test Coverage: 12 tests**

#### Invoice Payment Reversal Tests
- ✅ **it reverses an invoice payment**
  - Tests reversal of payment linked to invoice
  - Validates payment status = "reversed"
  - Ensures invoice balance is restored
  - Tests paid_amount reduction

- ✅ **it creates reversal journal entries for invoice payment**
  - Validates BANK account credit (cash returned)
  - Tests AR-CONTROL debit (receivable restored)
  - Ensures double-entry reversal

- ✅ **it handles payment with overpayment reversal**
  - Tests reversal when payment had overpayment
  - Validates CUSTOMER-PREPAYMENT debit
  - Ensures overpayment is removed

#### Advance Payment Reversal Tests
- ✅ **it reverses an advance payment**
  - Tests reversal of payment without invoice
  - Validates payment status = "reversed"
  - Ensures CUSTOMER-PREPAYMENT is debited

- ✅ **it creates reversal journal entries for advance payment**
  - Tests BANK credit (cash returned)
  - Validates CUSTOMER-PREPAYMENT debit (liability reduced)
  - Ensures proper accounting for prepayments

#### Reversal Validation Tests
- ✅ **it prevents reversing already reversed payment**
  - Tests duplicate reversal prevention
  - Validates exception is thrown
  - Ensures data integrity

#### Audit Trail Tests
- ✅ **it sets reversed by to current user**
  - Tests reversed_by field is set
  - Validates audit trail for reversals
  - Ensures accountability

- ✅ **it stores reversal reason**
  - Tests reversal_reason field is populated
  - Validates reason is required
  - Ensures business justification tracking

- ✅ **it maintains audit trail with reversal reference**
  - Tests journal entries include reversal reference
  - Validates reference format
  - Ensures traceability of reversals

- ✅ **it includes reason in journal descriptions**
  - Tests journal descriptions contain reversal reason
  - Validates complete audit trail
  - Ensures context is preserved

#### Transaction Tests
- ✅ **it uses database transaction for reversal**
  - Tests atomic reversal operations
  - Validates rollback on error
  - Ensures all-or-nothing reversal

- ✅ **it sets correct tenant id on reversal journals**
  - Tests tenant isolation in reversal entries
  - Validates multi-tenancy compliance
  - Ensures data segregation

---

### 3. CustomerPaymentServiceTest (`tests/Unit/Payment/CustomerPaymentServiceTest.php`)
**Purpose:** Tests customer-facing payment features and payment context

**Test Coverage: 15 tests**

#### Payment Context Tests
- ✅ **it gets customer payment context with invoice**
  - Tests payment context when customer has unpaid invoice
  - Validates context includes: invoice, balance, due date
  - Ensures correct data for payment forms

- ✅ **it gets customer payment context without invoice**
  - Tests payment context when no invoice exists
  - Validates advance payment scenario
  - Ensures graceful handling of no-invoice case

- ✅ **it gets latest unpaid invoice correctly**
  - Tests retrieval of most recent unpaid invoice
  - Validates ordering by invoice_date DESC
  - Ensures correct invoice is selected

- ✅ **it returns null when no unpaid invoices exist**
  - Tests behavior when all invoices paid
  - Validates null return
  - Ensures graceful handling

#### Quick Pay Tests
- ✅ **it processes full payment against invoice**
  - Tests customer quick payment (full amount)
  - Validates invoice is fully paid
  - Ensures payment record created

- ✅ **it processes partial payment against invoice**
  - Tests customer partial payment
  - Validates invoice partial payment status
  - Ensures balance tracking

- ✅ **it processes overpayment against invoice**
  - Tests customer overpayment scenario
  - Validates overpayment tracking
  - Ensures credit is stored for future use

- ✅ **it processes advance payment when no invoice exists**
  - Tests customer advance payment (no invoice)
  - Validates payment creates overpayment/credit
  - Ensures future invoice application

- ✅ **it can pay invoice directly**
  - Tests direct invoice payment method
  - Validates payment processing
  - Ensures UI integration works

#### Validation Tests
- ✅ **it validates payment amounts**
  - Tests amount validation (positive, numeric)
  - Validates minimum payment requirements
  - Ensures data integrity

- ✅ **it handles payment processing errors gracefully**
  - Tests error handling in payment flow
  - Validates exception propagation
  - Ensures transaction rollback

#### Account Availability Tests
- ✅ **it handles missing accounts gracefully**
  - Tests behavior when required accounts missing
  - Validates error messaging
  - Ensures clear feedback for setup issues

#### Form Helper Tests
- ✅ **it gets correct payment form defaults**
  - Tests default values for payment forms
  - Validates pre-filled amounts
  - Ensures good UX

- ✅ **it generates correct help text**
  - Tests contextual help text generation
  - Validates customer guidance
  - Ensures clear instructions

#### Transaction Integrity Tests
- ✅ **it maintains database consistency with transactions**
  - Tests atomic payment operations
  - Validates rollback on failure
  - Ensures data integrity across tables

---

## Payment Data Structure

### Payment Model Fields
```php
// Core Fields
'customer_id'  => foreign key (User) - Required
'invoice_id'   => foreign key (Invoice) - Nullable (for advance payments)
'meter_id'     => foreign key (Meter) - Required (for balance tracking)

// Payment Details
'amount'       => decimal - Payment amount received
'method'       => enum ['cash', 'mpesa', 'bank', 'card', 'cheque']
'reference'    => string - Transaction reference (M-Pesa code, cheque #, etc.)
'status'       => enum ['pending', 'paid', 'reversed']
'date'         => date - Payment date

// Reversal Fields
'reversed_at'     => timestamp - When payment was reversed
'reversed_by'     => foreign key (User) - Who reversed it
'reversal_reason' => text - Why it was reversed

// Multi-tenancy
'tenant_id'    => foreign key (Tenant)
'created_by'   => foreign key (User)
```

### Payment Processing Flow
```php
// 1. Determine payment allocation
$invoiceBalance = $invoice->balance;
$paymentToInvoice = min($paymentAmount, $invoiceBalance);
$overpayment = max(0, $paymentAmount - $invoiceBalance);

// 2. Create payment record
Payment::create([
    'customer_id' => $customer->id,
    'invoice_id' => $invoice->id,
    'meter_id' => $meter->id,  // CRITICAL for balance calculation
    'amount' => $paymentAmount,
    'method' => $method,
    'reference' => $reference,
    'status' => 'paid',
]);

// 3. Create journal entries
Journal::create(['account' => 'BANK', 'type' => 'debit', 'amount' => $paymentAmount]);
Journal::create(['account' => 'AR-CONTROL', 'type' => 'credit', 'amount' => $paymentToInvoice]);
if ($overpayment > 0) {
    Journal::create(['account' => 'CUSTOMER-PREPAYMENT', 'type' => 'credit', 'amount' => $overpayment]);
}

// 4. Update invoice
$invoice->paid_amount += $paymentToInvoice;
$invoice->balance = $invoice->total_amount - $invoice->paid_amount;
if ($invoice->balance <= 0) {
    $invoice->status = 'Fully Paid';
    $invoice->state = 'closed';
}

// 5. Recalculate meter balances
MeterFinancialService::recalculateCustomerMeters($customer->id);
```

---

## Key Business Rules

### Payment Processing
1. **Invoice Requirement:** Payments can be linked to an invoice OR be advance payments
2. **Meter Requirement:** All payments MUST have meter_id for balance tracking
3. **Overpayment Handling:** Excess payment stored as CUSTOMER-PREPAYMENT credit
4. **Automatic Application:** Overpayments automatically apply to next invoice generation

### Payment Methods
- **Cash:** Direct cash payment, reference is receipt number
- **M-Pesa:** Mobile money, reference is M-Pesa confirmation code
- **Bank:** Bank transfer, reference is transaction ID
- **Card:** Card payment, reference is authorization code
- **Cheque:** Cheque payment, reference is cheque number

### Payment Reversal
1. **Authorization:** Only authorized users can reverse payments
2. **Reason Required:** Reversal reason must be provided
3. **Audit Trail:** Complete audit trail maintained (reversed_by, reversed_at, reason)
4. **Accounting:** Full reversal of journal entries
5. **Invoice Update:** Invoice balance and paid_amount restored
6. **No Re-reversal:** Already reversed payments cannot be reversed again

### Payment Allocation Priority
1. Apply to invoice balance first (if invoice exists)
2. Excess goes to CUSTOMER-PREPAYMENT (overpayment)
3. Overpayments apply to next invoice automatically
4. Advance payments (no invoice) go directly to CUSTOMER-PREPAYMENT

---

## Accounting Rules

### Payment Accounting Entries
```
Payment with Invoice:
DR BANK                    XXX  (cash received)
CR AR-CONTROL              XXX  (receivable reduced)

Payment with Overpayment:
DR BANK                    YYY  (full payment received)
CR AR-CONTROL              XXX  (invoice portion)
CR CUSTOMER-PREPAYMENT     ZZZ  (overpayment portion)
                          ===
where YYY = XXX + ZZZ

Advance Payment (no invoice):
DR BANK                    XXX  (cash received)
CR CUSTOMER-PREPAYMENT     XXX  (liability created)
```

### Payment Reversal Accounting
```
Reverse Invoice Payment:
CR BANK                    XXX  (cash returned)
DR AR-CONTROL              XXX  (receivable restored)

Reverse Payment with Overpayment:
CR BANK                    YYY  (full amount returned)
DR AR-CONTROL              XXX  (invoice portion)
DR CUSTOMER-PREPAYMENT     ZZZ  (overpayment removed)

Reverse Advance Payment:
CR BANK                    XXX  (cash returned)
DR CUSTOMER-PREPAYMENT     XXX  (liability removed)
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
    $this->customer = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'balance' => 1000,
        'overpayment' => 0,
    ]);
    
    // Create required accounts
    Account::factory()->bank()->create(['tenant_id' => $this->tenant->id]);
    Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id]);
    Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id]);
    
    // Create meter
    $this->meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
    
    // Mock Auth
    Auth::shouldReceive('user')->andReturn($this->admin);
    Auth::shouldReceive('id')->andReturn($this->admin->id);
}
```

### Creating Test Payments
```php
// Payment against invoice
$payment = Payment::create([
    'customer_id' => $customer->id,
    'invoice_id' => $invoice->id,
    'meter_id' => $meter->id,     // REQUIRED!
    'amount' => 500,
    'method' => 'mpesa',
    'reference' => 'MPJ1234567',
    'status' => 'paid',
    'date' => now(),
    'tenant_id' => $tenant->id,
    'created_by' => $admin->id,
]);

// Advance payment (no invoice)
$payment = Payment::create([
    'customer_id' => $customer->id,
    'invoice_id' => null,         // No invoice
    'meter_id' => $meter->id,     // Still required!
    'amount' => 1000,
    'method' => 'cash',
    'reference' => 'CASH-001',
    'status' => 'paid',
    'date' => now(),
    'tenant_id' => $tenant->id,
    'created_by' => $admin->id,
]);
```

---

## Common Payment Scenarios

### Scenario 1: Full Payment
```php
// Invoice: balance = 1000
// Payment: amount = 1000
// Result: Invoice fully paid, no overpayment

$invoice->paid_amount = 1000;
$invoice->balance = 0;
$invoice->status = 'Fully Paid';
$invoice->state = 'closed';
```

### Scenario 2: Partial Payment
```php
// Invoice: balance = 1000
// Payment: amount = 400
// Result: Invoice partially paid, balance = 600

$invoice->paid_amount = 400;
$invoice->balance = 600;
$invoice->status = 'Partial Payment';
$invoice->state = 'open';
```

### Scenario 3: Overpayment
```php
// Invoice: balance = 500
// Payment: amount = 700
// Result: Invoice fully paid, overpayment = 200

$invoice->paid_amount = 500;
$invoice->balance = 0;
$invoice->status = 'Fully Paid';
$invoice->state = 'closed';
$meter->overpayment += 200;
```

### Scenario 4: Advance Payment
```php
// No invoice
// Payment: amount = 1000
// Result: Customer credit = 1000

$meter->overpayment += 1000;
// Next invoice will auto-apply this credit
```

### Scenario 5: Multiple Payments
```php
// Invoice: balance = 1000
// Payment 1: amount = 300
// Payment 2: amount = 300
// Payment 3: amount = 400
// Result: Invoice fully paid in 3 installments

$invoice->paid_amount = 1000;  // Sum of all payments
$invoice->balance = 0;
$invoice->status = 'Fully Paid';
$invoice->state = 'closed';
```

---

## Running Payment Tests

```bash
# Run all payment tests
php artisan test --filter=PaymentServiceTest
php artisan test --filter=PaymentReversalServiceTest
php artisan test --filter=CustomerPaymentServiceTest

# Run specific test
php artisan test --filter=it_handles_overpayment_correctly

# Run all payment-related tests
php artisan test --filter=Payment

# Run with coverage
php artisan test --filter=Payment --coverage
```

---

## Test Maintenance

### When Adding New Payment Features
1. Add test to appropriate test class
2. Use `#[Test]` attribute
3. Follow Arrange → Act → Assert pattern
4. Always create required accounts in setUp()
5. Always set meter_id on payments
6. Mock Auth for user context
7. Test both success and failure cases

### Common Pitfalls
- ❌ Forgetting to set meter_id on payment (breaks balance calculation)
- ❌ Not creating required accounts (BANK, AR-CONTROL, CUSTOMER-PREPAYMENT)
- ❌ Not testing transaction rollback on errors
- ❌ Forgetting tenant_id (breaks multi-tenancy)
- ❌ Not validating journal entries
- ❌ Not testing overpayment scenarios
- ❌ Setting invoice `balance` directly (it's auto-calculated)

---

## Coverage Summary

| Area | Test Count | Coverage |
|------|-----------|----------|
| Payment Processing | 8 | ✅ Complete |
| Payment Records | 6 | ✅ Complete |
| Invoice Updates | 4 | ✅ Complete |
| Journal Entries | 6 | ✅ Complete |
| Payment Reversal | 12 | ✅ Complete |
| Customer Context | 8 | ✅ Complete |
| Validation | 3 | ✅ Complete |
| **Total** | **45** | **✅ Comprehensive** |

