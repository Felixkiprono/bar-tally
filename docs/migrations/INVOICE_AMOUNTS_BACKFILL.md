# Invoice Amounts Backfill Command

## Overview

This command backfills invoice `amount`, `balance_brought_forward`, and `total_amount` fields based on the actual bills linked to each invoice through the `invoice_bills` table.

## Problem Statement

Invoices should have:
- `amount`: Sum of all non-BALANCE bill amounts
- `balance_brought_forward`: Sum of all BALANCE bill amounts (balances carried over from previous invoices)
- `total_amount`: Sum of ALL bill amounts (should equal `amount + balance_brought_forward`)

This command recalculates all three values directly from the invoice bills to ensure complete data integrity.

## Usage

### Dry Run (Preview Changes)
```bash
php artisan backfill:invoice-amounts --dry-run
```

This will show you what changes would be made without actually updating the database.

### Apply Changes
```bash
php artisan backfill:invoice-amounts
```

This will update all invoices with incorrect amounts.

## How It Works

### Calculation Logic

For each invoice:
1. **Calculate Amount**: Sum all `invoice_bills.amount` where the related bill's `bill_type != 'BALANCE'`
2. **Calculate Balance Brought Forward**: Sum all `invoice_bills.amount` where the related bill's `bill_type = 'BALANCE'`
3. **Calculate Total Amount**: Sum ALL `invoice_bills.amount` (should equal `amount + balance_brought_forward`)
4. **Update Invoice**: Set all three calculated values directly

### Edge Cases Handled

1. **Invoices with no bills**: 
   - `amount` = 0
   - `balance_brought_forward` = 0
   - `total_amount` = 0

2. **Invoices with only BALANCE bills**:
   - `amount` = 0
   - `balance_brought_forward` = sum of BALANCE bills
   - `total_amount` = sum of BALANCE bills

3. **Invoices with only non-BALANCE bills**:
   - `amount` = sum of non-BALANCE bills
   - `balance_brought_forward` = 0
   - `total_amount` = sum of non-BALANCE bills

4. **Discrepancies**: 
   - Where calculated `amount + balance_brought_forward` doesn't equal calculated `total_amount`
   - These are logged and reported for manual review (indicates data integrity issues)

## Output

The command provides:

### Progress Bar
Shows real-time progress of invoice processing

### Summary Table
```
┌───────────────────────┬───────┐
│ Metric                │ Count │
├───────────────────────┼───────┤
│ Total Invoices        │ 1000  │
│ Updated               │ 150   │
│ Already Correct       │ 840   │
│ No Bills Found        │ 5     │
│ Discrepancies Found   │ 5     │
└───────────────────────┴───────┘
```

### Discrepancy Report
If discrepancies are found (where amount + BBF doesn't equal total), they are displayed:

```
┌───────────┬─────────┬─────────┬─────────┬─────────┬───────────┬───────────┬──────┐
│ Invoice   │ Curr    │ Calc    │ Curr    │ Calc    │ Curr      │ Calc      │ Diff │
│           │ Amt     │ Amt     │ BBF     │ BBF     │ Total     │ Total     │      │
├───────────┼─────────┼─────────┼─────────┼─────────┼───────────┼───────────┼──────┤
│ INV-001   │ 100.00  │ 95.00   │ 50.00   │ 50.00   │ 150.00    │ 145.00    │ 5.00 │
└───────────┴─────────┴─────────┴─────────┴─────────┴───────────┴───────────┴──────┘
```

## Logging

All operations are logged to `storage/logs/laravel.log` including:

- Invoices with no bills
- Discrepancies found
- All updates performed (with before/after values)

## Performance

- Processes invoices in chunks of 500 for memory efficiency
- Uses database transactions for data integrity
- Eager loads relationships to minimize queries

## When to Run

This command should be run:
- After importing legacy data
- After any bulk bill operations
- If you suspect invoice amounts are incorrect
- As part of data validation/cleanup operations

## Safety Features

- **Dry-run mode**: Preview changes before applying
- **Transaction-based updates**: Ensures data integrity
- **Detailed logging**: Full audit trail of all changes
- **Discrepancy detection**: Identifies potential data issues
- **No automatic fixes for discrepancies**: Requires manual review

## Example Usage

```bash
# 1. First, run in dry-run mode to see what would change
php artisan backfill:invoice-amounts --dry-run

# 2. Review the output and logs

# 3. Apply the changes
php artisan backfill:invoice-amounts

# 4. Review the results and check logs for any discrepancies
```

## Related Commands

- `backfill:payment-meter-ids`: Should be run before balance calculations
- `validate:meter-financial-data`: Validates meter financial data after backfill

## Technical Details

### Database Updates
- Updates performed directly via query builder to avoid model event triggers
- Updates `amount`, `balance_brought_forward`, and `total_amount` fields
- `balance` is not updated by this command (it depends on `paid_amount` which is tracked separately)
- All three values are recalculated from scratch based on invoice bills

### Bill Type Filtering
The command separates bills by type for accurate calculation:

**Non-BALANCE bills** (for `amount` field):
- Represent new charges (METER_READING, CONNECTION_FEE, SERVICE_FEE, etc.)
- Should be reflected in the `amount` field
- Represents current billing period charges

**BALANCE bills** (for `balance_brought_forward` field):
- Represent carried-over balances from previous invoices
- Should be reflected in `balance_brought_forward`, not `amount`
- Maintains accounting separation between new charges and old balances

**ALL bills** (for `total_amount` field):
- Sum of everything on the invoice
- Should equal `amount + balance_brought_forward`
- Represents total amount customer owes on this invoice

