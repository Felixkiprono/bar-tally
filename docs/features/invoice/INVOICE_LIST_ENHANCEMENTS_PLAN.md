# Invoice List Enhancements Plan

## Overview
Add row selection, export functionality, and comprehensive filters to the invoice list table.

---

## 1. Row Selection

### Implementation
**Location:** `app/Filament/Tenant/Resources/InvoiceResource.php`

### Changes Needed:
```php
->bulkActions([
    Tables\Actions\BulkActionGroup::make([
        Tables\Actions\ExportBulkAction::make(),
        Tables\Actions\BulkAction::make('sendBulkSms')
            ->label('Send SMS to Selected')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('info')
            ->action(function (Collection $records) {
                // Send SMS to multiple invoices
            }),
    ]),
])
```

### Bulk Actions to Add:
1. **Export Selected** - Export only selected rows
2. **Send Bulk SMS** - Send SMS to customers of selected unpaid invoices
3. **Mark as Sent** - Bulk update status (if needed)

---

## 2. Export Functionality

### Option A: Use Filament Excel Plugin (Recommended)
**Package:** `pxlrbt/filament-excel`

**Features:**
- Excel export (.xlsx)
- CSV export
- PDF export (with additional config)
- Customizable columns
- Action/Bulk action support

**Installation:**
```bash
composer require pxlrbt/filament-excel
```

### Option B: Custom Export using Laravel Excel
**Package:** `maatwebsite/excel` (already installed)

**Create Export Class:**
```php
php artisan make:export InvoicesExport --model=Invoice
```

### Export Columns to Include:
1. Invoice Number
2. Invoice Date
3. Due Date
4. Customer Name
5. Customer Phone
6. Meter Number
7. Meter Location
8. Total Amount
9. Paid Amount
10. Balance
11. Status
12. Created At
13. Days Overdue (calculated)

### Export Actions:
1. **Header Action** - Export all filtered invoices
2. **Bulk Action** - Export selected invoices
3. **Format Options** - Excel (.xlsx), CSV (.csv)

---

## 3. Filters

### 3.1 Status Filter
**Type:** Multi-select
**Options:**
- Not Paid
- Paid
- Fully Paid
- Partial Payment
- Overdue
- Reversed
- Cancelled

**Implementation:**
```php
Tables\Filters\SelectFilter::make('status')
    ->label('Status')
    ->options([
        'not paid' => 'Not Paid',
        'paid' => 'Paid',
        'fully paid' => 'Fully Paid',
        'partial payment' => 'Partial Payment',
        'overdue' => 'Overdue',
        'reversed' => 'Reversed',
        'cancelled' => 'Cancelled',
    ])
    ->multiple()
```

### 3.2 Date Range Filters

#### Invoice Date Range
```php
Tables\Filters\Filter::make('invoice_date')
    ->form([
        Forms\Components\DatePicker::make('invoice_from')
            ->label('Invoice From'),
        Forms\Components\DatePicker::make('invoice_until')
            ->label('Invoice Until'),
    ])
    ->query(function (Builder $query, array $data): Builder {
        return $query
            ->when($data['invoice_from'], fn($q, $date) => $q->whereDate('invoice_date', '>=', $date))
            ->when($data['invoice_until'], fn($q, $date) => $q->whereDate('invoice_date', '<=', $date));
    })
    ->indicateUsing(function (array $data): array {
        $indicators = [];
        if ($data['invoice_from'] ?? null) {
            $indicators[] = 'From: ' . Carbon::parse($data['invoice_from'])->toFormattedDateString();
        }
        if ($data['invoice_until'] ?? null) {
            $indicators[] = 'Until: ' . Carbon::parse($data['invoice_until'])->toFormattedDateString();
        }
        return $indicators;
    })
```

#### Due Date Range
Similar implementation for due date filtering.

### 3.3 Customer Filter
```php
Tables\Filters\SelectFilter::make('customer_id')
    ->label('Customer')
    ->relationship('customer', 'name')
    ->searchable()
    ->preload()
```

### 3.4 Meter Filter
```php
Tables\Filters\SelectFilter::make('meter_id')
    ->label('Meter')
    ->relationship('meter', 'meter_number')
    ->searchable()
    ->preload()
```

### 3.5 Balance Filter
```php
Tables\Filters\Filter::make('balance')
    ->form([
        Forms\Components\Select::make('balance_type')
            ->label('Balance Type')
            ->options([
                'has_balance' => 'Has Outstanding Balance',
                'fully_paid' => 'Fully Paid (Zero Balance)',
                'overpaid' => 'Overpaid (Negative Balance)',
            ])
    ])
    ->query(function (Builder $query, array $data): Builder {
        return $query->when($data['balance_type'], function($q, $type) {
            return match($type) {
                'has_balance' => $q->where('balance', '>', 0),
                'fully_paid' => $q->where('balance', '=', 0),
                'overpaid' => $q->where('balance', '<', 0),
                default => $q,
            };
        });
    })
```

### 3.6 Overdue Filter
```php
Tables\Filters\TernaryFilter::make('overdue')
    ->label('Overdue Status')
    ->placeholder('All invoices')
    ->trueLabel('Overdue only')
    ->falseLabel('Not overdue')
    ->queries(
        true: fn (Builder $query) => $query
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['paid', 'fully paid', 'reversed', 'cancelled']),
        false: fn (Builder $query) => $query
            ->where(function($q) {
                $q->where('due_date', '>=', now())
                  ->orWhereIn('status', ['paid', 'fully paid', 'reversed', 'cancelled']);
            }),
    )
```

### 3.7 Amount Range Filter
```php
Tables\Filters\Filter::make('amount_range')
    ->form([
        Forms\Components\TextInput::make('amount_from')
            ->label('Amount From')
            ->numeric()
            ->prefix('KES'),
        Forms\Components\TextInput::make('amount_to')
            ->label('Amount To')
            ->numeric()
            ->prefix('KES'),
    ])
    ->query(function (Builder $query, array $data): Builder {
        return $query
            ->when($data['amount_from'], fn($q, $amount) => $q->where('total_amount', '>=', $amount))
            ->when($data['amount_to'], fn($q, $amount) => $q->where('total_amount', '<=', $amount));
    })
```

### 3.8 Created Date Filter
```php
Tables\Filters\Filter::make('created_at')
    ->form([
        Forms\Components\DatePicker::make('created_from')
            ->label('Created From'),
        Forms\Components\DatePicker::make('created_until')
            ->label('Created Until'),
    ])
    ->query(function (Builder $query, array $data): Builder {
        return $query
            ->when($data['created_from'], fn($q, $date) => $q->whereDate('created_at', '>=', $date))
            ->when($data['created_until'], fn($q, $date) => $q->whereDate('created_at', '<=', $date));
    })
```

---

## 4. Filter Layout & Organization

### Layout Configuration:
```php
->filtersLayout(FiltersLayout::AboveContentCollapsible)
->filtersFormColumns(4)
```

### Filter Groups:
Organize filters into logical groups:
1. **Status & State** - Status, Overdue
2. **Dates** - Invoice Date Range, Due Date Range, Created Date
3. **Related** - Customer, Meter
4. **Financial** - Amount Range, Balance Type

---

## 5. Quick Filter Presets

Add quick filter buttons above the table:

```php
->persistFiltersInSession()
->filtersTriggerAction(
    fn (Action $action) => $action
        ->button()
        ->label('Filters'),
)
```

### Preset Filters:
Create custom header actions for common filters:
1. **Unpaid Invoices** - Status: Not Paid, Partial Payment
2. **Overdue Invoices** - Due date < today, Status: Unpaid
3. **This Month** - Invoice date: current month
4. **This Week** - Invoice date: current week
5. **Last 30 Days** - Invoice date: last 30 days

---

## 6. Export Implementation Details

### Create Export Class:

```php
namespace App\Exports;

use App\Models\Invoice;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;

class InvoicesExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query;
    }

    public function headings(): array
    {
        return [
            'Invoice Number',
            'Invoice Date',
            'Due Date',
            'Customer Name',
            'Customer Phone',
            'Meter Number',
            'Meter Location',
            'Total Amount',
            'Paid Amount',
            'Balance',
            'Status',
            'Days Overdue',
            'Created At',
        ];
    }

    public function map($invoice): array
    {
        $daysOverdue = $invoice->due_date && $invoice->due_date->isPast() 
            ? now()->diffInDays($invoice->due_date) 
            : 0;

        return [
            $invoice->invoice_number,
            $invoice->invoice_date->format('Y-m-d'),
            $invoice->due_date?->format('Y-m-d'),
            $invoice->customer->name,
            $invoice->customer->telephone,
            $invoice->meter->meter_number,
            $invoice->meter->location,
            $invoice->total_amount,
            $invoice->paid_amount,
            $invoice->balance,
            ucfirst($invoice->status),
            $daysOverdue,
            $invoice->created_at->format('Y-m-d H:i:s'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
```

### Add Export Actions:

```php
// Header action for exporting all filtered results
->headerActions([
    Tables\Actions\ExportAction::make()
        ->exporter(InvoicesExporter::class)
        ->label('Export All')
        ->color('success'),
])

// Bulk action for exporting selected rows
->bulkActions([
    Tables\Actions\ExportBulkAction::make()
        ->exporter(InvoicesExporter::class),
])
```

---

## 7. Additional Enhancements

### 7.1 Saved Filters
Allow users to save commonly used filter combinations.

### 7.2 Column Toggle
Already implemented - users can show/hide columns.

### 7.3 Search Enhancement
Add global search across multiple fields:
```php
->searchable()
->searchPlaceholder('Search invoices...')
```

### 7.4 Pagination Options
Already implemented - 10, 25, 50, 100 records per page.

---

## 8. Implementation Order

1. ✅ **Row Selection** - Enable bulk actions
2. ✅ **Basic Filters** - Status, Date Range, Customer, Meter
3. ✅ **Export Functionality** - Excel and CSV
4. ✅ **Advanced Filters** - Overdue, Balance, Amount Range
5. ✅ **Quick Filter Presets** - Common filter buttons
6. ✅ **Bulk Actions** - SMS, Export selected
7. ✅ **Testing** - Ensure all filters work correctly

---

## 9. Files to Modify

1. `app/Filament/Tenant/Resources/InvoiceResource.php`
   - Add filters
   - Enable row selection
   - Add bulk actions
   - Add export actions

2. `app/Exports/InvoicesExport.php` (create new)
   - Export class implementation

3. `app/Filament/Helpers/InvoiceTableHelper.php` (optional)
   - Extract filter methods if needed

---

## 10. Testing Checklist

- [ ] Row selection works on all pages
- [ ] Export all produces correct file
- [ ] Export selected only exports checked rows
- [ ] Each filter correctly filters results
- [ ] Multiple filters work together
- [ ] Filter indicators show active filters
- [ ] Clear filters button works
- [ ] Saved filters persist across sessions
- [ ] Export includes all visible columns
- [ ] Export respects current filters
- [ ] Bulk SMS action works for selected invoices

---

## Notes

- All exports respect current filters and search
- Row selection persists across pages (configurable)
- Filters are collapsible to save screen space
- Export actions are available in both header and bulk actions
- Consider adding a "Loading" state for large exports
- Add proper error handling for failed exports

