# 🗺️ METER-CENTRIC BILLING REFACTOR - IMPLEMENTATION PLAN

## Executive Summary

**Project Goal:** Refactor the billing system to make the Meter the central entity for financial transactions, while maintaining Customer as the primary navigation entry point.

**Current State:** Bills, invoices, payments, and balances are tracked at the customer level, causing ambiguity when customers have multiple meters.

**Target State:** Each meter maintains its own balance, overpayment, and transaction history. Customers remain the primary UI navigation point, but all financial operations happen at the meter level.

**Timeline:** 6-8 weeks
**Risk Level:** Medium (mitigated by phased approach with backward compatibility)

---

## Core Architecture Principles

### Dual-Center Model
- **Customer** = Navigation/UI entry point (how users find and interact with the system)
- **Meter** = Transactional core (where billing operations actually occur)

### Key Changes
1. Each meter has its own `balance` and `overpayment`
2. Every invoice must have a `meter_id`
3. Every payment must have a `meter_id`
4. Customer-level balances become computed aggregates
5. All financial services operate on meters, not customers

---

## 📅 PHASE 1: FOUNDATION (Week 1)
**Goal:** Prepare infrastructure without breaking existing functionality

### 1.1 Database Migrations

#### Migration 1: Add Financial Fields to Meters
```bash
php artisan make:migration add_financial_fields_to_meters_table
```

**Fields to add:**
```php
Schema::table('meters', function (Blueprint $table) {
    $table->decimal('balance', 10, 2)->default(0)->after('status');
    $table->decimal('overpayment', 10, 2)->default(0)->after('balance');
    $table->date('last_invoice_date')->nullable()->after('overpayment');
    $table->decimal('total_billed', 10, 2)->default(0)->after('last_invoice_date');
    $table->decimal('total_paid', 10, 2)->default(0)->after('total_billed');
});
```

#### Migration 2: Add Performance Indexes
```bash
php artisan make:migration add_indexes_for_meter_financials
```

**Indexes to add:**
```php
Schema::table('invoices', function (Blueprint $table) {
    $table->index('meter_id');
    $table->index(['meter_id', 'state']);
    $table->index(['meter_id', 'invoice_date']);
});

Schema::table('payments', function (Blueprint $table) {
    $table->index('meter_id');
    $table->index(['meter_id', 'date']);
});

Schema::table('journals', function (Blueprint $table) {
    $table->index(['customer_id', 'date']);
});
```

### 1.2 Testing
- [ ] Run migrations in local environment
- [ ] Verify schema changes with `php artisan migrate:status`
- [ ] Check existing data integrity
- [ ] Verify no foreign key constraint issues

### 1.3 Deliverable
✅ Database schema ready for meter financial tracking

---

## 📅 PHASE 2: DATA MIGRATION (Week 1-2)
**Goal:** Populate meter financial fields from existing customer data

### 2.1 Backfill Payment meter_id (MUST RUN FIRST)

```bash
php artisan make:command BackfillPaymentMeterIds
```

**File:** `app/Console/Commands/BackfillPaymentMeterIds.php`

**⚠️ CRITICAL:** This MUST run before meter balance calculation because we need `payment.meter_id` populated to accurately sum payments per meter.


**Logic:**
```php
// Step 1: Payments with invoice_id
Payment::whereNull('meter_id')
    ->whereNotNull('invoice_id')
    ->chunkById(500, function ($payments) {
        foreach ($payments as $payment) {
            $payment->meter_id = $payment->invoice->meter_id;
            $payment->save();
        }
    });

// Step 2: Payments without invoice_id (advance/general payments)
Payment::whereNull('meter_id')
    ->whereNull('invoice_id')
    ->chunkById(500, function ($payments) {
        foreach ($payments as $payment) {
            $customer = $payment->customer;
            $activeMeter = $customer->meterAssignments()
                ->where('is_active', true)
                ->first();
            
            if ($activeMeter && $customer->active_meters_count == 1) {
                $payment->meter_id = $activeMeter->meter_id;
                $payment->save();
            } else {
                // Assign to first available meter
                $firstMeter = $customer->meterAssignments()->first();
                if ($firstMeter) {
                    $payment->meter_id = $firstMeter->meter_id;
                    $payment->save();
                    Log::channel('migration')->info('Payment assigned to first meter', [
                        'payment_id' => $payment->id,
                        'customer_id' => $customer->id,
                        'meter_id' => $firstMeter->meter_id
                    ]);
                } else {
                    Log::channel('migration')->warning('Payment has no meters to assign to', [
                        'payment_id' => $payment->id,
                        'customer_id' => $customer->id
                    ]);
                }
            }
        }
    });
```

### 2.2 Calculate Meter Balances from Transactions (RUNS AFTER 2.1)

```bash
php artisan make:command MigrateCustomerBalancesToMeters
```

**File:** `app/Console/Commands/MigrateCustomerBalancesToMeters.php`

**⚠️ PREREQUISITE:** Section 2.1 must complete successfully first (all payments need meter_id)

**Logic Flow:**
```php
1. Verify prerequisite: Check that all payments have meter_id
   - If any payments missing meter_id, abort with error
2. Create backup table: customer_balances_backup (for verification only)
3. Store original values: customer_id, balance, overpayment, migrated_at
4. For each meter with an assignment:
   a. Calculate meter balance from SOURCE OF TRUTH:
      - Sum all invoices for this meter_id
      - Sum all payments for this meter_id
      - Balance = Invoices - Payments
   b. If Balance < 0 (overpayment scenario):
      - Overpayment = abs(Balance)
      - Balance = 0
   c. If Balance >= 0 (normal scenario):
      - Balance = Balance
      - Overpayment = 0
5. Validate: Sum of all meter balances ≈ Sum of customer balances (within tolerance)
6. Generate migration report with discrepancies (CSV + Log)
```

**Migration Strategy: CALCULATE FROM TRANSACTIONS (SINGLE SOURCE OF TRUTH)**

This approach is based on how the system currently calculates customer statements (ViewCustomerStatement.php lines 71-82):

```php
// PREREQUISITE CHECK
$paymentsWithoutMeter = Payment::whereNull('meter_id')->count();
if ($paymentsWithoutMeter > 0) {
    throw new \Exception(
        "Cannot calculate meter balances: {$paymentsWithoutMeter} payments still missing meter_id. " .
        "Run BackfillPaymentMeterIds command first."
    );
}

foreach (Meter::whereHas('assignments')->get() as $meter) {
    // Sum all invoices for this meter
    $totalInvoiced = Invoice::where('meter_id', $meter->id)
        ->sum('total_amount');
    
    // Sum all payments for this meter (NOW ACCURATE because meter_id is populated)
    $totalPaid = Payment::where('meter_id', $meter->id)
        ->sum('amount');
    
    // Calculate balance (invoices - payments)
    $calculatedBalance = $totalInvoiced - $totalPaid;
    
    // Determine balance and overpayment
    if ($calculatedBalance < 0) {
        // Payments exceed invoices = overpayment situation
        $meter->balance = 0;
        $meter->overpayment = abs($calculatedBalance);
    } else {
        // Normal balance situation
        $meter->balance = $calculatedBalance;
        $meter->overpayment = 0;
    }
    
    // Set lifetime totals
    $meter->total_billed = $totalInvoiced;
    $meter->total_paid = $totalPaid;
    $meter->last_invoice_date = Invoice::where('meter_id', $meter->id)
        ->max('invoice_date');
    
    $meter->save();
    
    Log::info('Meter balance calculated', [
        'meter_id' => $meter->id,
        'meter_number' => $meter->meter_number,
        'total_invoiced' => $totalInvoiced,
        'total_paid' => $totalPaid,
        'balance' => $meter->balance,
        'overpayment' => $meter->overpayment
    ]);
}
```

**Why This Approach is Better:**

1. ✅ **Single Source of Truth** - Calculated from actual transactions (invoices & payments)
2. ✅ **No Proportional Split Guessing** - Each meter's balance is derived from its own transactions
3. ✅ **Handles All Scenarios** - Works for customers with 1 or 100 meters
4. ✅ **Self-Validating** - Can compare against customer balance fields for verification
5. ✅ **Idempotent** - Can run multiple times safely with same result
6. ✅ **Audit Trail** - Every meter balance can be traced back to invoices/payments

**Validation Strategy:**

```php
// After migration, validate totals
$calculatedCustomerBalances = User::customers()->get()->map(function($customer) {
    return [
        'customer_id' => $customer->id,
        'old_balance' => $customer->balance,
        'old_overpayment' => $customer->overpayment,
        'new_balance' => $customer->meterAssignments()
            ->where('is_active', true)
            ->with('meter')
            ->get()
            ->sum(fn($a) => $a->meter->balance),
        'new_overpayment' => $customer->meterAssignments()
            ->where('is_active', true)
            ->with('meter')
            ->get()
            ->sum(fn($a) => $a->meter->overpayment),
    ];
});

// Check for discrepancies
$discrepancies = $calculatedCustomerBalances->filter(function($data) {
    $balanceDiff = abs($data['old_balance'] - $data['new_balance']);
    $overpaymentDiff = abs($data['old_overpayment'] - $data['new_overpayment']);
    
    // Allow 1 KES tolerance for rounding
    return $balanceDiff > 1.00 || $overpaymentDiff > 1.00;
});

if ($discrepancies->count() > 0) {
    Log::warning('Balance discrepancies found', [
        'count' => $discrepancies->count(),
        'discrepancies' => $discrepancies->toArray()
    ]);
}
```

**Edge Cases Handled:**

1. **Meter without invoices** - Balance = 0, Overpayment = 0
2. **Meter with only advance payments** - Balance = 0, Overpayment = sum(payments)
3. **Meter with unpaid invoices** - Balance = invoices - payments, Overpayment = 0
4. **Disconnected meters** - Historical data preserved, balance calculated from history
5. **Customer balance field mismatch** - Transaction-based calculation is authoritative

### 2.3 Validation Command

```bash
php artisan make:command ValidateMeterFinancialData
```

**File:** `app/Console/Commands/ValidateMeterFinancialData.php`

**Validation Checks:**
```php
// Check 1: Balance totals match
$customerBalanceSum = User::customers()->sum('balance');
$meterBalanceSum = Meter::sum('balance');
$difference = abs($customerBalanceSum - $meterBalanceSum);

if ($difference > 1.00) { // Allow 1 KES rounding difference
    $this->error("Balance mismatch: Customer total = {$customerBalanceSum}, Meter total = {$meterBalanceSum}");
}

// Check 2: Overpayment totals match
$customerOverpaymentSum = User::customers()->sum('overpayment');
$meterOverpaymentSum = Meter::sum('overpayment');
$overpaymentDiff = abs($customerOverpaymentSum - $meterOverpaymentSum);

if ($overpaymentDiff > 1.00) {
    $this->error("Overpayment mismatch");
}

// Check 3: All invoices have meter_id
$invoicesWithoutMeter = Invoice::whereNull('meter_id')->count();
if ($invoicesWithoutMeter > 0) {
    $this->error("{$invoicesWithoutMeter} invoices missing meter_id");
}

// Check 4: All payments have meter_id
$paymentsWithoutMeter = Payment::whereNull('meter_id')->count();
if ($paymentsWithoutMeter > 0) {
    $this->error("{$paymentsWithoutMeter} payments missing meter_id");
}

// Check 5: No negative balances (except valid reversals)
$negativeBalances = Meter::where('balance', '<', 0)->count();
if ($negativeBalances > 0) {
    $this->warn("{$negativeBalances} meters have negative balances");
}

// Generate detailed report
$this->generateValidationReport();
```

### 2.4 Testing & Execution Order

**⚠️ CRITICAL EXECUTION ORDER:**
```bash
# Step 1: Backfill payment meter_ids
php artisan backfill:payment-meter-ids

# Step 2: Verify all payments have meter_id
php artisan validate:meter-financial-data --check=payments

# Step 3: Calculate meter balances from transactions
php artisan migrate:customer-balances-to-meters

# Step 4: Full validation
php artisan validate:meter-financial-data --full
```

**Testing checklist:**
- [ ] Test Step 1 (backfill) with sample data (10-20 customers)
- [ ] Verify all payments get meter_id assigned
- [ ] Test Step 3 (calculate) with sample data
- [ ] Compare calculated balances with customer balances
- [ ] Test with production copy in staging environment
- [ ] Review flagged accounts manually
- [ ] Verify validation checks pass
- [ ] Get sign-off on migration results

### 2.5 Deliverable
✅ All historical data migrated to meters with validation report

---

## 📅 PHASE 3: SERVICE LAYER (Week 2-3)
**Goal:** Create new meter-centric services while maintaining backward compatibility

### 3.1 Create MeterFinancialService

```bash
# Create the service file manually
```

**File:** `app/Services/MeterFinancialService.php`

**Class Structure:**
```php
<?php

namespace App\Services;

use App\Models\Meter;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MeterFinancialService - Lean service for meter financial operations
 * 
 * This service only handles complex operations that require business logic.
 * Simple queries (balance, overpayment) should access the model directly.
 */
class MeterFinancialService
{
    /**
     * Recalculate meter balance and overpayment from transaction history
     * This is the SOURCE OF TRUTH calculation
     */
    public function recalculateMeterBalance(int $meterId): void
    {
        $meter = Meter::findOrFail($meterId);
        
        // Sum all invoices for this meter
        $totalInvoiced = Invoice::where('meter_id', $meterId)->sum('total_amount');
        
        // Sum all payments for this meter
        $totalPaid = Payment::where('meter_id', $meterId)->sum('amount');
        
        // Calculate balance (invoices - payments)
        $calculatedBalance = $totalInvoiced - $totalPaid;
        
        // Determine balance and overpayment
        if ($calculatedBalance < 0) {
            // Payments exceed invoices = overpayment
            $meter->balance = 0;
            $meter->overpayment = abs($calculatedBalance);
        } else {
            // Normal balance
            $meter->balance = $calculatedBalance;
            $meter->overpayment = 0;
        }
        
        // Update lifetime totals
        $meter->total_billed = $totalInvoiced;
        $meter->total_paid = $totalPaid;
        $meter->last_invoice_date = Invoice::where('meter_id', $meterId)
            ->max('invoice_date');
        
        $meter->save();
        
        Log::info('Meter balance recalculated', [
            'meter_id' => $meterId,
            'balance' => $meter->balance,
            'overpayment' => $meter->overpayment
        ]);
    }
    
    /**
     * Recalculate balances for all meters belonging to a customer
     * Also updates customer balance/overpayment fields (backward compatibility)
     */
    public function recalculateCustomerMeters(int $customerId): void
    {
        $customer = User::findOrFail($customerId);
        
        $meterIds = $customer->meterAssignments()
            ->pluck('meter_id')
            ->unique();
        
        // Recalculate each meter
        foreach ($meterIds as $meterId) {
            $this->recalculateMeterBalance($meterId);
        }
        
        // Update customer's balance and overpayment fields (for backward compatibility)
        $totalBalance = 0;
        $totalOverpayment = 0;
        
        foreach ($customer->meterAssignments()->where('is_active', true)->with('meter')->get() as $assignment) {
            $totalBalance += $assignment->meter->balance ?? 0;
            $totalOverpayment += $assignment->meter->overpayment ?? 0;
        }
        
        $customer->balance = $totalBalance;
        $customer->overpayment = $totalOverpayment;
        $customer->save();
        
        Log::info('Customer meters recalculated', [
            'customer_id' => $customerId,
            'meter_count' => $meterIds->count(),
            'total_balance' => $totalBalance,
            'total_overpayment' => $totalOverpayment
        ]);
    }
    
    /**
     * Generate meter statement with transactions
     * This is complex aggregation logic that belongs in a service
     */
    public function getMeterStatement(int $meterId, $dateFrom, $dateTo): array
    {
        $meter = Meter::with('currentAssignment.customer')->findOrFail($meterId);
        
        // Calculate opening balance
        $invoicesBeforeDate = Invoice::where('meter_id', $meterId)
            ->where('invoice_date', '<', $dateFrom)
            ->sum('total_amount');
        
        $paymentsBeforeDate = Payment::where('meter_id', $meterId)
            ->where('date', '<', $dateFrom)
            ->sum('amount');
        
        $openingBalance = $invoicesBeforeDate - $paymentsBeforeDate;
        
        // Get invoices in period
        $invoices = Invoice::where('meter_id', $meterId)
            ->whereBetween('invoice_date', [$dateFrom, $dateTo])
            ->orderBy('invoice_date')
            ->get()
            ->map(fn($inv) => [
                'type' => 'invoice',
                'date' => $inv->invoice_date,
                'reference' => $inv->invoice_number,
                'debit' => $inv->total_amount,
                'credit' => 0,
                'record' => $inv
            ]);
        
        // Get payments in period
        $payments = Payment::where('meter_id', $meterId)
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->orderBy('date')
            ->get()
            ->map(fn($pay) => [
                'type' => 'payment',
                'date' => $pay->date,
                'reference' => $pay->reference ?? 'N/A',
                'debit' => 0,
                'credit' => $pay->amount,
                'record' => $pay
            ]);
        
        // Combine and sort transactions
        $transactions = $invoices->concat($payments)->sortBy('date')->values();
        
        // Calculate running balance
        $runningBalance = $openingBalance;
        $transactionsWithBalance = $transactions->map(function ($transaction) use (&$runningBalance) {
            $runningBalance += $transaction['debit'] - $transaction['credit'];
            $transaction['balance'] = $runningBalance;
            return $transaction;
        });
        
        return [
            'meter' => $meter,
            'customer' => $meter->currentAssignment?->customer,
            'period_from' => $dateFrom,
            'period_to' => $dateTo,
            'opening_balance' => $openingBalance,
            'closing_balance' => $runningBalance,
            'transactions' => $transactionsWithBalance,
            'total_invoiced' => $invoices->sum('debit'),
            'total_paid' => $payments->sum('credit'),
        ];
    }
    
    /**
     * Get financial summary for a meter
     * Aggregates data from multiple sources
     */
    public function getMeterFinancialSummary(int $meterId): array
    {
        $meter = Meter::with(['currentAssignment.customer'])->findOrFail($meterId);
        
        $lastInvoice = $meter->invoices()->latest('invoice_date')->first();
        $lastPayment = Payment::where('meter_id', $meterId)->latest('date')->first();
        $openInvoicesCount = $meter->invoices()->where('state', 'open')->count();
        
        return [
            'meter_number' => $meter->meter_number,
            'customer' => $meter->currentAssignment?->customer,
            'current_balance' => $meter->balance,
            'overpayment' => $meter->overpayment,
            'last_invoice_date' => $lastInvoice?->invoice_date,
            'last_invoice_amount' => $lastInvoice?->total_amount,
            'last_payment_date' => $lastPayment?->date,
            'last_payment_amount' => $lastPayment?->amount,
            'total_billed' => $meter->total_billed,
            'total_paid' => $meter->total_paid,
            'open_invoices_count' => $openInvoicesCount,
            'financial_status' => $meter->financial_status, // Use model accessor
        ];
    }
}
```

**Why This is Better:**

1. ✅ **Only 4 methods** instead of 15+
2. ✅ **Clear purpose** - Each method does complex business logic
3. ✅ **Single responsibility** - Recalculation, statements, summaries
4. ✅ **No wrapper methods** - Direct model access for simple queries
5. ✅ **Maintainable** - Easy to understand and modify

**What was removed and why:**
- ❌ `getMeterBalance()` - Just use `$meter->balance`
- ❌ `getMeterOverpayment()` - Just use `$meter->overpayment`
- ❌ `updateMeterBalance()` - Use `recalculateMeterBalance()` for accuracy
- ❌ `recordInvoiceForMeter()` - Balance updates via recalculation
- ❌ `recordPaymentForMeter()` - Balance updates via recalculation
- ❌ `applyOverpaymentToMeter()` - Handled by recalculation
- ❌ `getCustomerTotalBalance()` - Use model accessor
- ❌ `recalculateAllMeters()` - Use command with chunking instead
- ❌ All reversal methods - Just recalculate after reversal
- ❌ **Entire `MeterFinancialHelper` class** - Not needed!

### 3.2 Create Backward Compatibility Helper

**❌ File Removed:** `app/Helpers/MeterFinancialHelper.php`

**Why removed:** Not needed! Customer balance accessors handle this directly in the model.

### 3.3 Unit Tests

```bash
php artisan make:test MeterFinancialServiceTest --unit
```

**File:** `tests/Unit/MeterFinancialServiceTest.php`

**Test cases:**
- [ ] Test `recalculateMeterBalance()` - with invoices and payments
- [ ] Test `recalculateMeterBalance()` - with overpayment scenario
- [ ] Test `recalculateCustomerMeters()` - customer with multiple meters
- [ ] Test `recalculateCustomerMeters()` - verify customer balance/overpayment updated
- [ ] Test `getMeterStatement()` - generates correct transactions
- [ ] Test `getMeterFinancialSummary()` - aggregates data correctly
- [ ] Test edge cases (meter with no transactions, disconnected meters)

### 3.4 Usage Pattern

**The Golden Rule: Just Recalculate!**

```php
// ❌ OLD WAY - Manual balance manipulation (COMPLEX & ERROR-PRONE):
$customer->balance = max(0, $customer->balance - $paymentAmount);
$customer->overpayment += $overpaymentAmount;
$customer->save();

$meter->balance -= $paymentAmount;
$meter->overpayment += $overpaymentAmount;
$meter->save();

// ✅ NEW WAY - Single source of truth (SIMPLE & ACCURATE):

// Step 1: Save transaction to database (invoice or payment)
$invoice->save();  // or $payment->save();

// Step 2: Recalculate balance from transaction history
app(MeterFinancialService::class)->recalculateMeterBalance($meterId);

// Done! Balance and overpayment are now accurate.
// No manual manipulation needed.
// No risk of incremental errors.
```

**When to call recalculation:**
- ✅ After invoice is saved
- ✅ After payment is saved
- ✅ After payment reversal
- ✅ After invoice correction
- ✅ After any transaction that affects balance

**When NOT to call:**
- ❌ Don't manually update `meter->balance` or `meter->overpayment`
- ❌ Don't try to "fix" balances with incremental changes
- ❌ Don't calculate differences - just recalculate from source

### 3.5 Deliverable
✅ MeterFinancialService ready with comprehensive tests

---

## 📅 PHASE 4: REFACTOR INVOICE SERVICE (Week 3-4)
**Goal:** Update invoice generation to use meter-centric approach

### 4.1 Update InvoiceService

**File:** `app/Services/Invoice/InvoiceService.php`

**Key Methods to Refactor:**

#### Method 1: processMeterAssignmentBills() (Lines 122-169)
```php
// FIND: Lines 135-145 (Overpayment calculation)
$customerAccount = User::find($meterAssignment->customer->id);
$overpayment = (float)$customerAccount->overpayment ?? 0;

// REPLACE WITH:
$meter = $meterAssignment->meter;
$overpayment = (float)$meter->overpayment ?? 0;

Log::info('InvoiceService: Overpayment Debug', [
    'meter_id' => $meter->id,
    'meter_number' => $meter->meter_number,
    'overpayment' => $overpayment,
    'total_amount' => $totalAmount,
    'meter_balance' => $meter->balance
]);
```

#### Method 2: createJournalEntries() (Lines 276-295)
```php
// FIND: Lines 289-294
$customerAccount = User::find($meterAssignment->customer->id);
$customerAccount->overpayment -= $creditToApply;
$customerAccount->balance = $invoice->balance;
$customerAccount->save();

// REPLACE WITH:
// After invoice is fully saved, recalculate meter balance from source of truth
app(\App\Services\MeterFinancialService::class)->recalculateMeterBalance($invoice->meter_id);

// That's it! No manual balance manipulation needed.
// The recalculation will figure out balance and overpayment from transaction history.
```

#### Method 3: updateCustomerBalance() (Lines 355-363)
```php
// FIND: Lines 355-363
protected function updateCustomerBalance($userId, $invoiceAmount): void
{
    $user = User::find($userId);
    if ($user) {
        $user->balance = $invoiceAmount;
        $user->save();
    }
}

// REPLACE WITH:
protected function updateCustomerBalance($userId, $invoiceAmount): void
{
    // DEPRECATED: This method is no longer used
    // Balance is now calculated from meter transactions
    // To update customer balance, call:
    // app(\App\Services\MeterFinancialService::class)->recalculateCustomerMeters($userId);
    
    Log::debug('updateCustomerBalance called (deprecated, no-op)');
}
```

#### Method 4: openInvoices() - NO CHANGES NEEDED
```php
// EXISTING METHOD - Keep as-is, no changes needed
public function openInvoices($customerId)
{
    $openInvoices = Invoice::where('state', 'open')
        ->where('customer_id', $customerId)
        ->get();
    return $openInvoices;
}

// NOTE: We don't need new methods like openInvoicesForMeter() or openInvoicesGroupedByMeter()
// If you need open invoices for a specific meter, just query directly:
// Invoice::where('meter_id', $meterId)->where('state', 'open')->get()
//
// Keep it simple - no unnecessary service methods!
```

### 4.2 Update Invoice Creation Method (IMPORTANT)

**Ensure meter_id is always set:**
```php
protected function createInvoice($meterAssignment, $bills, $creditToApply, $remainingAmount, $meter)
{
    // CRITICAL: Validate meter_id is set
    if (!$meter || !$meter->id) {
        throw new \Exception('Invoice must have a meter_id');
    }
    
    $invoice = Invoice::create([
        'invoice_number' => $this->generateInvoiceNumber(),
        'customer_id' => $meterAssignment->customer_id,
        'meter_id' => $meter->id, // ENSURE THIS IS SET
        'invoice_date' => now(),
        'due_date' => now()->addDays(14),
        // ... rest of fields
    ]);
    
    return $invoice;
}
```

**Why this matters:**
- Every invoice MUST have a `meter_id` for balance calculation to work
- Without `meter_id`, payments can't be properly allocated
- This is enforced at invoice creation, not as an afterthought

### 4.3 Summary of Changes

**What we're changing:**
1. ✅ Read overpayment from `meter.overpayment` instead of `customer.overpayment`
2. ✅ After invoice saved, call `recalculateMeterBalance($meterId)`
3. ✅ Deprecate `updateCustomerBalance()` method (make it no-op)
4. ✅ Ensure `meter_id` is always set on invoice creation

**What we're NOT changing:**
- ❌ No new methods added
- ❌ No complex refactoring
- ❌ Existing `openInvoices()` stays as-is

### 4.4 Testing
- [ ] Test invoice generation for single meter customer
- [ ] Test invoice generation for multi-meter customer
- [ ] Test overpayment application per meter
- [ ] Test balance calculations via recalculation
- [ ] Verify meter.balance matches sum(invoices) - sum(payments)

### 4.5 Deliverable
✅ Invoice service uses meter-centric financial tracking

---

## 📅 PHASE 5: REFACTOR PAYMENT SERVICE (Week 4-5)
**Goal:** Update payment recording to use meter-centric approach

### 5.1 Update PaymentService::handlePayment()

**File:** `app/Services/Payment/PaymentService.php`

**Lines to Refactor: 24-151**

```php
// FIND: Lines 37-48 (Payment creation)
$payment = new \App\Models\Payment();
$payment->customer_id = $invoice->customer_id;
$payment->invoice_id = $invoice->id;
$payment->method = $method;
$payment->reference = $reference;
$payment->amount = $amount;
$payment->status = $status;
$payment->date = now()->toDateString();
$payment->tenant_id = $invoice->tenant_id;
$payment->created_by = $createdBy ?? auth()->id();
$payment->save();

// UPDATE TO:
$payment = new \App\Models\Payment();
$payment->customer_id = $invoice->customer_id;
$payment->invoice_id = $invoice->id;
$payment->meter_id = $invoice->meter_id; // ADD THIS LINE
$payment->method = $method;
$payment->reference = $reference;
$payment->amount = $amount;
$payment->status = $status;
$payment->date = now()->toDateString();
$payment->tenant_id = $invoice->tenant_id;
$payment->created_by = $createdBy ?? auth()->id();
$payment->save();
```

```php
// FIND: Lines 138-150 (Balance update)
// Update customer balance and overpayment
$customer = User::find($invoice->customer_id);
$customer->balance = max(0, $customer->balance - $paymentAmount);

if ($overpaymentAmount > 0) {
    $customer->overpayment = ($customer->overpayment ?? 0) + $overpaymentAmount;
}
$customer->save();

// REPLACE WITH:
// After payment and journal entries are saved, recalculate meter balance
app(\App\Services\MeterFinancialService::class)->recalculateMeterBalance($payment->meter_id);

// That's it! Balance and overpayment calculated from transaction history.
```

### 5.2 Update CustomerPaymentService

**File:** `app/Services/CustomerPaymentService.php`

**Key Changes:**

```php
// ADD: Require meter_id parameter
public function recordCustomerPayment($data)
{
    // Validate required fields including meter_id
    if (!isset($data['meter_id'])) {
        throw new \Exception('Meter ID is required for payment');
    }
    
    $meterId = $data['meter_id'];
    $customerId = $data['customer_id'];
    
    // Validate meter belongs to customer
    $meter = Meter::where('id', $meterId)
        ->whereHas('assignments', function($query) use ($customerId) {
            $query->where('customer_id', $customerId)
                  ->where('is_active', true);
        })
        ->firstOrFail();
    
    // Create payment
    $payment = Payment::create([
        'customer_id' => $customerId,
        'meter_id' => $meterId,
        'invoice_id' => $data['invoice_id'] ?? null,
        'amount' => $data['amount'],
        'method' => $data['method'],
        'reference' => $data['reference'],
        'date' => $data['date'] ?? now(),
        'status' => $data['status'] ?? 'completed',
        'tenant_id' => auth()->user()->tenant_id,
        'created_by' => auth()->id(),
    ]);
    
    // Recalculate meter balance after payment
    app(\App\Services\MeterFinancialService::class)->recalculateMeterBalance($meterId);
    
    // Send SMS if requested
    if ($data['send_sms'] ?? false) {
        $this->sendPaymentSms($payment, $meter);
    }
    
    return $payment;
}
```

### 5.3 Update PaymentReversalService

**File:** `app/Services/Payment/PaymentReversalService.php`

```php
public function reversePayment(Payment $payment, string $reason): void
{
    DB::transaction(function () use ($payment, $reason) {
        // Mark payment as reversed (or delete it)
        $payment->update([
            'reversal_reason' => $reason,
            'reversed_at' => now(),
            'reversed_by' => auth()->id(),
        ]);
        
        // Reverse journal entries (existing code)
        // ...
        
        // Recalculate meter balance - it will automatically adjust since payment is reversed
        app(\App\Services\MeterFinancialService::class)->recalculateMeterBalance($payment->meter_id);
    });
}
```

### 5.4 Testing
- [ ] Test payment recording with meter_id
- [ ] Test payment to specific invoice
- [ ] Test general payment (no invoice)
- [ ] Test payment with overpayment
- [ ] Test payment reversal
- [ ] Test validation (meter belongs to customer)
- [ ] Compare results with old system

### 5.5 Deliverable
✅ Payment service uses meter-centric financial tracking

---

## 📅 PHASE 6: UPDATE UI - MODELS (Week 5)
**Goal:** Add meter financial accessors and update customer aggregates

### 6.1 Update Meter Model

**File:** `app/Models/Meter.php`

**Add casts for financial fields:**
```php
// Find the $casts property and ADD these:
protected $casts = [
    'installation_date' => 'datetime',
    'last_reading_date' => 'datetime',
    'current_reading' => 'float',
    'initial_reading' => 'float',
    'last_reading' => 'float',
    // ADD THESE NEW CASTS:
    'balance' => 'float',
    'overpayment' => 'float',
    'total_billed' => 'float',
    'total_paid' => 'float',
    'last_invoice_date' => 'date',
];
```

**Add ONE accessor for financial status:**
```php
/**
 * Get financial status indicator (for UI display)
 */
public function getFinancialStatusAttribute(): string
{
    if ($this->balance > 0) return 'Outstanding';
    if ($this->overpayment > 0) return 'Credit';
    return 'Clear';
}
```

**Add payments relationship (if not already exists):**
```php
public function payments()
{
    return $this->hasMany(Payment::class);
}
```

**That's it! Keep it minimal.**
- ❌ Don't add `getCurrentBalanceAttribute` - just use `$meter->balance` (cast handles it)
- ❌ Don't add `getCurrentOverpaymentAttribute` - just use `$meter->overpayment`
- ❌ Don't add `getBalanceDisplayAttribute` - format in blade/component where needed
- ❌ Don't add `getBalanceColorAttribute` - determine inline where needed

### 6.2 Update User Model

**File:** `app/Models/User.php`

**Keep existing casts (no changes needed):**
```php
// These should already exist in your User model:
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'registered_at' => 'datetime',
        'terminated_at' => 'datetime',
        'balance' => 'decimal:2',  // Keep for backward compatibility
        'overpayment' => 'decimal:2',  // Keep for backward compatibility
    ];
}
```

**NO accessors needed!**
- ❌ Don't add `getTotalBalanceAttribute` - Query directly where needed
- ❌ Don't add `getTotalOverpaymentAttribute` - Query directly where needed
- ❌ Don't add `getMeterBalancesAttribute` - Query directly where needed
- ❌ Don't add `hasMultipleMetersAttribute` - Check count directly where needed

**If you need customer's total balance, query directly:**
```php
// In controller/view where needed:
$totalBalance = $customer->meterAssignments()
    ->where('is_active', true)
    ->with('meter')
    ->get()
    ->sum(fn($a) => $a->meter->balance);
```

**Or better yet, use the service:**
```php
$service = app(\App\Services\MeterFinancialService::class);
$service->recalculateCustomerMeters($customerId); // This updates customer.balance field
// Then just use:
$customer->balance  // Already synced by service
```

### 6.3 Testing
- [ ] Verify meter financial casts work correctly
- [ ] Test `financial_status` accessor
- [ ] Verify backward compatibility with customer.balance field
- [ ] Test with customers having 0, 1, and multiple meters

### 6.4 Deliverable
✅ Models ready with meter financial accessors

---

## 📅 PHASE 7: UPDATE UI - FILAMENT RESOURCES (Week 5-6)
**Goal:** Update Filament admin panel to display meter-centric data

### 7.1 Update Existing MeterResource

**Note:** MeterResource already exists in the project. We're just adding financial columns.

**File:** `app/Filament/Tenant/Resources/MeterResource.php`

**Key Sections:**
```php
public static function form(Form $form): Form
{
    return $form->schema([
        // Existing meter fields...
        
        Section::make('Financial Information')
            ->schema([
                Placeholder::make('balance')
                    ->label('Current Balance')
                    ->content(fn($record) => 'KES ' . number_format($record->balance ?? 0, 2)),
                    
                Placeholder::make('overpayment')
                    ->label('Overpayment/Credit')
                    ->content(fn($record) => 'KES ' . number_format($record->overpayment ?? 0, 2)),
                    
                Placeholder::make('financial_status')
                    ->label('Status')
                    ->content(fn($record) => $record->financial_status ?? '-'),
            ])
            ->collapsible(),
    ]);
}

public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('meter_number')
                ->label('Meter Number')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('currentAssignment.customer.name')
                ->label('Customer')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('status')
                ->badge()
                ->colors([
                    'success' => 'active',
                    'danger' => 'inactive',
                ]),
                
            TextColumn::make('balance')
                ->label('Balance')
                ->money('KES')
                ->sortable()
                ->color(fn($record) => $record->balance > 0 ? 'danger' : 'gray'),
                
            TextColumn::make('overpayment')
                ->label('Credit')
                ->money('KES')
                ->sortable(),
                
            TextColumn::make('last_invoice_date')
                ->label('Last Invoice')
                ->date()
                ->sortable(),
        ])
        ->filters([
            SelectFilter::make('status')
                ->options([
                    'active' => 'Active',
                    'inactive' => 'Inactive',
                ]),
                
            Filter::make('has_balance')
                ->label('Has Outstanding Balance')
                ->query(fn($query) => $query->where('balance', '>', 0)),
                
            Filter::make('has_overpayment')
                ->label('Has Credit')
                ->query(fn($query) => $query->where('overpayment', '>', 0)),
        ])
        ->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('record_payment')
                ->label('Record Payment')
                ->icon('heroicon-o-currency-dollar')
                ->color('success')
                ->url(fn($record) => MeterResource::getUrl('payment', ['record' => $record])),
        ]);
}
```

### 7.2 Create Meter View Page

**File:** `app/Filament/Tenant/Resources/MeterResource/Pages/ViewMeter.php`

```php
<?php

namespace App\Filament\Tenant\Resources\MeterResource\Pages;

use App\Filament\Tenant\Resources\MeterResource;
use App\Services\MeterFinancialService;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewMeter extends ViewRecord
{
    protected static string $resource = MeterResource::class;
    
    protected function getHeaderWidgets(): array
    {
        return [
            MeterResource\Widgets\MeterFinancialSummary::class,
        ];
    }
    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Components\Section::make('Meter Information')
                    ->schema([
                        Components\TextEntry::make('meter_number'),
                        Components\TextEntry::make('serial_number'),
                        Components\TextEntry::make('status')->badge(),
                        Components\TextEntry::make('currentAssignment.customer.name')
                            ->label('Current Customer'),
                    ])
                    ->columns(2),
                    
                Components\Section::make('Financial Summary')
                    ->schema([
                        Components\TextEntry::make('balance')
                            ->label('Current Balance')
                            ->money('KES')
                            ->color(fn($record) => $record->balance_color),
                            
                        Components\TextEntry::make('overpayment')
                            ->label('Credit/Overpayment')
                            ->money('KES'),
                            
                        Components\TextEntry::make('total_billed')
                            ->label('Total Billed (Lifetime)')
                            ->money('KES'),
                            
                        Components\TextEntry::make('total_paid')
                            ->label('Total Paid (Lifetime)')
                            ->money('KES'),
                    ])
                    ->columns(2),
            ]);
    }
}
```

### 7.3 Update CustomerResource

**File:** `app/Filament/Tenant/Resources/CustomerResource.php`

**Update table columns (keep it simple):**
```php
public static function table(Table $table): Table
{
    return $table
        ->columns([
            TextColumn::make('name')
                ->searchable()
                ->sortable(),
                
            TextColumn::make('telephone')
                ->searchable(),
                
            TextColumn::make('active_meters_count')
                ->label('Active Meters')
                ->counts('meterAssignments', fn($query) => $query->where('is_active', true))
                ->sortable(),
                
            // Just show the balance field - it's synced by recalculateCustomerMeters()
            TextColumn::make('balance')
                ->label('Balance')
                ->money('KES')
                ->sortable()
                ->color(fn($state) => $state > 0 ? 'danger' : 'gray'),
                
            TextColumn::make('overpayment')
                ->label('Credit')
                ->money('KES')
                ->sortable()
                ->color(fn($state) => $state > 0 ? 'success' : 'gray'),
                
            TextColumn::make('status')
                ->badge(),
        ]);
}
```

**Why simpler?**
- ✅ `customer.balance` and `customer.overpayment` are synced by `recalculateCustomerMeters()`
- ✅ No complex queries in table columns (better performance)
- ✅ If balance is stale, just run the recalculation command

**Update relations:**
```php
public static function getRelations(): array
{
    return [
        RelationManagers\MetersRelationManager::class, // ADD THIS (create below)
        RelationManagers\InvoicesRelationManager::class,
        RelationManagers\PaymentsRelationManager::class,
        RelationManagers\ContactsRelationManager::class,
    ];
}
```

### 7.4 Create MetersRelationManager

```bash
php artisan make:filament-relation-manager CustomerResource meters meter_id
```

**File:** `app/Filament/Tenant/Resources/CustomerResource/RelationManagers/MetersRelationManager.php`

```php
<?php

namespace App\Filament\Tenant\Resources\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Tables;
use Filament\Resources\RelationManagers\RelationManager;

class MetersRelationManager extends RelationManager
{
    protected static string $relationship = 'meterAssignments';
    protected static ?string $title = 'Meters';
    protected static ?string $recordTitleAttribute = 'meter.meter_number';
    
    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('meter.meter_number')
                    ->label('Meter Number')
                    ->searchable()
                    ->url(fn($record) => route('filament.tenant.resources.meters.view', ['record' => $record->meter_id])),
                    
                Tables\Columns\TextColumn::make('meter.status')
                    ->label('Status')
                    ->badge(),
                    
                Tables\Columns\TextColumn::make('meter.balance')
                    ->label('Balance')
                    ->money('KES')
                    ->color(fn($record) => $record->meter->balance > 0 ? 'danger' : 'gray'),
                    
                Tables\Columns\TextColumn::make('meter.overpayment')
                    ->label('Credit')
                    ->money('KES')
                    ->color(fn($record) => $record->meter->overpayment > 0 ? 'success' : 'gray'),
                    
                Tables\Columns\TextColumn::make('meter.last_invoice_date')
                    ->label('Last Invoice')
                    ->date(),
                    
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\Filter::make('is_active')
                    ->label('Active Only')
                    ->query(fn($query) => $query->where('is_active', true))
                    ->default(),
            ])
            ->headerActions([
                // Add meter assignment action
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn($record) => route('filament.tenant.resources.meters.view', ['record' => $record->meter_id])),
                    
                Tables\Actions\Action::make('record_payment')
                    ->label('Record Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('amount')
                            ->label('Amount')
                            ->required()
                            ->numeric()
                            ->prefix('KES'),
                            
                        Forms\Components\Select::make('invoice_id')
                            ->label('Invoice (Optional)')
                            ->options(fn($record) => 
                                \App\Models\Invoice::where('meter_id', $record->meter_id)
                                    ->where('state', 'open')
                                    ->pluck('invoice_number', 'id')
                            ),
                            
                        Forms\Components\Select::make('method')
                            ->options([
                                'cash' => 'Cash',
                                'mpesa' => 'M-Pesa',
                                'bank' => 'Bank Transfer',
                            ])
                            ->required(),
                            
                        Forms\Components\TextInput::make('reference')
                            ->label('Reference Number'),
                    ])
                    ->action(function($record, array $data) {
                        $customerPaymentService = app(\App\Services\CustomerPaymentService::class);
                        $customerPaymentService->recordCustomerPayment([
                            'customer_id' => $record->customer_id,
                            'meter_id' => $record->meter_id,
                            'amount' => $data['amount'],
                            'method' => $data['method'],
                            'reference' => $data['reference'] ?? null,
                            'invoice_id' => $data['invoice_id'] ?? null,
                            'send_sms' => true,
                        ]);
                    }),
            ]);
    }
}
```

### 7.5 Update InvoicesRelationManager

**File:** `app/Filament/Tenant/Resources/CustomerResource/RelationManagers/InvoicesRelationManager.php`

**Add meter_number column:**
```php
Tables\Columns\TextColumn::make('meter.meter_number')
    ->label('Meter')
    ->searchable()
    ->sortable(),
```

### 7.6 Update PaymentsRelationManager (if exists)

**Add meter_number column:**
```php
Tables\Columns\TextColumn::make('meter.meter_number')
    ->label('Meter')
    ->searchable()
    ->sortable(),
```

### 7.7 Deliverable
✅ UI displays meter-level financial information

---

## 📅 PHASE 8: REFACTOR QUICK PAYMENT (Week 6)
**Goal:** Update Quick Payment to require meter selection and show meter-specific balances

### 8.1 Update CustomerPaymentService

**File:** `app/Services/CustomerPaymentService.php`

**Current Issues:**
- Gets latest invoice across ALL meters
- Shows customer-level balance/overpayment
- No meter selection

**Changes Needed:**

#### Update: getLatestUnpaidInvoice (add meter filter)
```php
// ADD NEW METHOD: Get latest unpaid invoice for specific meter
public function getLatestUnpaidInvoiceForMeter(int $customerId, int $meterId): ?Invoice
{
    return Invoice::where('customer_id', $customerId)
        ->where('meter_id', $meterId)
        ->where('balance', '>', 0)
        ->whereIn('status', ['invoiced', 'not paid', 'partial payment', 'pending'])
        ->orderBy('invoice_date', 'desc')
        ->first();
}
```

#### Update: getCustomerPaymentContext (add meter parameter)
```php
// MODIFY METHOD: Add meter_id parameter
public function getCustomerPaymentContext(User $customer, ?int $meterId = null): array
{
    if ($meterId) {
        // Get meter-specific context
        $meter = \App\Models\Meter::findOrFail($meterId);
        $latestInvoice = $this->getLatestUnpaidInvoiceForMeter($customer->id, $meterId);
        
        return [
            'customer' => $customer,
            'meter' => $meter,
            'latest_invoice' => $latestInvoice,
            'has_unpaid_invoice' => !is_null($latestInvoice),
            'suggested_amount' => $latestInvoice ? $latestInvoice->balance : 0,
            'meter_balance' => $meter->balance,
            'meter_overpayment' => $meter->overpayment,
        ];
    }
    
    // Legacy: customer-level context (for backward compatibility)
    $latestInvoice = $this->getLatestUnpaidInvoice($customer->id);
    
    return [
        'customer' => $customer,
        'meter' => null,
        'latest_invoice' => $latestInvoice,
        'has_unpaid_invoice' => !is_null($latestInvoice),
        'suggested_amount' => $latestInvoice ? $latestInvoice->balance : 0,
        'customer_balance' => $customer->balance ?? 0,
        'customer_overpayment' => $customer->overpayment ?? 0,
    ];
}
```

#### Update: processQuickPayment (require meter_id)
```php
// MODIFY METHOD: Require meter_id parameter
public function processQuickPayment(User $customer, array $paymentData): array
{
    try {
        // VALIDATE: meter_id is required
        if (!isset($paymentData['meter_id'])) {
            throw new \InvalidArgumentException('Meter ID is required for payment.');
        }
        
        // Validate meter belongs to customer
        $meter = \App\Models\Meter::where('id', $paymentData['meter_id'])
            ->whereHas('assignments', function($query) use ($customer) {
                $query->where('customer_id', $customer->id)
                      ->where('is_active', true);
            })
            ->firstOrFail();
        
        // Validate payment amount
        if (empty($paymentData['amount']) || (float)$paymentData['amount'] <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        DB::beginTransaction();

        $latestInvoice = $this->getLatestUnpaidInvoiceForMeter($customer->id, $meter->id);
        $amount = (float)$paymentData['amount'];

        if ($latestInvoice) {
            // Pay against latest invoice for this meter
            $invoiceBalance = $latestInvoice->balance;
            $paymentAmount = min($amount, $invoiceBalance);
            $overpaymentAmount = max(0, $amount - $invoiceBalance);
            
            if ($overpaymentAmount > 0) {
                $message = "Payment of KES " . number_format($amount, 2) . 
                          " applied: KES " . number_format($paymentAmount, 2) . 
                          " to Invoice #{$latestInvoice->invoice_number} for Meter {$meter->meter_number}, " .
                          "KES " . number_format($overpaymentAmount, 2) . " as overpayment";
            } else {
                $message = "Payment of KES " . number_format($amount, 2) . 
                          " applied to Invoice #{$latestInvoice->invoice_number} for Meter {$meter->meter_number}";
            }

            // Pay against the invoice
            $updatedInvoice = $this->payInvoice($latestInvoice, $paymentData);
            $result = $updatedInvoice;
        } else {
            // Create advance payment for this meter
            $result = $this->createAdvancePayment($customer, $paymentData);
            $message = "Advance payment of KES " . number_format($amount, 2) . 
                      " recorded for {$customer->name} - Meter {$meter->meter_number}";
        }

        DB::commit();

        Log::info("CustomerPaymentService: Quick payment processed", [
            'customer_id' => $customer->id,
            'meter_id' => $meter->id,
            'amount' => $amount
        ]);

        return [
            'success' => true,
            'message' => $message,
            'payment' => $result,
            'invoice' => $updatedInvoice ?? $latestInvoice,
            'meter' => $meter,
        ];

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("CustomerPaymentService: Quick payment failed: " . $e->getMessage());
        
        return [
            'success' => false,
            'message' => 'Payment processing failed: ' . $e->getMessage(),
            'error' => $e->getMessage(),
        ];
    }
}
```

#### Update: createAdvancePayment (add meter_id)
```php
protected function createAdvancePayment(User $customer, array $paymentData): Payment
{
    $amount = (float)$paymentData['amount'];
    $meterId = $paymentData['meter_id']; // REQUIRED NOW

    // Create payment record
    $payment = Payment::create([
        'customer_id' => $customer->id,
        'meter_id' => $meterId,  // ADDED
        'invoice_id' => null, // No specific invoice
        'amount' => $amount,
        'method' => $paymentData['method'],
        'reference' => $paymentData['reference'] ?? null,
        'status' => $paymentData['status'] ?? 'completed',
        'date' => $paymentData['date'] ?? now(),
        'tenant_id' => $customer->tenant_id,
        'created_by' => auth()->id(),
    ]);

    // Recalculate meter balance (will set overpayment)
    app(\App\Services\MeterFinancialService::class)->recalculateMeterBalance($meterId);

    return $payment;
}
```

### 8.2 Update CustomerActionHelper

**File:** `app/Filament/Helpers/CustomerActionHelper.php`

**Changes to Quick Pay Form:**

```php
public static function getQuickPayTableAction(): TableAction
{
    return TableAction::make('quickPay')
        ->label('Quick Pay')
        ->icon('heroicon-o-currency-dollar')
        ->color('success')
        ->form(function ($record) {
            $paymentService = new CustomerPaymentService();
            
            $schema = [
                // STEP 1: Meter Selection (NEW - FIRST FIELD)
                Forms\Components\Select::make('meter_id')
                    ->label('Select Meter')
                    ->required()
                    ->options(function () use ($record) {
                        return \App\Models\MeterAssignment::where('customer_id', $record->id)
                            ->where('is_active', true)
                            ->with('meter')
                            ->get()
                            ->mapWithKeys(fn($assignment) => [
                                $assignment->meter_id => $assignment->meter->meter_number . 
                                    ' (Balance: KES ' . number_format($assignment->meter->balance, 2) . 
                                    ($assignment->meter->overpayment > 0 ? 
                                        ' | Credit: KES ' . number_format($assignment->meter->overpayment, 2) : 
                                        '') . ')'
                            ]);
                    })
                    ->searchable()
                    ->reactive()
                    ->afterStateUpdated(function ($state, callable $set) use ($record) {
                        if ($state) {
                            $meter = \App\Models\Meter::find($state);
                            $paymentService = new CustomerPaymentService();
                            $context = $paymentService->getCustomerPaymentContext($record, $state);
                            
                            // Update form fields based on selected meter
                            $set('meter_balance', $meter->balance);
                            $set('meter_overpayment', $meter->overpayment);
                            $set('suggested_amount', $context['suggested_amount']);
                            $set('latest_invoice_info', 
                                $context['has_unpaid_invoice'] ? 
                                    "Invoice #{$context['latest_invoice']->invoice_number} - KES " . 
                                    number_format($context['latest_invoice']->balance, 2) : 
                                    'No outstanding invoices'
                            );
                        }
                    })
                    ->helperText('Select which meter this payment is for'),
                
                // STEP 2: Meter Financial Summary (NEW - DYNAMIC DISPLAY)
                Forms\Components\Placeholder::make('meter_info')
                    ->label('Meter Financial Information')
                    ->content(function ($get) {
                        $meterId = $get('meter_id');
                        if (!$meterId) {
                            return 'Please select a meter first';
                        }
                        
                        $balance = $get('meter_balance') ?? 0;
                        $overpayment = $get('meter_overpayment') ?? 0;
                        $invoiceInfo = $get('latest_invoice_info') ?? '-';
                        
                        return "Balance: KES " . number_format($balance, 2) . 
                               " | Credit: KES " . number_format($overpayment, 2) . 
                               " | Latest Invoice: " . $invoiceInfo;
                    })
                    ->columnSpanFull(),
                
                // Hidden fields to store meter data
                Forms\Components\Hidden::make('meter_balance'),
                Forms\Components\Hidden::make('meter_overpayment'),
                Forms\Components\Hidden::make('latest_invoice_info'),
                
                // STEP 3: Payment Amount
                TextInput::make('amount')
                    ->label('Payment Amount')
                    ->required()
                    ->numeric()
                    ->prefix('KES')
                    ->default(fn($get) => $get('suggested_amount'))
                    ->helperText(fn($get) => 
                        $get('suggested_amount') > 0 ? 
                            "Suggested: KES " . number_format($get('suggested_amount'), 2) . " (invoice balance)" : 
                            'Any amount will be recorded as advance payment'
                    )
                    ->reactive(),
                
                // Payment context info
                Placeholder::make('payment_context')
                    ->label('Payment Allocation')
                    ->content(function ($get) {
                        $amount = (float)($get('amount') ?? 0);
                        $balance = (float)($get('meter_balance') ?? 0);
                        
                        if ($amount <= 0) return '-';
                        
                        if ($balance > 0) {
                            $appliedToBalance = min($amount, $balance);
                            $overpayment = max(0, $amount - $balance);
                            
                            if ($overpayment > 0) {
                                return "KES " . number_format($appliedToBalance, 2) . 
                                       " to balance, KES " . number_format($overpayment, 2) . 
                                       " as credit";
                            } else {
                                return "KES " . number_format($appliedToBalance, 2) . " to balance";
                            }
                        } else {
                            return "KES " . number_format($amount, 2) . " as advance payment/credit";
                        }
                    })
                    ->columnSpanFull(),
                
                // STEP 4: Payment Details (existing fields)
                Forms\Components\Select::make('method')
                    ->label('Payment Method')
                    ->options([
                        'mpesa' => 'M-Pesa',
                        'bank' => 'Bank Transfer',
                        'cash' => 'Cash',
                        'cheque' => 'Cheque',
                    ])
                    ->default('mpesa')
                    ->required(),
                    
                TextInput::make('reference')
                    ->label('Reference Number')
                    ->placeholder('M-Pesa code, cheque number, etc.'),
                    
                Forms\Components\Select::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'paid' => 'Paid',
                    ])
                    ->default('completed')
                    ->required(),
                    
                Forms\Components\Toggle::make('send_sms')
                    ->label('Send SMS Notification')
                    ->default(true),
            ];
            
            return $schema;
        })
        ->action(function (array $data, $record) {
            try {
                $paymentService = new CustomerPaymentService();
                $result = $paymentService->processQuickPayment($record, $data);
                
                if ($result['success']) {
                    \Filament\Notifications\Notification::make()
                        ->title('Payment processed successfully!')
                        ->body($result['message'])
                        ->success()
                        ->send();
                } else {
                    \Filament\Notifications\Notification::make()
                        ->title('Payment processing failed')
                        ->body($result['message'])
                        ->danger()
                        ->send();
                }
            } catch (\Exception $e) {
                \Filament\Notifications\Notification::make()
                    ->title('Payment error')
                    ->body('An unexpected error occurred: ' . $e->getMessage())
                    ->danger()
                    ->send();
            }
        })
        ->requiresConfirmation()
        ->modalHeading(fn($record): string => 'Quick Pay for ' . $record->name)
        ->modalDescription('Select meter and enter payment details')
        ->modalSubmitActionLabel('Process Payment');
}
```

**Also update: getQuickPayPageAction() with same logic**

### 8.3 Visual Flow for User

**Before (Customer-Centric):**
```
Quick Pay Form:
├── Amount: [____]
├── Method: [M-Pesa ▼]
├── Reference: [____]
└── [Process Payment]

Shows: "Latest invoice: INV-123 (KES 5,000)"
```

**After (Meter-Centric):**
```
Quick Pay Form:
├── Select Meter: [Meter-001 (Balance: KES 2,300) ▼]  ← NEW
├── Meter Info: Balance: KES 2,300 | Credit: KES 0   ← NEW (auto-updates)
│   Latest Invoice: INV-456 - KES 2,300              ← NEW (filtered by meter)
├── Amount: [2300] (suggested)
├── Payment Allocation: KES 2,300 to balance          ← NEW (shows split)
├── Method: [M-Pesa ▼]
├── Reference: [____]
└── [Process Payment]
```

### 8.4 Multi-Meter Customer Example

**Scenario:** Customer "John Doe" has 3 meters:
- Meter-001: Balance KES 2,300 (house)
- Meter-045: Balance KES 0, Credit KES 500 (shop - overpaid)
- Meter-078: Balance KES 5,100 (rental property)

**Quick Pay Flow:**
1. User clicks "Quick Pay" on John Doe
2. Dropdown shows:
   ```
   Meter-001 (Balance: KES 2,300)
   Meter-045 (Balance: KES 0 | Credit: KES 500)
   Meter-078 (Balance: KES 5,100)
   ```
3. User selects "Meter-001"
4. Form shows:
   - Balance: KES 2,300
   - Credit: KES 0
   - Latest Invoice: INV-456 - KES 2,300
   - Suggested amount: 2,300
5. User enters amount and pays
6. Only Meter-001 balance is updated

### 8.5 Testing Checklist
- [ ] Customer with single meter - meter auto-selected or shown
- [ ] Customer with multiple meters - dropdown shows all meters with balances
- [ ] Payment to meter with outstanding balance
- [ ] Payment exceeding meter balance (creates overpayment)
- [ ] Advance payment to meter with no invoices
- [ ] Payment to meter that already has overpayment
- [ ] Verify meter selection is required (validation)
- [ ] Verify only active meters shown in dropdown
- [ ] Verify balance updates correctly after payment
- [ ] Verify SMS shows correct meter balance

### 8.6 Deliverable
✅ Quick Payment now requires meter selection and shows meter-specific financial data

---

## 📅 PHASE 9: UPDATE OTHER PAYMENT FORMS (Week 6)
**Goal:** Add meter selection to all other payment entry points

### 8.1 Update RecordPayment Action

**File:** Find where payments are recorded (likely in CustomerResource actions or separate page)

**Add meter selection field:**
```php
Forms\Components\Select::make('meter_id')
    ->label('Select Meter')
    ->required()
    ->options(function ($livewire) {
        $customerId = $livewire->record->id;
        
        return \App\Models\MeterAssignment::where('customer_id', $customerId)
            ->where('is_active', true)
            ->with('meter')
            ->get()
            ->mapWithKeys(fn($assignment) => [
                $assignment->meter_id => $assignment->meter->meter_number . 
                    ' (Balance: KES ' . number_format($assignment->meter->balance, 2) . ')'
            ]);
    })
    ->reactive()
    ->afterStateUpdated(function ($state, callable $set) {
        if ($state) {
            $meter = \App\Models\Meter::find($state);
            $set('current_balance', $meter->balance);
            $set('current_overpayment', $meter->overpayment);
            
            // Load open invoices for this meter
            $invoices = \App\Models\Invoice::where('meter_id', $state)
                ->where('state', 'open')
                ->orderBy('invoice_date', 'desc')
                ->get()
                ->mapWithKeys(fn($inv) => [
                    $inv->id => "{$inv->invoice_number} - KES " . 
                        number_format($inv->balance, 2) . 
                        " (Due: {$inv->due_date->format('Y-m-d')})"
                ]);
            
            $set('available_invoices', $invoices);
        }
    })
    ->helperText('Select which meter this payment is for'),

Forms\Components\Placeholder::make('meter_info')
    ->label('Meter Financial Info')
    ->content(function ($get) {
        $balance = $get('current_balance') ?? 0;
        $overpayment = $get('current_overpayment') ?? 0;
        
        return "Balance: KES " . number_format($balance, 2) . 
               " | Credit: KES " . number_format($overpayment, 2);
    })
    ->visible(fn($get) => $get('meter_id')),

Forms\Components\Select::make('invoice_id')
    ->label('Invoice (Optional)')
    ->options(fn($get) => $get('available_invoices') ?? [])
    ->searchable()
    ->helperText('Leave empty for general payment to meter')
    ->visible(fn($get) => $get('meter_id')),
```

### 8.2 Update QuickPay Component (if exists)

**Similar meter selection logic**

### 8.3 Add Validation

```php
// In the payment action
->action(function (array $data) {
    // Validate meter belongs to customer
    $customerId = $this->record->id;
    $meterId = $data['meter_id'];
    
    $validMeter = \App\Models\MeterAssignment::where('customer_id', $customerId)
        ->where('meter_id', $meterId)
        ->where('is_active', true)
        ->exists();
    
    if (!$validMeter) {
        throw new \Exception('Invalid meter selection');
    }
    
    // Record payment
    $customerPaymentService = app(\App\Services\CustomerPaymentService::class);
    $customerPaymentService->recordCustomerPayment($data);
})
```

### 8.4 Deliverable
✅ Payment forms require meter selection

---

## 📅 PHASE 10: UPDATE SMS & COMMUNICATIONS (Week 6-7)
**Goal:** Update message templates to use meter-specific balances

### 9.1 Update Invoice SMS

**File:** `app/Services/Invoice/InvoiceService.php`
**Method:** `sendInvoiceSms()`
**Lines:** ~520-572

```php
// FIND: Message template placeholder replacements
protected function sendInvoiceSms($invoice, $customer)
{
    if ($customer && $customer->telephone) {
        $meter = $invoice->meter;
        $totalAmount = $invoice->total_amount;

        // Define the message template
        $messageTemplate = "Dear {customer_name}, your invoice {invoice_number} 
for METER {meter_number} amounts to KES {invoice_amount}. 
Current meter balance: KES {meter_balance}. 
Pay via Paybill...";

        // Replace placeholders with actual values
        $formattedMessage = str_replace('{customer_name}', $customer->name, $messageTemplate);
        $formattedMessage = str_replace('{invoice_number}', $invoice->invoice_number, $formattedMessage);
        $formattedMessage = str_replace('{meter_number}', $meter->meter_number, $formattedMessage);
        $formattedMessage = str_replace('{invoice_amount}', number_format($totalAmount, 2), $formattedMessage);
        $formattedMessage = str_replace('{meter_balance}', number_format($meter->balance, 2), $formattedMessage); // UPDATED
        $formattedMessage = str_replace('{invoice_date}', $invoice->invoice_date->format('d/m/Y'), $formattedMessage);
        
        // Queue SMS
        dispatch(new \App\Jobs\SimpleSendSmsJob($customer->telephone, $formattedMessage));
        
        // Send to contacts
        foreach ($customer->contacts as $contact) {
            dispatch(new \App\Jobs\SimpleSendSmsJob($contact->phone, $formattedMessage));
        }
    }
}
```

### 9.2 Update Payment SMS

**File:** `app/Services/Payment/PaymentService.php`
**Method:** `notifyCustomer()`
**Lines:** ~310-342

```php
protected function notifyCustomer($payment, $invoice)
{
    try {
        $customer = $payment->customer;
        $meter = \App\Models\Meter::find($payment->meter_id);
        
        if (!$meter) {
            Log::warning('Payment SMS: Meter not found', ['payment_id' => $payment->id]);
            return;
        }
        
        // Get message template
        $messageTemplate = "Payment of KES {paid_amount} received for METER {meter_number}. 
Invoice {invoice_number} updated. 
New meter balance: KES {meter_balance}. 
Meter credit: KES {meter_overpayment}. 
Thank you.";
        
        // Replace placeholders
        $message = str_replace('{customer_name}', $customer->name, $messageTemplate);
        $message = str_replace('{paid_amount}', number_format($payment->amount, 2), $message);
        $message = str_replace('{invoice_number}', $invoice->invoice_number ?? 'N/A', $message);
        $message = str_replace('{meter_number}', $meter->meter_number, $message);
        $message = str_replace('{meter_balance}', number_format($meter->balance, 2), $message); // UPDATED
        $message = str_replace('{meter_overpayment}', number_format($meter->overpayment, 2), $message); // UPDATED
        $message = str_replace('{payment_date}', now()->format('Y-m-d'), $message);
        
        // Send SMS
        if ($customer->telephone) {
            \App\Services\Sms\SmsManager::send($customer->telephone, $message);
        }
        
        // Send to contacts
        foreach ($customer->contacts as $contact) {
            \App\Services\Sms\SmsManager::send($contact->phone, $message);
        }
        
    } catch (\Exception $e) {
        Log::error("SMS sending failed: " . $e->getMessage());
    }
}
```

### 9.3 Update Bulk SMS

**File:** `app/Filament/Tenant/Resources/CustomerResource/Pages/BulkSendSms.php`

**Add per-meter option:**
```php
Forms\Components\Checkbox::make('send_per_meter')
    ->label('Send separate SMS per meter')
    ->helperText('Each customer with multiple meters will receive individual messages per meter')
    ->reactive()
    ->default(false),

Forms\Components\Placeholder::make('meter_info')
    ->content('Messages will include meter-specific balance information')
    ->visible(fn($get) => $get('send_per_meter')),
```

**Update send logic:**
```php
protected function sendBulkSms(array $data)
{
    $customers = $this->getSelectedCustomers();
    $message = $data['message'];
    $sendPerMeter = $data['send_per_meter'] ?? false;
    
    foreach ($customers as $customer) {
        if ($sendPerMeter) {
            // Send separate message per meter
            foreach ($customer->meterAssignments()->where('is_active', true)->get() as $assignment) {
                $meter = $assignment->meter;
                $meterMessage = $message;
                $meterMessage = str_replace('{customer_name}', $customer->name, $meterMessage);
                $meterMessage = str_replace('{meter_number}', $meter->meter_number, $meterMessage);
                $meterMessage = str_replace('{meter_balance}', number_format($meter->balance, 2), $meterMessage);
                $meterMessage = str_replace('{meter_overpayment}', number_format($meter->overpayment, 2), $meterMessage);
                
                dispatch(new \App\Jobs\SimpleSendSmsJob($customer->telephone, $meterMessage));
            }
        } else {
            // Send single message with total balance
            $totalBalance = $customer->total_balance;
            $messageFormatted = str_replace('{customer_name}', $customer->name, $message);
            $messageFormatted = str_replace('{total_balance}', number_format($totalBalance, 2), $messageFormatted);
            
            dispatch(new \App\Jobs\SimpleSendSmsJob($customer->telephone, $messageFormatted));
        }
    }
}
```

### 9.4 Deliverable
✅ SMS messages show meter-specific balances

---

## 📅 PHASE 11: REPORTS & STATEMENTS (Week 7)
**Goal:** Update reports to support meter-level analysis

### 10.1 Create Meter Statement Command

```bash
php artisan make:command GenerateMeterStatement
```

**Features:**
- Accept meter_id and date range parameters
- Generate statement PDF
- Show all transactions (invoices, payments)
- Display opening balance, closing balance

### 10.2 Create Meter Financial Report Page

```bash
php artisan make:filament-page MeterFinancialReport
```

**Location:** `app/Filament/Tenant/Pages/Reports/MeterFinancialReport.php`

**Widgets to show:**
- Total active meters
- Total outstanding balance (all meters)
- Total overpayments (all meters)
- Top 10 meters by balance
- Payment collection rate
- Aging analysis per meter

### 10.3 Update Customer Statement

**File:** `app/Filament/Tenant/Resources/CustomerStatementResource/Pages/ViewCustomerStatement.php`

**Add options:**
```php
Forms\Components\Radio::make('view_mode')
    ->label('Statement View')
    ->options([
        'consolidated' => 'Consolidated (All Meters Combined)',
        'per_meter' => 'Per Meter Breakdown',
        'specific_meter' => 'Specific Meter Only',
    ])
    ->default('per_meter')
    ->reactive(),

Forms\Components\Select::make('meter_id')
    ->label('Select Meter')
    ->options(fn($get) => 
        \App\Models\MeterAssignment::where('customer_id', $this->record->id)
            ->with('meter')
            ->get()
            ->mapWithKeys(fn($a) => [$a->meter_id => $a->meter->meter_number])
    )
    ->visible(fn($get) => $get('view_mode') === 'specific_meter'),
```

### 10.4 Update Aging Report

**Show meters instead of just customers:**
```php
// Group by meter
$agingData = \App\Models\Meter::whereHas('currentAssignment')
    ->with('currentAssignment.customer')
    ->where('balance', '>', 0)
    ->get()
    ->map(function($meter) {
        $oldestInvoice = $meter->invoices()
            ->where('state', 'open')
            ->orderBy('invoice_date')
            ->first();
        
        return [
            'meter_number' => $meter->meter_number,
            'customer_name' => $meter->currentAssignment->customer->name,
            'balance' => $meter->balance,
            'oldest_invoice_date' => $oldestInvoice?->invoice_date,
            'days_overdue' => $oldestInvoice ? 
                now()->diffInDays($oldestInvoice->due_date) : 0,
        ];
    })
    ->sortByDesc('days_overdue');
```

### 10.5 Deliverable
✅ Reports support meter-level analysis

---

## 📅 PHASE 12: TESTING & VALIDATION (Week 7-8)
**Goal:** Comprehensive testing before production deployment

### 11.1 Unit Tests

**Create test files:**
```bash
php artisan make:test MeterFinancialServiceTest --unit
php artisan make:test MeterBalanceCalculationTest --unit
```

**Test cases:**
- [ ] Balance calculations
- [ ] Overpayment application
- [ ] Payment recording
- [ ] Invoice recording
- [ ] Reversal operations
- [ ] Customer aggregation
- [ ] Edge cases (negative amounts, null values)

### 11.2 Feature Tests

```bash
php artisan make:test MeterFinancialIntegrationTest
php artisan make:test PaymentToMeterFlowTest
```

**Test scenarios:**
- [ ] Complete flow: Invoice → Payment → Balance Update
- [ ] Multi-meter customer payment allocation
- [ ] Payment reversal impact
- [ ] Overpayment credit application
- [ ] Meter transfer between customers

### 11.3 Data Validation

```bash
php artisan validate:meter-financial-data
```

**Run on staging with production copy:**
- [ ] Sum of meter balances = sum of customer balances (pre-migration)
- [ ] All invoices have meter_id
- [ ] All payments have meter_id
- [ ] No orphaned records
- [ ] Balance reconciliation

### 11.4 UI Testing

**Manual test checklist:**
- [ ] View customer with multiple meters
- [ ] Record payment to specific meter
- [ ] Generate invoice for meter
- [ ] View meter financial summary
- [ ] Export meter statement
- [ ] Verify meter balance colors
- [ ] Test payment form meter selection

### 11.5 Performance Testing

**Load tests:**
- [ ] Customer list with 1000+ customers
- [ ] Payment recording speed
- [ ] Invoice generation speed
- [ ] Report generation
- [ ] Database query optimization

### 11.6 Deliverable
✅ All tests passing, ready for production

---

## 📅 PHASE 13: DEPLOYMENT (Week 8)
**Goal:** Safe production deployment

### 12.1 Pre-Deployment Checklist

```
□ All unit tests passing
□ All feature tests passing
□ Code reviewed and approved
□ Database backup created
□ Rollback plan documented
□ Team trained on new features
□ User documentation updated
□ Monitoring alerts configured
□ Staging validation complete
□ Stakeholder sign-off received
```

### 12.2 Deployment Steps

```bash
# 1. Enable maintenance mode
php artisan down --message="System upgrade in progress" --retry=60

# 2. Backup database
# (Use your backup tool/script)

# 3. Pull latest code
git pull origin main

# 4. Install dependencies
composer install --no-dev --optimize-autoloader

# 5. Run migrations
php artisan migrate --force

# 6. Run data migration (critical step)
php artisan migrate:customer-balances-to-meters --force

# 7. Validate data migration
php artisan validate:meter-financial-data

# 8. Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 9. Restart queue workers
php artisan queue:restart

# 10. Disable maintenance mode
php artisan up
```

### 12.3 Post-Deployment Validation

**Immediate checks (within 1 hour):**
- [ ] Verify sum of meter balances = sum of customer balances
- [ ] Record 2-3 test payments
- [ ] Generate 1-2 test invoices
- [ ] Verify SMS messages
- [ ] Check error logs
- [ ] Test customer lookup
- [ ] Verify UI loading performance

**24-hour monitoring:**
- [ ] Monitor error rates
- [ ] Check payment recording success rate
- [ ] Verify invoice generation
- [ ] Check SMS delivery
- [ ] Monitor database performance
- [ ] Review user feedback

### 12.4 Rollback Plan

**If critical issues occur:**
```bash
# 1. Enable maintenance mode
php artisan down

# 2. Revert code
git checkout <previous-commit-hash>

# 3. Restore database from backup
# (Use your restore tool/script)

# 4. Clear caches
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 5. Restart queue workers
php artisan queue:restart

# 6. Disable maintenance mode
php artisan up

# 7. Document issues for investigation
```

### 12.5 User Communication

**Email/SMS to users:**
```
Subject: System Upgrade - New Meter-Level Billing Features

Dear valued customer,

We've upgraded our billing system with the following improvements:

✓ Individual balance tracking per meter
✓ Clearer payment allocation
✓ Meter-specific statements
✓ Enhanced payment receipts

When making payments, please select which meter you're paying for.

For assistance, contact [support details].

Thank you!
```

### 12.6 Deliverable
✅ System live in production with meter-centric billing

---

## 📅 PHASE 14: POST-DEPLOYMENT (Week 8+)
**Goal:** Monitor, optimize, and cleanup

### 13.1 Week 1 Monitoring

- [ ] Daily error log review
- [ ] User feedback collection
- [ ] Performance monitoring
- [ ] Data accuracy spot checks
- [ ] Support ticket tracking

### 13.2 Performance Optimization

**If needed:**
- [ ] Add missing database indexes
- [ ] Optimize slow queries
- [ ] Cache aggregated customer balances
- [ ] Optimize report queries
- [ ] Review N+1 query issues

### 13.3 Documentation Updates

- [ ] Update SYSTEM_DOCUMENTATION.md
- [ ] Update BUSINESS_USER_GUIDE.md
- [ ] Create API documentation
- [ ] Document MeterFinancialService
- [ ] Create troubleshooting guide
- [ ] Add developer notes

### 13.4 Future Cleanup (3-6 months)

**After stable operation:**
```bash
# Create migration to remove deprecated fields
php artisan make:migration remove_deprecated_customer_balance_fields
```

```php
// In migration
Schema::table('users', function (Blueprint $table) {
    $table->dropColumn(['balance', 'overpayment']);
});
```

**Remove backward compatibility code:**
- Remove MeterFinancialHelper
- Remove sync methods
- Update all services to use meters directly

### 13.5 Deliverable
✅ Optimized, documented, production-ready system

---

## 🎯 SUCCESS METRICS

### Technical Metrics
- ✅ 100% of invoices have meter_id
- ✅ 100% of payments have meter_id
- ✅ Data integrity: Σ(meter.balance) = original Σ(customer.balance)
- ✅ Zero data loss during migration
- ✅ All tests passing (unit, feature, integration)
- ✅ Page load time < 2 seconds
- ✅ Payment recording time < 3 seconds

### Business Metrics
- ✅ Users can identify balance per meter
- ✅ Payment allocation clarity improved
- ✅ Invoice generation accuracy maintained
- ✅ SMS delivery rate maintained
- ✅ Support tickets about balance confusion reduced by 50%
- ✅ Accounting reconciliation time reduced

---

## 🚨 RISK MATRIX

| Risk | Impact | Likelihood | Mitigation |
|------|---------|------------|------------|
| Data loss during migration | Critical | Low | Full backups, validation, rollback plan |
| Performance degradation | High | Medium | Indexes, caching, load testing |
| User confusion | Medium | High | Training, clear UI, documentation |
| Incorrect balance calculation | Critical | Low | Extensive testing, validation scripts |
| SMS formatting errors | Low | Medium | Template testing, fallback messages |
| Multi-meter customer issues | High | Medium | Thorough testing, manual review |

---

## 📞 TEAM & COMMUNICATION

### Recommended Team
- **Backend Developer(s):** Services, migrations
- **Frontend Developer:** UI/Filament
- **QA Tester:** Test execution
- **Product Owner:** Requirements, UAT
- **DBA/DevOps:** Performance, deployment

### Communication Plan
- Daily standups during development
- Weekly demos of progress
- UAT session after Phase 7
- Go/No-Go meeting before deployment
- Post-deployment daily check-ins (Week 1)

---

## 📋 QUICK REFERENCE: FILES TO MODIFY

### Priority 1 - Critical
- [ ] `database/migrations/xxxx_add_financial_fields_to_meters_table.php`
- [ ] `database/migrations/xxxx_add_indexes_for_meter_financials.php`
- [ ] `app/Services/MeterFinancialService.php`
- [ ] `app/Console/Commands/MigrateCustomerBalancesToMeters.php`
- [ ] `app/Console/Commands/BackfillPaymentMeterIds.php`
- [ ] `app/Console/Commands/ValidateMeterFinancialData.php`

### Priority 2 - Core Services
- [ ] `app/Services/Invoice/InvoiceService.php`
- [ ] `app/Services/Payment/PaymentService.php`
- [ ] `app/Services/CustomerPaymentService.php`
- [ ] `app/Services/Payment/PaymentReversalService.php`

### Priority 3 - Models
- [ ] `app/Models/Meter.php`
- [ ] `app/Models/User.php`
- [ ] `app/Helpers/MeterFinancialHelper.php`

### Priority 4 - UI
- [ ] `app/Filament/Tenant/Resources/MeterResource.php`
- [ ] `app/Filament/Tenant/Resources/CustomerResource.php`
- [ ] `app/Filament/Tenant/Resources/CustomerResource/RelationManagers/MetersRelationManager.php`
- [ ] Payment action forms

### Priority 5 - Communications
- [ ] SMS templates in InvoiceService
- [ ] SMS templates in PaymentService
- [ ] Bulk SMS functionality

---

## 🎓 LESSONS & BEST PRACTICES

1. **Always backup before migration**
2. **Validate data at every step**
3. **Maintain backward compatibility during transition**
4. **Test with production copy in staging**
5. **Monitor closely post-deployment**
6. **Document everything**
7. **Communicate changes clearly to users**
8. **Plan for rollback scenarios**
9. **Incremental deployment reduces risk**
10. **User training is as important as code changes**

---

## 📚 ADDITIONAL RESOURCES

- Laravel Documentation: https://laravel.com/docs
- Filament Documentation: https://filamentphp.com/docs
- Database Indexing Best Practices
- Testing Strategy Guide
- Project SYSTEM_DOCUMENTATION.md
- Project BUSINESS_USER_GUIDE.md

---

**Document Version:** 1.0
**Last Updated:** 2025-10-08
**Status:** Ready for Review and Implementation

---

**Ready to begin implementation? Start with Phase 1!**
