# Meter-Centric Billing Migration Flow

## Overview

The `migrate:meter-financials` command is the master orchestrator for migrating from customer-centric to meter-centric billing. It runs all necessary commands in the correct order to ensure data integrity.

## Migration Steps

### Step 1: Backfill Payment meter_ids
**Command**: `backfill:payment-meter-ids`

**Purpose**: Ensures all payments have a `meter_id` assigned
- Payments with `invoice_id`: Get meter_id from invoice
- Payments without invoice_id: Assigned to customer's active meter
- Multi-meter customers: Payments assigned to first active meter (logged for review)

**Why First**: Payments need meter_ids before we can calculate meter balances

---

### Step 2: Backfill Invoice Amounts
**Command**: `backfill:invoice-amounts`

**Purpose**: Recalculates invoice financial fields from invoice bills
- `amount`: Sum of non-BALANCE bills
- `balance_brought_forward`: Sum of BALANCE bills  
- `total_amount`: Sum of ALL bills

**Why Second**: Ensures invoice amounts are correct before using them in balance calculations

**Key Benefits**:
- Fixes any discrepancies in invoice amounts
- Ensures `amount + balance_brought_forward = total_amount`
- Validates bill-to-invoice relationships

---

### Step 3: Validate Payments (Optional)
**Command**: `validate:meter-financial-data --check=payments`

**Purpose**: Verifies all payments have meter_id assigned
- Reports any payments still missing meter_id
- Migration aborts if validation fails (unless dry-run)

**Skip With**: `--skip-validation` flag

---

### Step 4: Calculate Meter Balances
**Command**: `migrate:customer-balances-to-meters`

**Purpose**: Recalculates meter balances from transaction history
- Processes invoices, payments, and overpayments
- Updates `meters.balance` and `meters.overpayment`
- Backs up customer balances before migration

**Why Fourth**: Requires correct payment meter_ids and invoice amounts from previous steps

---

### Step 5: Full Validation (Optional)
**Command**: `validate:meter-financial-data`

**Purpose**: Comprehensive validation of all financial data
- Validates payments have meter_ids
- Validates invoices have meter_ids
- Validates meter balances match transaction totals
- Reports discrepancies for review

**Skip With**: `--skip-validation` flag

---

## Usage

### Preview Changes (Recommended)
```bash
php artisan migrate:meter-financials --dry-run
```

This runs all steps in dry-run mode to show what would be changed without actually modifying data.

### Apply Migration
```bash
php artisan migrate:meter-financials
```

Runs all steps and applies changes. Prompts for confirmation before starting.

### Skip Validation Steps
```bash
php artisan migrate:meter-financials --skip-validation
```

Skips steps 3 and 5 (validation) - useful if you've already validated or want faster execution.

### Combined Options
```bash
php artisan migrate:meter-financials --dry-run --skip-validation
```

Dry-run without validation steps (fastest preview).

---

## Data Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    MIGRATION DATA FLOW                      │
└─────────────────────────────────────────────────────────────┘

Step 1: Backfill Payment meter_ids
   ↓
   payments.meter_id populated
   ↓
Step 2: Backfill Invoice Amounts
   ↓
   invoices.amount, balance_brought_forward, total_amount corrected
   ↓
Step 3: Validate Payments (optional)
   ↓
   Confirm all payments have meter_id
   ↓
Step 4: Calculate Meter Balances
   ↓
   meters.balance and meters.overpayment calculated
   customer balances backed up
   ↓
Step 5: Full Validation (optional)
   ↓
   Verify complete data integrity
   ↓
   MIGRATION COMPLETE
```

---

## Order Importance

The steps **must** run in this order because:

1. **Payment meter_ids first**: Needed before calculating meter balances
2. **Invoice amounts second**: Ensures accurate invoice totals before balance calculations
3. **Validation third**: Confirms prerequisites are met
4. **Meter balances fourth**: Uses corrected data from steps 1-2
5. **Final validation fifth**: Verifies complete migration success

---

## Error Handling

- **Dry-run mode**: Continues through all steps even if errors occur (to show full impact)
- **Live mode**: Aborts immediately on any step failure
- **Validation failures**: Treated as errors (abort migration)
- **Discrepancies**: Logged but may allow continuation depending on severity

---

## Output

The command provides:

1. **Progress indicators**: Clear step-by-step progress
2. **Step results**: Success/failure for each step
3. **Duration tracking**: Total time taken
4. **Next steps guide**: Post-migration tasks to verify
5. **Backup reference**: Location of customer balance backup

### Example Output

```
╔════════════════════════════════════════════════════════╗
║   METER-CENTRIC BILLING MIGRATION                      ║
╚════════════════════════════════════════════════════════╝

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 1/5: Backfilling payment meter_ids...
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[Progress output...]
✓ Step 1 complete!

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STEP 2/5: Backfilling invoice amounts...
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[Progress output...]
✓ Step 2 complete!

[...continues through all steps...]

╔════════════════════════════════════════════════════════╗
║   MIGRATION COMPLETE                                   ║
╚════════════════════════════════════════════════════════╝

✓ Migration completed successfully in 45 seconds

Next steps:
  1. Test invoice generation (verify meter balance increases)
  2. Test payment recording (verify meter balance decreases)
  3. Test Quick Payment with meter selection
  4. Verify SMS messages show meter-specific balances
  5. Check customer statements show correct per-meter data

Backup stored in: customer_balances_backup table
```

---

## Rollback

If migration fails or produces unexpected results:

1. **Customer balances**: Restored from `customer_balances_backup` table
2. **Meter balances**: Can be recalculated by re-running step 4
3. **Payment meter_ids**: Can be re-backfilled by re-running step 1
4. **Invoice amounts**: Can be re-backfilled by re-running step 2

Individual commands can be re-run independently for targeted fixes.

---

## Related Commands

- `backfill:payment-meter-ids` - Run step 1 independently
- `backfill:invoice-amounts` - Run step 2 independently
- `migrate:customer-balances-to-meters` - Run step 4 independently
- `validate:meter-financial-data` - Run validation independently

---

## Best Practices

1. **Always dry-run first**: `--dry-run` to preview changes
2. **Review logs**: Check `storage/logs/laravel.log` for details
3. **Backup database**: Take database backup before running
4. **Test environment first**: Run on staging/test before production
5. **Validate after**: Run validation command post-migration
6. **Test functionality**: Follow the "Next steps" checklist in output

---

## Troubleshooting

### Step 1 Fails
- Check for payments with missing customers
- Review logs for multi-meter customer warnings
- Verify customer meter assignments

### Step 2 Fails
- Check for invoices with no bills
- Review discrepancy report for data issues
- Verify invoice_bills relationships

### Step 4 Fails
- Ensure steps 1-2 completed successfully
- Check for missing transaction data
- Verify tenant_id consistency

### Validation Fails
- Review specific validation errors
- Check data relationships (customer-meter-invoice-payment)
- Verify meter assignments are active

