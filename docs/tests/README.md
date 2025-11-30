# Hydra Billing - Test Documentation

This directory contains comprehensive documentation for all tests in the Hydra Billing system.

## 📚 Documentation Index

### Core Test Documentation
1. **[Invoice Tests](./INVOICE_TESTS.md)** - Invoice generation, actions, and lifecycle (38 tests)
2. **[Payment Tests](./PAYMENT_TESTS.md)** - Payment processing, reversal, and customer payments (45 tests)
3. **[Bill Tests](./BILL_TESTS.md)** - Bill creation, batch processing, and references (61 tests)

---

## 🎯 Quick Start

### Running All Tests
```bash
# Run complete test suite
php artisan test

# Run only unit tests
php artisan test --testsuite=Unit

# Run only feature/integration tests
php artisan test --testsuite=Feature
```

### Running Specific Test Categories
```bash
# Invoice tests
php artisan test --filter=Invoice

# Payment tests
php artisan test --filter=Payment

# Bill tests
php artisan test --filter=Bill

# Meter tests
php artisan test --filter=Meter
```

### Running Individual Test Files
```bash
php artisan test tests/Unit/Invoice/InvoiceServiceTest.php
php artisan test tests/Unit/Payment/PaymentServiceTest.php
php artisan test tests/Unit/Bill/BillCreationServiceTest.php
```

---

## 📊 Test Coverage Overview

| Category | Test Files | Test Count | Status |
|----------|-----------|-----------|--------|
| **Invoices** | 2 | 39 | ✅ Complete (Added consolidation test) |
| **Payments** | 3 | 45 | ✅ Complete |
| **Bills** | 4 | 61 | ✅ Complete |
| **Meters** | 2 | 23 | ✅ Complete |
| **Customers** | 2 | 13 | ✅ Complete |
| **Messaging** | 4 | 40 | ✅ Complete |
| **Integration** | 4 | 43 | ✅ Complete |
| **TOTAL** | **21** | **264** | **✅ Comprehensive** |

---

## 🏗️ Test Architecture

### Test Structure
```
tests/
├── Unit/                           # Unit tests organized by domain
│   ├── Invoice/
│   │   ├── InvoiceServiceTest.php
│   │   └── InvoiceActionServiceTest.php
│   ├── Payment/
│   │   ├── PaymentServiceTest.php
│   │   ├── PaymentReversalServiceTest.php
│   │   └── CustomerPaymentServiceTest.php
│   ├── Bill/
│   │   ├── BillServiceTest.php
│   │   ├── BillCreationServiceTest.php
│   │   ├── BillBatchServiceTest.php
│   │   └── BillReferenceServiceTest.php
│   ├── Meter/
│   │   ├── MeterFinancialServiceTest.php
│   │   ├── MeterAssignmentServiceTest.php
│   │   └── AutoApplyOverpaymentTest.php
│   ├── Customer/
│   │   └── CustomerServiceTest.php
│   ├── Messaging/
│   │   ├── MessagingServiceTest.php
│   │   ├── MessageResolverTest.php
│   │   └── TemplateServiceTest.php
│   └── Database/
│       ├── DatabaseSetupTest.php
│       └── Seeders/
│           └── MessageTemplateSeederTest.php
│
├── Feature/                        # Integration tests by domain
│   ├── Billing/
│   │   └── BillingCycleIntegrationTest.php
│   ├── Customer/
│   │   └── CustomerCreationTest.php
│   ├── Messaging/
│   │   └── MessagingIntegrationTest.php
│   └── EdgeCasesAndErrorHandlingTest.php
│
├── Integration/                    # Integration tests
│   └── Meter/
│       └── AutoApplyOverpaymentIntegrationTest.php
│
└── TestCase.php                   # Base test case with common setup
```

### Test Naming Convention
Tests use the `#[Test]` PHP attribute and descriptive method names:
```php
#[Test]
public function it_generates_invoice_from_pending_bills()
{
    // Arrange
    // Act
    // Assert
}
```

---

## 🔧 Standard Test Setup

### Base Test Configuration (`tests/TestCase.php`)
- Uses in-memory SQLite database
- Runs fresh migrations before each test
- Disables multitenancy for test environment
- Provides consistent base state

### Common Test Setup Pattern
```php
protected function setUp(): void
{
    parent::setUp();
    
    // 1. Create Tenant
    $this->tenant = Tenant::factory()->create();
    
    // 2. Create Users (admin and customers)
    $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
    $this->customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    
    // 3. Create Required Accounts
    Account::factory()->bank()->create(['tenant_id' => $this->tenant->id]);
    Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id]);
    Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id]);
    
    // 4. Create Related Models (meters, assignments, etc.)
    $this->meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->assignment = MeterAssignment::factory()->create([
        'meter_id' => $this->meter->id,
        'customer_id' => $this->customer->id,
        'tenant_id' => $this->tenant->id,
    ]);
    
    // 5. Mock Auth
    Auth::shouldReceive('user')->andReturn($this->admin);
    Auth::shouldReceive('id')->andReturn($this->admin->id);
}
```

---

## ⚠️ Critical Test Data Guidelines

### Invoice Creation (IMPORTANT!)
```php
// ✅ CORRECT: Explicitly set amount components
$invoice = Invoice::factory()->create([
    'customer_id' => $customer->id,
    'balance_brought_forward' => 200,  // Previous balance
    'amount' => 1000,                   // Current period charges
    'paid_amount' => 0,                 // Payments received
    'tenant_id' => $tenant->id,
]);
// Result: total_amount = 1200 (auto-calculated)
//         balance = 1200 (auto-calculated)

// ❌ WRONG: Don't set auto-calculated fields
$invoice = Invoice::factory()->create([
    'balance' => 1000,        // This is IGNORED! (auto-calculated)
    'total_amount' => 1000,   // This is IGNORED! (auto-calculated)
]);
// Result: Random factory values used instead!
```

### Payment Creation
```php
// ✅ ALWAYS set meter_id (required for balance calculations)
$payment = Payment::create([
    'customer_id' => $customer->id,
    'invoice_id' => $invoice->id,
    'meter_id' => $meter->id,     // CRITICAL!
    'amount' => 500,
    'method' => 'mpesa',
    'tenant_id' => $tenant->id,
]);
```

### Bill Creation
```php
// ✅ CORRECT: Set reference format properly
$bill = Bill::factory()->create([
    'customer_id' => $customer->id,
    'reference' => '2025-10-0001',  // Format: YYYY-MM-NNNN
    'tenant_id' => $tenant->id,
]);
```

---

## 🧪 Test Categories

### Unit Tests
**Purpose:** Test individual services in isolation

**Characteristics:**
- Fast execution (< 0.1s per test)
- Isolated from external dependencies
- Mock external services
- Focus on single service/class
- Test edge cases and error handling

**Examples:**
- `InvoiceServiceTest` - Invoice generation logic
- `PaymentServiceTest` - Payment processing logic
- `BillCreationServiceTest` - Bill creation logic

### Feature/Integration Tests
**Purpose:** Test complete workflows and integrations

**Characteristics:**
- Slower execution (0.1s - 1s per test)
- Test full user journeys
- Validate cross-service integrations
- Ensure end-to-end functionality
- Test transaction boundaries

**Examples:**
- `BillingCycleIntegrationTest` - Complete billing cycle
- `CustomerCreationTest` - Customer onboarding flow
- `EdgeCasesAndErrorHandlingTest` - Error scenarios
- `MessagingIntegrationTest` - SMS integration

---

## 🎓 Testing Best Practices

### 1. Arrange-Act-Assert Pattern
```php
#[Test]
public function it_processes_payment_correctly()
{
    // Arrange: Set up test data
    $invoice = Invoice::factory()->create([...]);
    $paymentData = ['amount' => 500, 'method' => 'cash'];
    
    // Act: Execute the action
    $service->handlePayment($invoice, $paymentData);
    
    // Assert: Verify the result
    $this->assertEquals(0, $invoice->fresh()->balance);
}
```

### 2. Use Descriptive Test Names
```php
// ✅ Good: Describes what is being tested
public function it_handles_overpayment_correctly()

// ❌ Bad: Vague, unclear intent
public function test_payment()
```

### 3. Test One Thing Per Test
```php
// ✅ Good: Single assertion focus
public function it_creates_payment_record()
{
    $payment = $service->createPayment($data);
    $this->assertDatabaseHas('payments', ['id' => $payment->id]);
}

// ❌ Bad: Testing multiple concerns
public function it_handles_payment()
{
    $payment = $service->createPayment($data);
    $this->assertDatabaseHas('payments', [...]);
    $this->assertDatabaseHas('journals', [...]);
    $this->assertEquals(...);
    // Too much in one test!
}
```

### 4. Always Test Edge Cases
- Zero amounts
- Negative amounts
- Null values
- Empty collections
- Boundary values
- Concurrent operations
- Transaction rollbacks

### 5. Use Factories for Test Data
```php
// ✅ Good: Use factories
$customer = User::factory()->create(['tenant_id' => $tenant->id]);

// ❌ Bad: Manual creation
$customer = new User();
$customer->name = 'Test User';
$customer->save();
```

### 6. Ensure Multi-tenancy
```php
// ✅ Always set tenant_id
$invoice = Invoice::factory()->create([
    'customer_id' => $customer->id,
    'tenant_id' => $this->tenant->id,  // Required!
]);
```

### 7. Test Transaction Rollbacks
```php
#[Test]
public function it_rolls_back_on_error()
{
    $initialCount = Payment::count();
    
    try {
        $service->processPaymentWithError($data);
    } catch (\Exception $e) {
        // Expected
    }
    
    $this->assertEquals($initialCount, Payment::count());
}
```

---

## 🐛 Common Test Issues & Solutions

### Issue 1: Random Factory Values
**Problem:** Tests fail because factory generates random amounts
```php
// ❌ Problem
$invoice = Invoice::factory()->create(['balance' => 1000]);
// Factory ignores 'balance' and generates random amount

// ✅ Solution
$invoice = Invoice::factory()->create([
    'balance_brought_forward' => 0,
    'amount' => 1000,
    'paid_amount' => 0,
]);
```

### Issue 2: Missing Required Accounts
**Problem:** Tests fail with "No query results for model [App\Models\Account]"
```php
// ✅ Solution: Create accounts in setUp()
Account::factory()->bank()->create(['tenant_id' => $this->tenant->id]);
Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id]);
Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id]);
```

### Issue 3: Missing meter_id on Payment
**Problem:** Balance calculations fail silently
```php
// ❌ Problem
Payment::create([
    'customer_id' => $customer->id,
    'invoice_id' => $invoice->id,
    // meter_id is missing!
]);

// ✅ Solution
Payment::create([
    'customer_id' => $customer->id,
    'invoice_id' => $invoice->id,
    'meter_id' => $meter->id,  // Required!
]);
```

### Issue 4: Tenant ID Not Set
**Problem:** Tests fail or data leaks across tenants
```php
// ✅ Solution: Always set tenant_id
Model::factory()->create(['tenant_id' => $this->tenant->id]);
```

---

## 📈 Test Execution Times

| Test Suite | Tests | Time | Avg per Test |
|------------|-------|------|--------------|
| Unit Tests | 220 | ~15s | 0.07s |
| Feature Tests | 43 | ~13s | 0.30s |
| **Total** | **263** | **~28s** | **0.11s** |

---

## 🔍 Finding Specific Tests

### By Feature
```bash
# Invoice-related
php artisan test --filter=Invoice

# Payment-related
php artisan test --filter=Payment

# Bill-related
php artisan test --filter=Bill

# Meter-related
php artisan test --filter=Meter

# Customer-related
php artisan test --filter=Customer

# Messaging-related
php artisan test --filter=Messaging
```

### By Test Name
```bash
# Run specific test by name
php artisan test --filter=it_handles_overpayment_correctly

# Run tests matching pattern
php artisan test --filter="handles.*payment"
```

### By File or Directory
```bash
# Run single test file
php artisan test tests/Unit/Payment/PaymentServiceTest.php

# Run all tests in a domain
php artisan test tests/Unit/Invoice/
php artisan test tests/Unit/Payment/
php artisan test tests/Unit/Bill/

# Run all unit tests
php artisan test tests/Unit/

# Run all feature tests
php artisan test tests/Feature/
```

---

## 📝 Adding New Tests

### 1. Choose Appropriate Test Class
- **Service logic** → Unit test
- **Full workflow** → Feature test
- **Utilities/Helpers** → Unit test
- **API endpoints** → Feature test

### 2. Follow Template
```php
<?php

namespace Tests\Unit\Payment; // Use appropriate domain namespace

use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use App\Models\{User, Tenant, Account};
use Illuminate\Support\Facades\Auth;

class NewServiceTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;
    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Create required accounts
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id]);
        // ... other setup
        
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
    }

    #[Test]
    public function it_does_something_correctly()
    {
        // Arrange
        $data = [...];
        
        // Act
        $result = $service->doSomething($data);
        
        // Assert
        $this->assertTrue($result);
    }
}
```

### 3. Update Documentation
- Add test description to relevant doc file
- Update test count in README
- Document any new edge cases

---

## 🚀 Recent Test Updates (October 2025)

### Invoice Refactoring
- Split monolithic InvoiceService into 5 focused services
- Updated 38 invoice tests to new service structure
- Added `balance_brought_forward` field tests
- Eliminated "BALANCE" bill tests

### Payment Bug Fixes
- Fixed double-payment bug in InvoiceActionService
- Updated 45 payment tests with explicit invoice amounts
- Added CONNECTION_FEE account to test setups

### Test Data Improvements
- Standardized invoice factory usage
- Enforced explicit amount setting in tests
- Improved test reliability with deterministic data

---

## 📞 Support

For questions about tests:
1. Check relevant test documentation file
2. Review test file comments
3. Run specific test to see failure details
4. Check TestCase.php for base setup

---

## ✅ Test Checklist

Before committing code:
- [ ] All tests pass (`php artisan test`)
- [ ] New features have tests
- [ ] Edge cases are covered
- [ ] Multi-tenancy is tested
- [ ] Transaction rollbacks work
- [ ] Test data uses factories
- [ ] Documentation is updated
- [ ] No skipped/pending tests (unless intentional)

---

**Last Updated:** October 16, 2025  
**Total Tests:** 264 (Added consolidation test)  
**Pass Rate:** 100% ✅

