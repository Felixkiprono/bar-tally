# Test Organization Plan

## Current State Analysis

### Current Structure Issues
1. **Flat Organization**: Most unit tests are in `tests/Unit/` root with no domain grouping
2. **Inconsistent Subdirectories**: Some tests in `Unit/Services/`, others at root level
3. **No Business Domain Grouping**: Tests aren't organized by what they test (Invoice, Payment, Bill, etc.)
4. **Unclear Integration Tests**: Only one test in `Integration/` folder

### Current Test Inventory (26 total tests)

#### Unit Tests (18 tests)
- AutoApplyOverpaymentTest.php
- BillBatchServiceTest.php
- BillCreationServiceTest.php
- BillReferenceServiceTest.php
- BillServiceTest.php
- CustomerPaymentServiceTest.php
- CustomerServiceTest.php
- DatabaseSetupTest.php
- InvoiceActionServiceTest.php
- InvoiceServiceTest.php
- MeterAssignmentServiceTest.php
- MeterFinancialServiceTest.php
- PaymentReversalServiceTest.php
- PaymentServiceTest.php
- Unit/Services/MessageResolverTest.php
- Unit/Services/MessagingServiceTest.php
- Unit/Services/TemplateServiceTest.php
- Unit/Seeders/MessageTemplateSeederTest.php

#### Feature Tests (5 tests)
- BillingCycleIntegrationTest.php
- CustomerCreationTest.php
- EdgeCasesAndErrorHandlingTest.php
- MessagingIntegrationTest.php
- ExampleTest.php

#### Integration Tests (1 test)
- AutoApplyOverpaymentIntegrationTest.php

---

## Proposed New Structure

### Organizing Principles

1. **Test Type First** (Unit, Feature, Integration)
2. **Business Domain Second** (Invoice, Payment, Bill, Customer, Meter, Messaging)
3. **Service/Component Third** (specific service or component being tested)

### Proposed Directory Structure

```
tests/
├── TestCase.php                           # Base test case
│
├── Unit/                                  # Unit tests (isolated component testing)
│   ├── Invoice/                          # Invoice domain
│   │   ├── InvoiceServiceTest.php
│   │   └── InvoiceActionServiceTest.php
│   │
│   ├── Payment/                          # Payment domain
│   │   ├── PaymentServiceTest.php
│   │   ├── PaymentReversalServiceTest.php
│   │   └── CustomerPaymentServiceTest.php
│   │
│   ├── Bill/                             # Bill domain
│   │   ├── BillServiceTest.php
│   │   ├── BillCreationServiceTest.php
│   │   ├── BillBatchServiceTest.php
│   │   └── BillReferenceServiceTest.php
│   │
│   ├── Meter/                            # Meter domain
│   │   ├── MeterFinancialServiceTest.php
│   │   ├── MeterAssignmentServiceTest.php
│   │   └── AutoApplyOverpaymentTest.php
│   │
│   ├── Customer/                         # Customer domain
│   │   └── CustomerServiceTest.php
│   │
│   ├── Messaging/                        # Messaging domain
│   │   ├── MessagingServiceTest.php
│   │   ├── MessageResolverTest.php
│   │   └── TemplateServiceTest.php
│   │
│   ├── Database/                         # Database/Setup tests
│   │   ├── DatabaseSetupTest.php
│   │   └── Seeders/
│   │       └── MessageTemplateSeederTest.php
│   │
│   └── ExampleTest.php                   # Keep examples at root
│
├── Feature/                               # Feature tests (user workflow testing)
│   ├── Billing/
│   │   └── BillingCycleIntegrationTest.php
│   │
│   ├── Customer/
│   │   └── CustomerCreationTest.php
│   │
│   ├── Messaging/
│   │   └── MessagingIntegrationTest.php
│   │
│   ├── EdgeCasesAndErrorHandlingTest.php  # General edge cases
│   └── ExampleTest.php                     # Keep examples at root
│
└── Integration/                           # Integration tests (multiple components together)
    └── Meter/
        └── AutoApplyOverpaymentIntegrationTest.php
```

---

## Business Domain Categories

### 1. **Invoice Domain** (2 tests)
**Purpose**: Invoice generation, actions, and lifecycle management

**Tests**:
- `InvoiceServiceTest.php` → `Unit/Invoice/InvoiceServiceTest.php`
- `InvoiceActionServiceTest.php` → `Unit/Invoice/InvoiceActionServiceTest.php`

**Namespace**: `Tests\Unit\Invoice`

---

### 2. **Payment Domain** (3 tests)
**Purpose**: Payment processing, recording, and reversal

**Tests**:
- `PaymentServiceTest.php` → `Unit/Payment/PaymentServiceTest.php`
- `PaymentReversalServiceTest.php` → `Unit/Payment/PaymentReversalServiceTest.php`
- `CustomerPaymentServiceTest.php` → `Unit/Payment/CustomerPaymentServiceTest.php`

**Namespace**: `Tests\Unit\Payment`

---

### 3. **Bill Domain** (4 tests)
**Purpose**: Bill creation, management, and batch processing

**Tests**:
- `BillServiceTest.php` → `Unit/Bill/BillServiceTest.php`
- `BillCreationServiceTest.php` → `Unit/Bill/BillCreationServiceTest.php`
- `BillBatchServiceTest.php` → `Unit/Bill/BillBatchServiceTest.php`
- `BillReferenceServiceTest.php` → `Unit/Bill/BillReferenceServiceTest.php`

**Namespace**: `Tests\Unit\Bill`

---

### 4. **Meter Domain** (3 tests)
**Purpose**: Meter financial calculations, assignments, and overpayment handling

**Tests**:
- `MeterFinancialServiceTest.php` → `Unit/Meter/MeterFinancialServiceTest.php`
- `MeterAssignmentServiceTest.php` → `Unit/Meter/MeterAssignmentServiceTest.php`
- `AutoApplyOverpaymentTest.php` → `Unit/Meter/AutoApplyOverpaymentTest.php`

**Namespace**: `Tests\Unit\Meter`

---

### 5. **Customer Domain** (2 tests)
**Purpose**: Customer management and account creation

**Tests**:
- `CustomerServiceTest.php` → `Unit/Customer/CustomerServiceTest.php`
- `CustomerCreationTest.php` (Feature) → `Feature/Customer/CustomerCreationTest.php`

**Namespaces**: 
- `Tests\Unit\Customer`
- `Tests\Feature\Customer`

---

### 6. **Messaging Domain** (4 tests)
**Purpose**: SMS messaging, templates, and notifications

**Tests**:
- `MessagingServiceTest.php` → `Unit/Messaging/MessagingServiceTest.php`
- `MessageResolverTest.php` → `Unit/Messaging/MessageResolverTest.php`
- `TemplateServiceTest.php` → `Unit/Messaging/TemplateServiceTest.php`
- `MessagingIntegrationTest.php` (Feature) → `Feature/Messaging/MessagingIntegrationTest.php`

**Namespaces**: 
- `Tests\Unit\Messaging`
- `Tests\Feature\Messaging`

---

### 7. **Billing Domain** (Feature Tests)
**Purpose**: End-to-end billing cycle workflows

**Tests**:
- `BillingCycleIntegrationTest.php` → `Feature/Billing/BillingCycleIntegrationTest.php`

**Namespace**: `Tests\Feature\Billing`

---

### 8. **Database/Infrastructure** (2 tests)
**Purpose**: Database setup, seeders, and infrastructure

**Tests**:
- `DatabaseSetupTest.php` → `Unit/Database/DatabaseSetupTest.php`
- `MessageTemplateSeederTest.php` → `Unit/Database/Seeders/MessageTemplateSeederTest.php`

**Namespace**: `Tests\Unit\Database` and `Tests\Unit\Database\Seeders`

---

### 9. **Integration Tests**
**Purpose**: Multi-component integration testing

**Tests**:
- `AutoApplyOverpaymentIntegrationTest.php` → `Integration/Meter/AutoApplyOverpaymentIntegrationTest.php`

**Namespace**: `Tests\Integration\Meter`

---

## Migration Steps

### Phase 1: Create Directory Structure
1. Create all new subdirectories
2. Verify directory permissions

### Phase 2: Move and Update Unit Tests
For each test file:
1. Move file to new location
2. Update namespace in the file
3. Update any import statements if needed
4. Run the specific test to verify it still works

### Phase 3: Move and Update Feature Tests
Same process as Phase 2 for Feature tests

### Phase 4: Move and Update Integration Tests
Same process as Phase 2 for Integration tests

### Phase 5: Cleanup
1. Delete old empty directories
2. Update any documentation referencing old paths
3. Run full test suite to verify everything works

### Phase 6: Update CI/CD (if applicable)
Update any CI/CD configuration that references specific test paths

---

## Namespace Mapping

### Unit Tests

| Old Namespace | New Namespace |
|---------------|---------------|
| `Tests\Unit` | `Tests\Unit\Invoice` |
| `Tests\Unit` | `Tests\Unit\Payment` |
| `Tests\Unit` | `Tests\Unit\Bill` |
| `Tests\Unit` | `Tests\Unit\Meter` |
| `Tests\Unit` | `Tests\Unit\Customer` |
| `Tests\Unit\Services` | `Tests\Unit\Messaging` |
| `Tests\Unit\Seeders` | `Tests\Unit\Database\Seeders` |

### Feature Tests

| Old Namespace | New Namespace |
|---------------|---------------|
| `Tests\Feature` | `Tests\Feature\Billing` |
| `Tests\Feature` | `Tests\Feature\Customer` |
| `Tests\Feature` | `Tests\Feature\Messaging` |

### Integration Tests

| Old Namespace | New Namespace |
|---------------|---------------|
| `Tests\Integration` | `Tests\Integration\Meter` |

---

## Benefits of New Structure

### 1. **Clear Organization**
- Tests grouped by business domain
- Easy to find related tests
- New developers can quickly understand test structure

### 2. **Scalability**
- Easy to add new tests in the right place
- Each domain can grow independently
- Clear separation of concerns

### 3. **Better Maintainability**
- Related tests are together
- Easier to refactor domain-specific tests
- Reduced cognitive load when working on a specific domain

### 4. **Improved Test Discovery**
- PHPUnit can discover tests in subdirectories automatically
- Can run domain-specific tests: `php artisan test tests/Unit/Invoice/`
- Better IDE support and navigation

### 5. **Consistency with App Structure**
- Mirrors the `app/` directory structure
- Services in `app/Services/Invoice/` have tests in `tests/Unit/Invoice/`
- Easy to maintain parallel structures

---

## Running Domain-Specific Tests

After reorganization, you can run tests by domain:

```bash
# Run all Invoice tests
php artisan test tests/Unit/Invoice/

# Run all Payment tests
php artisan test tests/Unit/Payment/

# Run all Bill tests
php artisan test tests/Unit/Bill/

# Run all Meter tests
php artisan test tests/Unit/Meter/

# Run all Messaging tests
php artisan test tests/Unit/Messaging/

# Run all Feature tests for Billing
php artisan test tests/Feature/Billing/

# Run all tests (still works)
php artisan test
```

---

## Example Migration

### Before
```php
<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Invoice\InvoiceService;

class InvoiceServiceTest extends TestCase
{
    // ...
}
```

**File Location**: `tests/Unit/InvoiceServiceTest.php`

### After
```php
<?php

namespace Tests\Unit\Invoice;

use Tests\TestCase;
use App\Services\Invoice\InvoiceService;

class InvoiceServiceTest extends TestCase
{
    // ...
}
```

**File Location**: `tests/Unit/Invoice/InvoiceServiceTest.php`

---

## Rollback Plan

If issues arise:
1. Keep old test files until verification complete
2. Can easily revert by moving files back
3. Git history preserves original locations
4. No database or production impact

---

## Testing the Migration

After each phase:
1. Run specific test file: `php artisan test tests/Unit/Invoice/InvoiceServiceTest.php`
2. Run domain tests: `php artisan test tests/Unit/Invoice/`
3. Run all tests: `php artisan test`
4. Verify no errors or warnings

---

## Timeline Estimate

- **Phase 1** (Directory Creation): 5 minutes
- **Phase 2** (Unit Tests - 18 files): 30-45 minutes
- **Phase 3** (Feature Tests - 5 files): 10-15 minutes
- **Phase 4** (Integration Tests - 1 file): 5 minutes
- **Phase 5** (Cleanup & Verification): 10 minutes
- **Total**: ~1-1.5 hours

---

## Best Practices Going Forward

### When Creating New Tests

1. **Identify the business domain** first (Invoice, Payment, Bill, etc.)
2. **Choose test type** (Unit, Feature, Integration)
3. **Place in appropriate directory**: `tests/{Type}/{Domain}/`
4. **Use correct namespace**: `Tests\{Type}\{Domain}`
5. **Follow naming convention**: `{Component}Test.php`

### Examples

- Testing `InvoiceService`? → `tests/Unit/Invoice/InvoiceServiceTest.php`
- Testing `PaymentService`? → `tests/Unit/Payment/PaymentServiceTest.php`
- Testing billing workflow? → `tests/Feature/Billing/BillingWorkflowTest.php`
- Testing meter + payment interaction? → `tests/Integration/Meter/MeterPaymentIntegrationTest.php`

---

## Conclusion

This reorganization will:
- ✅ Make tests easier to find and maintain
- ✅ Improve code navigation in IDEs
- ✅ Clarify test organization for new developers
- ✅ Scale better as the codebase grows
- ✅ Mirror the application structure
- ✅ Enable domain-specific test execution

The structure is consistent, scalable, and follows Laravel/PHPUnit best practices.

