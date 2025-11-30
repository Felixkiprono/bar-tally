# Invoice Service Refactoring Proposal (Revised)

## Current State Analysis

The current `InvoiceService` (974 lines) has two main issues:

1. **Violates Single Responsibility** - Handles generation, accounting, actions, and notifications
2. **Code Duplication** - Meter-based and bulk methods are 90% identical

### Identified Duplications:
- `createInvoice()` vs `createBulkInvoice()` - Only differ in meter_id and notes
- `createJournalEntries()` vs `createBulkJournalEntries()` - Only differ in descriptions
- `processMeterAssignmentBills()` vs `processBulkBills()` - Very similar orchestration flow

## Proposed Refactor: Extract + Consolidate (Meter-Centric)

### Key Insight: Everything is Meter-Centric Now! 🎯

**Decision:** Since all bills are now tied to meters (even connection/disconnection), we can:
1. **Eliminate all "bulk" methods** - they're unused and redundant
2. **Single invoice generation flow** - all invoices have a meter
3. **Consistent overpayment source** - always from meter
4. **Simplified logic** - no conditionals for meter vs non-meter
5. **Better naming** - Service names reflect their specific responsibilities

## Refactored Service Structure

All services under `app/Services/Invoice/` namespace:

### 1. **InvoiceService** (~150 lines) - ✅ **NAME STAYS**
**Responsibility:** Generate invoices from bills (meter-centric orchestration)

**Public Methods:**
- `generateInvoiceFromBills($bills, bool $notifyCustomer = true): Invoice` ✨ **Renamed (clearer)**
- `generateInvoicesBatch(): void` ✨ **Renamed for clarity**

**Protected Methods:**
- `processBills($bills, bool $notifyCustomer): Invoice` ✨ **CONSOLIDATED**
- `buildInvoiceFromBills(...)` ✨ **Renamed for clarity**

**Removed Methods:**
- ~~`generateInvoiceForMeterReading()`~~ → renamed to `generateInvoiceFromBills()`
- ~~`generateInvoice()`~~ → renamed to `generateInvoicesBatch()`
- ~~`generateInvoiceForBulkBills()`~~ → DELETED (unused)
- ~~`processMeterAssignmentBills()`~~ → merged into `processBills()`
- ~~`processBulkBills()`~~ → DELETED (duplicate)
- ~~`createInvoice()`~~ → renamed to `buildInvoiceFromBills()`
- ~~`createBulkInvoice()`~~ → DELETED (duplicate)

**Dependencies:**
- InvoiceAccountingService (for journal entries)
- InvoiceNotificationService (for customer notifications)
- InvoiceRepository (for queries)
- MeterFinancialService (for balance recalculation)

---

### 2. **InvoiceAccountingService** (~120 lines) - ✅ **NAME STAYS**
**Responsibility:** Record accounting transactions (journal entries) for invoices

**Methods:**
- `recordInvoiceEntries(Invoice $invoice, $bills): void` ✨ **Renamed for clarity**
- `creditRevenueAccounts($bills, float $amount, Invoice $invoice): void` ✨ **Renamed**
- `reverseInvoiceEntries(Invoice $invoice, float $amount, string $reason): void` ✨ **Renamed**
- `writeJournalEntry(...): void` (protected) ✨ **Renamed**

**Removed Methods:**
- ~~`createJournalEntries()`~~ → renamed to `recordInvoiceEntries()`
- ~~`createBulkJournalEntries()`~~ → DELETED (duplicate)
- ~~`creditBillAccounts()`~~ → renamed to `creditRevenueAccounts()`
- ~~`reverseBillAccounts()`~~ → renamed to `reverseInvoiceEntries()`
- ~~`createJournalEntry()`~~ → renamed to `writeJournalEntry()`

**Dependencies:**
- Account model
- Journal model

**Key Logic:**
- Double-entry bookkeeping
- AR debits, revenue credits, prepayment handling
- Reversal transactions

---

### 3. **InvoiceActionService** (~200 lines) - ✅ **NAME STAYS**
**Responsibility:** Execute invoice lifecycle operations (payment, correction, reversal, cancellation)

**Methods:**
- `applyPayment(Invoice $invoice, array $paymentData, bool $notifyCustomer = true): void` ✨ **Renamed**
- `adjustAmount(Invoice $invoice, float $correctAmount, string $reason): Invoice` ✨ **Renamed**
- `reverseInvoice(Invoice $invoice, string $reason, string $notes, bool $notifyCustomer = true): array` ✅ **Keeps prefix**
- `cancelInvoice(Invoice $invoice, string $reason, string $notes = ''): void` ✅ **Keeps prefix**

**Removed Methods:**
- ~~`processInvoicePayment()`~~ → renamed to `applyPayment()`
- ~~`correctInvoice()`~~ → renamed to `adjustAmount()`
- ~~Old InvoiceActionService (deprecated)~~ → replaced by this

**Dependencies:**
- InvoiceAccountingService (for accounting reversals)
- PaymentService (for payment processing)
- InvoiceNotificationService (for confirmations)

**Key Logic:**
- State validation before operations
- Amount adjustments with journal coordination
- Reversal invoice creation
- Transaction management

---

### 4. **InvoiceNotificationService** (~80 lines) - ✅ **NAME STAYS**
**Responsibility:** Send invoice-related SMS messages to customers

**Methods:**
- `sendInvoiceCreated(User $customer, Invoice $invoice, ?string $customMessage = null): void` ✨ **Renamed**
- `sendPaymentConfirmation(User $customer, Invoice $invoice, Payment $payment): void` ✨ **Renamed**
- `sendCustomInvoiceMessage(Invoice $invoice, string $message): void` ✨ **Renamed**

**Removed Methods:**
- ~~`notifyCustomer()`~~ → split into specific methods
- ~~`notifyInvoiceCreated()`~~ → renamed to `sendInvoiceCreated()`
- ~~`notifyInvoicePayment()`~~ → renamed to `sendPaymentConfirmation()`

**Dependencies:**
- MessageTemplate model
- MessageResolver
- SendSmsJob

**Key Logic:**
- Template resolution and tag replacement
- Async SMS dispatch via queue
- Custom vs template messages
- Error logging

---

### 5. **InvoiceRepository** (~80 lines) - ✅ **NAME STAYS**
**Responsibility:** Data access and query operations for invoices

**Methods:**
- `findOpenByCustomer(int $customerId): Collection` ✨ **Renamed for clarity**
- `findByNumber(string $invoiceNumber): ?Invoice`
- `findByCustomer(int $customerId, array $filters = []): Collection`
- `generateInvoiceNumber(): string` (static)
- `attachBills(Invoice $invoice, $bills): void` ✨ **Renamed**

**Removed Methods:**
- ~~`openInvoices()`~~ → renamed to `findOpenByCustomer()`
- ~~`linkBillsToInvoice()`~~ → renamed to `attachBills()`

**Dependencies:**
- Invoice model
- InvoiceBill model (pivot)

**Key Logic:**
- Query operations (no business logic)
- Invoice number generation
- Relationship management

---

## Complete Naming Reference

### Service Names - ✅ ALL STAY THE SAME

| Service | Status | Why Keeping? |
|---------|--------|--------------|
| `InvoiceService` | ✅ Unchanged | Avoid breaking changes |
| `InvoiceAccountingService` | ✅ Unchanged | Avoid breaking changes |
| `InvoiceActionService` | ✅ Unchanged | Avoid breaking changes |
| `InvoiceNotificationService` | ✅ Unchanged | Avoid breaking changes |
| `InvoiceRepository` | ✅ Unchanged | Already perfect |

**Decision:** Keep all class names to minimize breaking changes. Focus on improving method names only.

---

### Method Name Changes (by Service)

#### InvoiceService
| Old Name | New Name | Reason |
|----------|----------|--------|
| `generateInvoiceForMeterReading()` | `generateInvoiceFromBills()` | Clearer - describes input |
| `generateInvoice()` | `generateInvoicesBatch()` | Plural form clearer |
| `processMeterAssignmentBills()` | `processBills()` | Consolidated + simpler |
| `createInvoice()` | `buildInvoiceFromBills()` | More descriptive |
| `openInvoices()` | (moved to Repository) | Data access logic |
| ~~`generateInvoiceForBulkBills()`~~ | **DELETED** | Unused |
| ~~`processBulkBills()`~~ | **DELETED** | Duplicate |
| ~~`createBulkInvoice()`~~ | **DELETED** | Duplicate |

#### InvoiceAccountingService
| Old Name | New Name | Reason |
|----------|----------|--------|
| `createJournalEntries()` | `recordInvoiceEntries()` | Accounting terminology |
| `creditBillAccounts()` | `creditRevenueAccounts()` | More accurate |
| `reverseBillAccounts()` | `reverseInvoiceEntries()` | More accurate |
| `createJournalEntry()` | `writeJournalEntry()` | Action-oriented |
| ~~`createBulkJournalEntries()`~~ | **DELETED** | Duplicate |

#### InvoiceActionService
| Old Name | New Name | Reason |
|----------|----------|--------|
| `processInvoicePayment()` | `applyPayment()` | Shorter, clearer |
| `correctInvoice()` | `adjustAmount()` | More specific |
| `reverseInvoice()` | `reverseInvoice()` | ✅ Already good |
| `cancelInvoice()` | `cancelInvoice()` | ✅ Already good |
| `sendInvoiceSms()` | (moved to NotificationService) | Belongs with messaging |

#### InvoiceNotificationService
| Old Name | New Name | Reason |
|----------|----------|--------|
| `notifyCustomer()` | `sendInvoiceCreated()` | More specific |
| `notifyInvoiceCreated()` | `sendInvoiceCreated()` | "Send" more direct |
| `notifyInvoicePayment()` | `sendPaymentConfirmation()` | More descriptive |
| (new) | `sendCustomInvoiceMessage()` | For custom messages |

#### InvoiceRepository
| Old Name | New Name | Reason |
|----------|----------|--------|
| `openInvoices()` | `findOpenByCustomer()` | Repository naming pattern |
| `linkBillsToInvoice()` | `attachBills()` | Clearer relationship verb |

### Verb Convention Guidelines

To maintain consistency, we use specific verbs for specific actions:

- **`generate*()`** - Create from scratch (bills → invoice)
- **`build*()`** - Construct entity instance
- **`record*()`** - Write accounting entries
- **`write*()`** - Persist to database (low-level)
- **`send*()`** - Dispatch messages/notifications
- **`apply*()`** - Execute an action that changes state
- **`adjust*()`** - Modify amounts/values
- **`reverse*()`** - Create opposite/cancellation entries
- **`cancel*()`** - Mark as cancelled
- **`find*()`** - Query/retrieve data
- **`attach*()`** - Create relationships

---

## Migration Strategy (Revised)

### Phase 1: Extract Supporting Services (Non-Breaking)
1. Create `InvoiceAccountingService` (extracts accounting methods from InvoiceService)
2. Create `InvoiceNotificationService` (extracts notification methods from InvoiceService)
3. Create `InvoiceRepository` (extracts query methods from InvoiceService)
4. Create new `InvoiceActionService` (under `Invoice/` namespace with new methods)
5. Test all new services in isolation
6. **Old InvoiceService continues working** - no breaking changes yet

### Phase 2: Refactor InvoiceService (Minimal Breaking Changes)
1. **Consolidate** duplicate methods in `InvoiceService`:
   - Delete all "bulk" methods (unused: `generateInvoiceForBulkBills`, `processBulkBills`, `createBulkInvoice`)
   - Merge `processMeterAssignmentBills()` logic into single `processBills()`
   - Rename methods for clarity (e.g., `generateInvoiceForMeterReading` → `generateInvoiceFromBills`)
2. Update `InvoiceService` to use new supporting services
3. **Public method signatures change slightly** but behavior stays same

### Phase 3: Update Call Sites
1. Update `GenerateInvoicesJob` → call `generateInvoicesBatch()` (renamed)
2. Update `BillCreationService` → call `generateInvoiceFromBills()` (renamed)
3. Update Filament pages (`ViewInvoice.php`) → use new `InvoiceActionService::applyPayment()` etc.
4. Update `InvoiceTableHelper` → use new `InvoiceActionService` methods
5. Update any direct calls to removed bulk methods (if any exist)
6. Run full test suite

### Phase 4: Final Cleanup
1. Delete old deprecated `InvoiceActionService` (at `app/Services/`)
2. Update documentation with new method names
3. Update any remaining imports
4. Final test run and deployment

---

## Consolidation Examples (Meter-Centric)

### Before: Duplicate Methods (974 lines total)
```php
// processMeterAssignmentBills() - 99 lines
protected function processMeterAssignmentBills($bills, bool $notifyCustomer = true): void
{
    $meterAssignment = $bills->first()->meterAssignment;
    $meter = $meterAssignment->meter;
    $customer = $meterAssignment->customer;
    $overpayment = (float)$meter->overpayment; // From meter
    // ... 90 lines of logic
}

// processBulkBills() - 73 lines (NEVER CALLED!)
protected function processBulkBills($bills): Invoice
{
    $customer = $bills->first()->customer;
    $overpayment = (float)$customer->overpayment; // From customer
    // ... 68 lines of DUPLICATE logic
}

// createInvoice() - 27 lines
protected function createInvoice($meterAssignment, $bills, ...): Invoice
{
    return Invoice::create([
        'meter_id' => $meterAssignment->meter_id,
        'customer_id' => $meterAssignment->customer_id,
        // ...
    ]);
}

// createBulkInvoice() - 27 lines (DUPLICATE!)
protected function createBulkInvoice($customer, $bills, ...): Invoice
{
    return Invoice::create([
        'meter_id' => null, // The only difference!
        'customer_id' => $customer->id,
        // ... exact same logic
    ]);
}

// createJournalEntries() - 67 lines
protected function createJournalEntries($invoice, $meterAssignment, $bills, ...): void
{
    // ... logic with $meterAssignment
}

// createBulkJournalEntries() - 68 lines (DUPLICATE!)
protected function createBulkJournalEntries($invoice, $bills, ...): void
{
    // ... exact same logic, no $meterAssignment
}
```

### After: Single Meter-Centric Flow with Clear Naming (~450 lines total)
```php
// InvoiceService.php - Single unified method (refactored)
protected function processBills($bills, bool $notifyCustomer = true): Invoice
{
    $firstBill = $bills->first();
    $meterAssignment = $firstBill->meterAssignment; // Always exists!
    $meter = $meterAssignment->meter;
    $customer = $meterAssignment->customer;
    
    // Calculate balance brought forward
    $openInvoices = $this->repository->findOpenByCustomer($customer->id);
    $balanceBroughtForward = 0;
    
    foreach ($openInvoices as $openInvoice) {
        $balanceBroughtForward += $openInvoice->balance;
        $openInvoice->update(['state' => 'closed', 'status' => 'Cleared']);
    }
    
    // Get overpayment from meter (consistent)
    $overpayment = (float)$meter->overpayment ?? 0;
    $billAmount = $bills->sum('total_amount');
    $totalAmount = $balanceBroughtForward + $billAmount;
    $creditToApply = min($overpayment, $totalAmount);
    
    // Build invoice (always has meter_id)
    $invoice = $this->buildInvoiceFromBills($meterAssignment, $bills, $balanceBroughtForward, $billAmount, $creditToApply);
    
    // Record accounting entries
    $this->accountingService->recordInvoiceEntries($invoice, $bills);
    
    // Attach bills to invoice
    $this->repository->attachBills($invoice, $bills);
    
    // Recalculate meter finances
    $this->meterFinancialService->recalculateCustomerMeters($customer->id);
    
    // Send notification if requested
    if ($notifyCustomer) {
        $this->notificationService->sendInvoiceCreated($customer, $invoice);
    }
    
    return $invoice;
}

// Single invoice building method
protected function buildInvoiceFromBills(
    MeterAssignment $meterAssignment, 
    $bills, 
    float $balanceBroughtForward, 
    float $billAmount, 
    float $paidAmount
): Invoice {
    $totalAmount = $balanceBroughtForward + $billAmount;
    $remainingAmount = $totalAmount - $paidAmount;
    $meter = $meterAssignment->meter;
    
    return Invoice::create([
        'invoice_number' => $this->repository->generateInvoiceNumber(),
        'customer_id' => $meterAssignment->customer_id,
        'meter_id' => $meterAssignment->meter_id, // Always present!
        'tenant_id' => $meterAssignment->tenant_id,
        'invoice_date' => now(),
        'due_date' => now()->addDays($this->getInvoiceDueDate()),
        'balance_brought_forward' => $balanceBroughtForward,
        'amount' => $billAmount,
        'paid_amount' => $paidAmount,
        'status' => $remainingAmount > 0 ? 'Not Paid' : 'Fully Paid',
        'state' => $remainingAmount > 0 ? 'open' : 'closed',
        'notes' => "Invoice for meter {$meter->meter_number}" 
            . ($balanceBroughtForward > 0 ? " - Balance b/f: KES " . number_format($balanceBroughtForward, 2) : ''),
    ]);
}
```

**Usage Example:**
```php
// Old way
$invoiceService = app(\App\Services\Invoice\InvoiceService::class);
$invoiceService->generateInvoiceForMeterReading($bills, true);

// New way - clearer method names, same class
$invoiceService = app(\App\Services\Invoice\InvoiceService::class);
$invoice = $invoiceService->generateInvoiceFromBills($bills, notifyCustomer: true);
```

**Result:** 
- ❌ DELETE: `processBulkBills()` (73 lines)
- ❌ DELETE: `createBulkInvoice()` (27 lines)
- ❌ DELETE: `createBulkJournalEntries()` (68 lines)  
- ❌ DELETE: `generateInvoiceForBulkBills()` (24 lines)
- ✅ KEEP: Single meter-centric flow
- **Savings: ~520 lines reduced to ~450 lines (70 lines of pure duplication removed)**

---

## Benefits (Meter-Centric Refactor)

1. **Single Responsibility** - Each service has one clear purpose
2. **DRY Principle** - Eliminate 200+ lines of duplicate "bulk" code
3. **Consistency** - One flow for all invoices (no meter vs bulk conditionals)
4. **Simplified Logic** - No nullable meter_id handling
5. **Single Source of Truth** - Overpayment always from meter
6. **Testability** - Smaller, focused services with single paths
7. **Maintainability** - One invoice creation path to maintain
8. **Clarity** - Meter-centric domain model matches business reality
9. **Performance** - Smaller classes, no conditionals
10. **Reduced Line Count** - From 974 lines to ~600 lines total across all services

### Consolidation Summary:
- **Before:** 974 lines in InvoiceService (with unused bulk methods)
- **After:** ~600 lines across 5 focused services
- **Savings:** 374 lines eliminated + better structure
- **Methods Deleted:** 4 unused/duplicate bulk methods (~192 lines of dead code)

---

## Call Site Impact

### Current Call Sites:
- `GenerateInvoicesJob` → Will use `InvoiceGenerationService`
- `BillCreationService` → Will use `InvoiceGenerationService`
- `ViewInvoice.php` (Filament) → Will use `InvoiceActionService`
- `InvoiceTableHelper.php` → Will use `InvoiceActionService`
- Various commands/scripts → Will use appropriate service

### Estimated Changes:
- ~10-15 files will need service injection updates
- No public API signature changes (methods remain the same)
- Service resolution via `app()` helper or constructor injection

---

## File Structure (Keeping Same Names)

```
app/Services/Invoice/
├── InvoiceService.php                    # Main billing (REFACTORED, ~150 lines, was 974)
├── InvoiceAccountingService.php          # NEW - Accounting/journal entries (~120 lines)
├── InvoiceActionService.php              # NEW - Payment/correction/reversal/cancel (~200 lines)
├── InvoiceNotificationService.php        # NEW - SMS notifications (~80 lines)
└── InvoiceRepository.php                 # NEW - Data access (~80 lines)
```

**What's happening:**
- ✅ `InvoiceService.php` - Slimmed from 974 to ~150 lines, removes duplicates
- ✅ New supporting services extracted from `InvoiceService`
- ❌ Old `InvoiceActionService.php` (at `app/Services/`) - Deprecated, will be deleted
- ✅ New `InvoiceActionService.php` (at `app/Services/Invoice/`) - Fresh implementation

---

## Potential Issues & Mitigations

### Issue 1: Circular Dependencies
**Risk:** Services might need each other creating circular references
**Mitigation:** 
- Use interfaces where needed
- Generation service depends on others, not vice versa
- Accounting/Notification are leaf services (no dependencies on Invoice services)

### Issue 2: Transaction Management
**Risk:** DB transactions span multiple services
**Mitigation:**
- Keep transaction boundaries in Generation/Action services
- Supporting services (Accounting, Notification) don't manage transactions
- Pass invoice as parameter, modify in-place, let caller commit

### Issue 3: Breaking Changes
**Risk:** Existing code breaks during migration
**Mitigation:**
- Phased approach keeps old service until all call sites updated
- Comprehensive test coverage before switching
- Feature flag to toggle between old/new implementation

---

## Alternative: Keep as Monolith with Better Organization

If splitting is too disruptive, we could instead:
1. Group methods by responsibility with clear comment sections
2. Extract accounting methods to a trait
3. Extract notification to a separate concern
4. Keep all in one file but better organized (~1000 lines is manageable if well-structured)

**Recommendation:** Proceed with the meter-centric split. The service has grown enough that separation will significantly improve maintainability, and eliminating the bulk methods will simplify the codebase.

---

## Meter-Centric Decision Tree

### Question: "Do we need bulk invoices?"
**Answer: NO!** All bills are now tied to meters:
- ✅ Connection bills → tied to meter
- ✅ Reading bills → tied to meter (from meter readings)
- ✅ Disconnection bills → tied to meter
- ✅ Manual bills → tied to meter
- ✅ Penalty bills → tied to meter

### Question: "What if a bill has no meter_assignment_id?"
**Answer:** That's a data integrity issue! All bills MUST have a meter_assignment_id in the meter-centric model. If found:
1. Log error
2. Skip the bill
3. Flag for manual review

### Question: "Where does overpayment come from?"
**Answer:** ALWAYS from the meter! Customer-level overpayment is an aggregate calculated from their meters.
```php
// ✅ CORRECT - Meter-centric
$overpayment = (float)$meter->overpayment ?? 0;

// ❌ WRONG - Customer-level (legacy)
$overpayment = (float)$customer->overpayment ?? 0;
```

### Question: "Can invoices have meter_id = null?"
**Answer:** NO! Not in the meter-centric model. The `meter_id` column should be:
1. Required (not nullable) going forward
2. Any existing null values are legacy data to be fixed
3. Invoice ALWAYS belongs to exactly one meter

---

## Migration Checklist

Before implementing this refactor:
- [ ] Verify no bills exist with null `meter_assignment_id`
- [ ] Verify no invoices exist with null `meter_id` (or plan to fix them)
- [ ] Confirm `generateInvoiceForBulkBills()` is never called in production
- [ ] Confirm all bill types (connection, reading, disconnection) have meter assignments
- [ ] Update any scripts/imports that create bills to always include meter_assignment_id

---

## Next Steps

1. **Review this proposal** - Confirm meter-centric approach is correct
2. **Verify data integrity** - Check for null meter_assignment_id in bills table
3. **Start with Phase 1** - Extract supporting services first
4. **Phase 2** - Consolidate InvoiceService (delete bulk methods)
5. **Phase 3** - Update call sites
6. **Phase 4** - Consider making meter_id non-nullable in database

**Ready to proceed?**

---

## Summary: What Changes?

### Class Names: ✅ ALL STAY THE SAME
**Decision:** Keep all service class names unchanged to minimize breaking changes across the codebase.

| Service | Status | Size |
|---------|--------|------|
| `InvoiceService` | ✅ Unchanged (refactored internally) | 974 → ~150 lines |
| `InvoiceAccountingService` | ✅ New (extracted) | ~120 lines |
| `InvoiceActionService` | ✅ New (replaces deprecated) | ~200 lines |
| `InvoiceNotificationService` | ✅ New (extracted) | ~80 lines |
| `InvoiceRepository` | ✅ New (extracted) | ~80 lines |

---

### Method Names: Action-Oriented Improvements
| What It Does | Old Name | New Name | Benefit |
|--------------|----------|----------|---------|
| Generate invoice from bills | `generateInvoiceForMeterReading()` | `generateInvoiceFromBills()` | Clearer input |
| Generate all pending | `generateInvoice()` | `generateInvoicesBatch()` | Plural form |
| Apply payment | `processInvoicePayment()` | `applyPayment()` | Shorter, clearer |
| Fix amount error | `correctInvoice()` | `adjustAmount()` | More specific |
| Record accounting | `createJournalEntries()` | `recordInvoiceEntries()` | Accounting term |
| Send creation SMS | `notifyCustomer()` | `sendInvoiceCreated()` | More specific |
| Query open invoices | `openInvoices()` | `findOpenByCustomer()` | Repository pattern |

---

### Methods Deleted: ~200 Lines of Duplicate Code
- ❌ `generateInvoiceForBulkBills()` - Unused
- ❌ `processBulkBills()` - Duplicate of processMeterAssignmentBills
- ❌ `createBulkInvoice()` - Duplicate of createInvoice
- ❌ `createBulkJournalEntries()` - Duplicate of createJournalEntries

---

### The Naming Pattern
✅ **Use specific verbs:** `generate`, `build`, `record`, `send`, `apply`, `adjust`, `find`, `attach`  
❌ **Avoid generic verbs:** `process`, `create`, `notify` when more specific verbs exist

**Consistency:**
- Accounting: `record*()`, `write*()`
- Messaging: `send*()`
- Queries: `find*()`
- Operations: `apply*()`, `adjust*()`, `reverse*()`, `cancel*()`

