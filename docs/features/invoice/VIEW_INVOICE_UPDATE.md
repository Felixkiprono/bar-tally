# View Invoice Page - Financial Breakdown Update

**Date:** October 16, 2025  
**Status:** ✅ IMPLEMENTED

---

## Overview

Updated the View Invoice page (`ViewInvoice.php`) to display comprehensive financial breakdown with all new invoice fields, showing clear separation between balance brought forward, current charges, credits, payments, and outstanding balance.

---

## Changes Made

### Before: Simple Financial Summary
```
Financial Summary (3 fields)
├─ Total Amount: KES X
├─ Paid Amount: KES Y  
└─ Balance: KES Z
```

### After: Detailed 3-Section Breakdown

#### **Section 1: Invoice Breakdown**
Shows how the invoice total is composed:

| Field | Description | Color |
|-------|-------------|-------|
| **Balance Brought Forward** | Outstanding balance from previous invoices | Warning (orange) |
| **Bill Amount** | Current period charges | Info (blue) |
| **Total Amount** | B/F + Bill Amount (Large, bold) | Primary (blue) |

#### **Section 2: Payment Breakdown**
Shows all payments applied to the invoice:

| Field | Description | Color | Calculation |
|-------|-------------|-------|-------------|
| **Credit Applied** | Overpayment credit at creation | Info (blue) | `paid_amount - payments()->sum()` |
| **Payments Made** | Payments received after creation | Success (green) | `payments()->sum('amount')` |
| **Total Paid** | Credit + Payments (Large, bold) | Success (green) | `paid_amount` |

#### **Section 3: Balance Due**
Prominent display of outstanding balance:

| Field | Description | Color | Size |
|-------|-------------|-------|------|
| **Outstanding Balance** | Amount still due | Red (outstanding) / Green (paid) | Large, extra bold |

---

## Visual Layout

```
┌────────────────────────────────────────────────────────┐
│ Invoice Information                                     │
│ ├─ Invoice Number  │ Status         │ Invoice Date    │
│ ├─ Due Date        │ Created At     │ Last Updated    │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│ Invoice Breakdown                                       │
│ Detailed financial breakdown of this invoice            │
│                                                         │
│ Balance Brought Forward    Bill Amount      Total      │
│ KES 1,500.00              KES 2,000.00   KES 3,500.00 │
│ (warning)                 (info)          (primary)    │
│ Previous outstanding      Current charges  B/F + Bill  │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│ Payment Breakdown                                       │
│ Credits and payments applied to this invoice            │
│                                                         │
│ Credit Applied      Payments Made      Total Paid      │
│ KES 200.00         KES 300.00         KES 500.00       │
│ (info)             (success)          (success)        │
│ Overpayment credit  Payments received  Credit + Payments│
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│ Balance Due                                             │
│                                                         │
│ Outstanding Balance: KES 3,000.00 (RED - large)        │
│ Amount still outstanding                                │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│ Customer & Meter Information                            │
│ [Customer details and meter details...]                 │
└────────────────────────────────────────────────────────┘
```

---

## Key Features

### 1. **Clear Financial Flow**
Shows the complete financial story:
1. What was owed before (Balance B/F)
2. What is owed now (Bill Amount)
3. What was paid (Credit + Payments)
4. What remains (Balance Due)

### 2. **Helper Text**
Each field has descriptive helper text explaining what it represents.

### 3. **Color Coding**
- **Warning (orange)**: Balance brought forward (previous debt)
- **Info (blue)**: Current period charges & credits
- **Success (green)**: Payments & fully paid status
- **Danger (red)**: Outstanding balance
- **Primary (blue)**: Totals

### 4. **Calculated Fields**
- **Credit Applied**: Automatically calculated as `paid_amount - payments()->sum()`
- **Payments Made**: Sum of all payment records for this invoice
- **Total Paid**: Direct from `paid_amount` field

### 5. **Size & Weight Emphasis**
- Important totals (Total Amount, Total Paid) are **Large** and **ExtraBold**
- Balance Due is most prominent with **Large** size

---

## Example Scenarios

### Scenario 1: New Invoice with Balance B/F
```
Invoice Breakdown:
├─ Balance Brought Forward: KES 1,500.00 (previous debt)
├─ Bill Amount: KES 2,000.00 (current charges)
└─ Total Amount: KES 3,500.00

Payment Breakdown:
├─ Credit Applied: KES 0.00 (no overpayment)
├─ Payments Made: KES 0.00 (no payments yet)
└─ Total Paid: KES 0.00

Balance Due: KES 3,500.00 (RED - outstanding)
```

### Scenario 2: Invoice with Overpayment Credit
```
Invoice Breakdown:
├─ Balance Brought Forward: KES 0.00
├─ Bill Amount: KES 1,000.00
└─ Total Amount: KES 1,000.00

Payment Breakdown:
├─ Credit Applied: KES 500.00 (overpayment at creation)
├─ Payments Made: KES 0.00
└─ Total Paid: KES 500.00

Balance Due: KES 500.00 (RED - outstanding)
```

### Scenario 3: Fully Paid Invoice
```
Invoice Breakdown:
├─ Balance Brought Forward: KES 500.00
├─ Bill Amount: KES 1,000.00
└─ Total Amount: KES 1,500.00

Payment Breakdown:
├─ Credit Applied: KES 200.00
├─ Payments Made: KES 1,300.00
└─ Total Paid: KES 1,500.00

Balance Due: KES 0.00 (GREEN - Invoice fully paid)
```

### Scenario 4: Cleared Invoice (Balance Carried Forward)
```
Invoice Breakdown:
├─ Balance Brought Forward: KES 0.00
├─ Bill Amount: KES 1,000.00
└─ Total Amount: KES 1,000.00

Payment Breakdown:
├─ Credit Applied: KES 0.00
├─ Payments Made: KES 0.00
└─ Total Paid: KES 1,000.00 (automatically set when cleared)

Balance Due: KES 0.00 (GREEN - Invoice fully paid)
Status: "Cleared" - Balance carried forward to new invoice
```

---

## Technical Details

### File Modified
`app/Filament/Tenant/Resources/InvoiceResource/Pages/ViewInvoice.php`

### Sections Added
1. `Invoice Breakdown` - Replaces "Financial Summary"
2. `Payment Breakdown` - New section
3. `Balance Due` - Prominent display

### Dynamic Calculations
```php
// Credit Applied (overpayment at creation)
$creditApplied = max(0, $record->paid_amount - $record->payments()->sum('amount'));

// Payments Made (after creation)
$paymentsMade = $record->payments()->sum('amount');

// Total Paid
$totalPaid = $record->paid_amount; // Includes both credit and payments
```

### Color Logic
```php
// Balance Due color
color(fn($state): string => $state > 0 ? 'danger' : 'success')

// Helper text for balance
helperText(fn($state): string => $state > 0 ? 'Amount still outstanding' : 'Invoice fully paid')
```

---

## Benefits

### For Users
✅ **Complete visibility** into invoice composition  
✅ **Clear understanding** of what was owed vs. what is current  
✅ **Transparent payment history** showing credits vs. actual payments  
✅ **Immediate status** of invoice with color coding

### For Admins
✅ **Easy troubleshooting** - can see exactly where amounts come from  
✅ **Audit trail** - complete breakdown of all financial components  
✅ **Customer support** - can explain charges clearly to customers

### For Reconciliation
✅ **Matches table display** - consistent breakdown across list and detail views  
✅ **Verifiable** - each amount can be traced to its source  
✅ **Clear** - no confusion about what "paid amount" includes

---

## Related Updates

This update complements the invoice table display updates:
- [Invoice Display Update](./INVOICE_DISPLAY_UPDATE.md) - Table columns
- [Bill Consolidation](./BILL_CONSOLIDATION_SIMPLE_APPROACH.md) - How invoices are generated
- [Invoice Tests](./tests/INVOICE_TESTS.md) - Test coverage

---

**Status:** ✅ Ready for Production  
**Tested:** No linter errors  
**Consistent:** Matches table display breakdown  
**User-Friendly:** Clear labels and helper text


