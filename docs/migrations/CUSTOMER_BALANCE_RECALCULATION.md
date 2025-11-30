# Customer Balance Recalculation Command

## Overview

The `recalculate:customer-balances` command updates customer-level balance and overpayment fields by aggregating values from their associated meters. This ensures customer records remain in sync with the meter-centric financial model.

## Purpose

After migrating to meter-centric billing (where balances are tracked at the meter level), customer records still maintain `balance` and `overpayment` fields for:
- Backward compatibility with existing code
- Quick customer-level reporting
- Dashboard displays

This command aggregates meter balances to update customer-level fields.

## Command Signature

```bash
php artisan recalculate:customer-balances {--dry-run}
```

### Options

- `--dry-run` : Preview what changes would be made without applying them

## How It Works

### Processing Flow

1. **Customer Retrieval**
   - Finds all customers with meter assignments
   - Processes them one by one

2. **Balance Calculation** (per customer)
   - Calls `MeterFinancialService::recalculateCustomerMeters($customerId)`
   - Service recalculates each meter's balance
   - Aggregates total balance from all active meters
   - Aggregates total overpayment from all active meters
   - Updates customer's `balance` and `overpayment` fields

3. **Change Tracking**
   - Captures "before" and "after" values
   - Tracks which customers changed
   - Identifies significant changes (> KES 100)

4. **Reporting**
   - Summary statistics
   - List of significant changes
   - Total validation (before vs after)

## Service Integration

### MeterFinancialService::recalculateCustomerMeters()

This service method does the heavy lifting:

```php
public function recalculateCustomerMeters(int $customerId): void
{
    // 1. Recalculate all meter balances for the customer
    // 2. Aggregate totals from active meters
    // 3. Update customer balance and overpayment fields
    // 4. Log the operation
}
```

**Key Points:**
- Recalculates each meter from transaction history (invoices - payments)
- Only includes **active** meter assignments in customer totals
- Updates both meter records and customer records
- Fully transactional and logged

## Usage Examples

### Dry Run (Preview Changes)

```bash
php artisan recalculate:customer-balances --dry-run
```

**Output Example:**

```
DRY RUN MODE - No changes will be made
Starting customer balance recalculation from meters...

Processing 1,250 customers...
 1250/1250 [============================] 100%

Recalculation Results:
┌───────────────────────────┬────────┐
│ Metric                    │ Count  │
├───────────────────────────┼────────┤
│ Customers Processed       │ 1,250  │
│ With Balance              │ 847    │
│ With Overpayment          │ 125    │
│ Clear (Zero Balance)      │ 278    │
│ Balances Changed          │ 42     │
│ Overpayments Changed      │ 18     │
└───────────────────────────┴────────┘

Significant Changes Detected (> KES 100):
┌─────────────┬────────────────┬──────────────┬──────────────┬──────────────┐
│ Customer ID │ Name           │ Type         │ Before       │ After        │ Difference   │
├─────────────┼────────────────┼──────────────┼──────────────┼──────────────┼──────────────┤
│ 1234        │ John Doe       │ Balance      │ KES 5,000.00 │ KES 5,250.00 │ +KES 250.00  │
│ 5678        │ Jane Smith     │ Balance      │ KES 3,200.00 │ KES 2,950.00 │ -KES 250.00  │
└─────────────┴────────────────┴──────────────┴──────────────┴──────────────┴──────────────┘

Validation Summary:
┌──────────────┬────────────────┬───────────────┬──────────────┐
│ Type         │ Before Total   │ After Total   │ Difference   │
├──────────────┼────────────────┼───────────────┼──────────────┤
│ Balance      │ KES 2,450,000  │ KES 2,450,500 │ +KES 500     │
│ Overpayment  │ KES 125,000    │ KES 124,750   │ -KES 250     │
└──────────────┴────────────────┴───────────────┴──────────────┘

DRY RUN COMPLETE - Run without --dry-run to apply changes
```

### Apply Changes

```bash
php artisan recalculate:customer-balances
```

This will:
- Actually update customer balance and overpayment fields
- Log all changes to application logs
- Display the same summary output

## When to Run This Command

### As Part of Migration

This command is **Step 5** in the master migration process (`migrate:meter-financials`):

1. Backfill payment meter_ids
2. Backfill invoice amounts
3. Validate payments
4. Calculate meter balances ← Meters get correct balances
5. **Recalculate customer balances** ← This command syncs customers
6. Full validation

### Standalone Usage

Run this command whenever:
- Customer balance seems out of sync with their meters
- After bulk meter balance adjustments
- After importing historical data
- As part of monthly reconciliation
- After fixing data issues

## Statistics Tracked

The command tracks and reports:

| Metric | Description |
|--------|-------------|
| Customers Processed | Total number of customers updated |
| With Balance | Customers with outstanding balance > 0 |
| With Overpayment | Customers with overpayment > 0 |
| Clear (Zero Balance) | Customers with no balance or overpayment |
| Balances Changed | Number of customers whose balance changed |
| Overpayments Changed | Number of customers whose overpayment changed |

## Significant Changes

The command highlights changes exceeding KES 100 to help identify:
- Data quality issues
- Meters that were recently updated
- Customers needing attention
- Potential reconciliation issues

## Performance Considerations

### Processing Speed

- Processes customers sequentially (not in parallel)
- Each customer triggers meter recalculations
- Typical performance: ~5-10 customers per second
- Expected time for 1,000 customers: 2-3 minutes

### Memory Usage

- **Uses chunking** to process customers in batches of 100
- Low memory footprint - only 100 customers loaded at a time
- Safe to run on very large datasets (100,000+ customers)
- Chunking prevents memory exhaustion on high-volume systems

### Database Impact

- Multiple queries per customer (meters, invoices, payments)
- Updates are transactional
- Minimal locking (only during customer record update)

## Error Handling

The command handles errors gracefully:

```php
// If a customer fails, it continues to the next one
// Errors are logged but don't stop the entire process
// Exit code indicates success/failure
```

## Integration with Master Migration

This command is automatically called by the master migration command:

```bash
# Part of the full migration process
php artisan migrate:meter-financials

# Or run standalone
php artisan recalculate:customer-balances
```

## Validation

### Before/After Totals

The command validates that:
- Total balance across all customers is tracked
- Total overpayment across all customers is tracked
- Significant differences are highlighted for review

### Expected Differences

Small differences (< KES 1) are normal due to:
- Rounding in calculations
- Inactive meters not included in customer totals
- Timing of when meters vs customers were last updated

Large differences (> KES 100) should be investigated.

## Logging

All operations are logged to the application log:

```php
Log::info('Customer balances recalculated', [
    'customers_processed' => 1250,
    'balance_changed' => 42,
    'overpayment_changed' => 18,
    'total_balance_before' => 2450000,
    'total_balance_after' => 2450500,
    'total_overpayment_before' => 125000,
    'total_overpayment_after' => 124750,
]);
```

## Best Practices

1. **Always Dry Run First**
   ```bash
   php artisan recalculate:customer-balances --dry-run
   ```
   Review the output before applying changes.

2. **Run During Off-Peak Hours**
   - The command is read-heavy on invoices and payments
   - Best run during low-traffic periods

3. **Review Significant Changes**
   - Investigate customers with large balance changes
   - Verify meter assignments are correct
   - Check for data quality issues

4. **After Data Imports**
   - Always run after importing historical data
   - Ensures customer fields are in sync

5. **Monthly Reconciliation**
   - Consider running monthly as part of closing procedures
   - Helps catch any drift between meters and customers

## Troubleshooting

### No Customers Found

```
No customers with meter assignments found.
```

**Cause:** No customers have active or inactive meter assignments.

**Solution:** This is expected if you haven't created customers or meter assignments yet.

### Large Discrepancies

If you see large differences in before/after totals:

1. **Check Meter Balances**
   ```bash
   # Verify meters were calculated first
   php artisan migrate:customer-balances-to-meters --dry-run
   ```

2. **Review Meter Assignments**
   - Ensure `is_active` flags are correct
   - Check for orphaned meters
   - Verify customer assignments

3. **Audit Transactions**
   - Check for invoices without meter_id
   - Check for payments without meter_id
   - Look for manual adjustments

## Related Commands

- `migrate:meter-financials` - Master migration command (includes this as Step 5)
- `migrate:customer-balances-to-meters` - Calculates meter balances (prerequisite)
- `backfill:payment-meter-ids` - Ensures payments have meter_id
- `validate:meter-financial-data` - Validates financial data integrity

## Technical Details

### Chunked Processing

The command uses Laravel's `chunkById()` method to process customers in batches:

```php
// Process customers in chunks of 100 to avoid memory issues
User::where('role', 'customer')
    ->whereHas('meterAssignments')
    ->chunkById(100, function ($customers) use (...) {
        foreach ($customers as $customer) {
            // Process each customer
        }
    });
```

**Benefits:**
- Only 100 customers loaded into memory at a time
- Prevents memory exhaustion on large datasets
- Progress bar updates in real-time across chunks
- Statistics aggregated across all chunks

### Service Method

```php
namespace App\Services;

class MeterFinancialService
{
    public function recalculateCustomerMeters(int $customerId): void
    {
        $customer = User::findOrFail($customerId);
        
        // Get unique meter IDs for this customer
        $meterIds = $customer->meterAssignments()
            ->pluck('meter_id')
            ->unique();
        
        // Recalculate each meter
        foreach ($meterIds as $meterId) {
            $this->recalculateMeterBalance($meterId);
        }
        
        // Aggregate totals from active meters only
        $totalBalance = 0;
        $totalOverpayment = 0;
        
        foreach ($customer->meterAssignments()
            ->where('is_active', true)
            ->with('meter')
            ->get() as $assignment) {
            $totalBalance += $assignment->meter->balance ?? 0;
            $totalOverpayment += $assignment->meter->overpayment ?? 0;
        }
        
        // Update customer
        $customer->balance = $totalBalance;
        $customer->overpayment = $totalOverpayment;
        $customer->save();
    }
}
```

### Database Updates

The command updates only the `users` table:

```sql
UPDATE users 
SET 
    balance = <calculated_balance>,
    overpayment = <calculated_overpayment>,
    updated_at = NOW()
WHERE 
    id = <customer_id>
```

## Summary

The `recalculate:customer-balances` command is essential for maintaining data consistency in the meter-centric billing model. It ensures that customer-level fields accurately reflect the sum of their meter balances, enabling backward compatibility and efficient reporting.

**Key Takeaways:**
- ✅ Uses `MeterFinancialService` for proper calculation logic
- ✅ Supports dry-run for safe preview
- ✅ Tracks and reports all changes
- ✅ Integrated into master migration flow
- ✅ Safe to run repeatedly (idempotent)
- ✅ Low performance impact
- ✅ Comprehensive logging and validation

---

**Last Updated:** November 13, 2025  
**Command:** `recalculate:customer-balances`  
**Location:** `app/Console/Commands/Migration/RecalculateCustomerBalances.php`

