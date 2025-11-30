# Quick Pay Meter-Centric Refactor Plan

## Current State Analysis

### What's Already Meter-Centric ✅
1. **CustomerPaymentService::processQuickPayment()** - Already requires `meter_id`
2. **CustomerActionHelper::getQuickPayTableAction()** - Fully meter-centric with meter selection
3. **CustomerPaymentService::getLatestUnpaidInvoiceForMeter()** - Meter-specific invoice lookup

### What's Still Customer-Centric ❌
1. **CustomerActionHelper::getQuickPayHeaderAction()** - Uses customer-level context
2. **CustomerPaymentService::getLatestUnpaidInvoice()** - Customer-level invoice lookup (legacy)
3. **CustomerPaymentService::getPaymentFormDefaults()** - Customer-level defaults
4. **CustomerPaymentService::getPaymentHelpText()** - Customer-level help text

### Missing Context-Awareness ⚠️
1. **Invoice Context** - Quick Pay from invoice view should auto-use invoice's meter
2. **No meter selection needed** - When invoice is known, meter_id is implicit
3. **Better UX** - Fewer steps when context is clear

### Code Duplication Issues 🔄
- Two separate Quick Pay form implementations (table vs header)
- Payment form schema is duplicated between table and header actions
- Context fetching logic is duplicated
- Similar reactive form logic in multiple places
- No reusable Quick Pay action for invoice context

---

## Refactoring Strategy

### Phase 1: Abstract Shared Form Logic with Context Awareness
**Goal**: Create reusable form components that adapt to context (customer vs invoice)

**Changes**:
1. **Create `CustomerActionHelper::buildQuickPayFormSchema()`**
   - Accept `$record` and optional `$context` parameters
   - `$context` can be: `['type' => 'customer']` or `['type' => 'invoice', 'invoice' => $invoice]`
   - **Customer Context**: Show meter selection dropdown
   - **Invoice Context**: Skip meter selection, use invoice's meter automatically
   - Return the complete form schema array
   - This eliminates duplication and adds context awareness

2. **Create `CustomerActionHelper::handleQuickPayAction()`**
   - Extract the action handler logic
   - Make it reusable for both table and header actions
   - Accepts `$data`, `$record`, and optional `$context` as parameters
   - Handle context-specific payment processing

**Benefits**:
- Single source of truth for Quick Pay form
- Context-aware UX (fewer steps when context is clear)
- Easier to maintain and update
- Consistent behavior across all Quick Pay entry points

---

### Phase 2: Update Header Action to be Meter-Centric
**Goal**: Make the header action use the same meter-centric approach as table action

**Changes**:
1. **Update `getQuickPayHeaderAction()`**
   - Remove customer-centric form building
   - Use the new shared `buildQuickPayFormSchema()` method
   - Use the shared `handleQuickPayAction()` method
   - Keep only the header-specific configuration (icon, color, modal settings)

**Benefits**:
- Consistency between table and header actions
- No more customer-level payment context
- All payments go through meter selection

---

### Phase 3: Clean Up CustomerPaymentService
**Goal**: Remove legacy customer-centric methods

**Methods to Remove**:
1. `getLatestUnpaidInvoice(int $customerId)` - Use `getLatestUnpaidInvoiceForMeter()` instead
2. `getPaymentFormDefaults(User $customer)` - No longer needed with meter-centric forms
3. `getPaymentHelpText(User $customer)` - Context is now dynamic based on meter selection

**Methods to Update**:
1. `getCustomerPaymentContext(User $customer, ?int $meterId = null)`
   - Remove the customer-level fallback (lines 66-77)
   - Make `$meterId` required: `getCustomerPaymentContext(User $customer, int $meterId)`
   - Always return meter-specific context
   - Remove `customer_balance` and `customer_overpayment` from return array

**Benefits**:
- Cleaner service with single responsibility
- No legacy code paths
- Enforces meter-centric architecture

---

### Phase 4: Create Invoice Context Quick Pay
**Goal**: Add Quick Pay action for invoice views that doesn't require meter selection

**Changes**:
1. **Create `InvoiceTableHelper::getQuickPayAction()`**
   - New action specifically for invoice context
   - Automatically uses invoice's meter_id
   - No meter selection needed
   - Uses shared `buildQuickPayFormSchema()` with invoice context

2. **Update `ViewInvoice` page**
   - Add Quick Pay action to header actions
   - Use invoice context Quick Pay

**Benefits**:
- Streamlined UX for invoice payments
- Fewer clicks for users
- Context-aware behavior

### Phase 5: Update Related Components
**Goal**: Ensure all components using Quick Pay are updated

**Files to Check**:
1. ✅ **CustomerResource** - Uses `CustomerActionHelper::getTableActions()`
   - No changes needed (already uses the helper)

2. ✅ **ViewCustomer page** - Uses `CustomerActionHelper::getHeaderActions()`
   - No changes needed (will automatically use updated helper)

3. ✅ **InvoiceResource** - Add Quick Pay to table actions
   - Use new context-aware Quick Pay

4. ✅ **ViewInvoice page** - Add Quick Pay to header actions
   - Use new context-aware Quick Pay

5. **Any custom implementations** - Search for direct Quick Pay usage
   - Search for: `processQuickPayment`, `getCustomerPaymentContext`
   - Update any direct calls to use meter-specific parameters

**Benefits**:
- Comprehensive update across the entire application
- No orphaned customer-centric code
- Quick Pay available everywhere it makes sense

---

## Implementation Steps

### Step 1: Create Context-Aware Shared Form Builder
```php
// In CustomerActionHelper.php

/**
 * Build the Quick Pay form schema (context-aware for customer or invoice)
 * 
 * @param mixed $record The customer or invoice record
 * @param array $context Context information: ['type' => 'customer'|'invoice', 'invoice' => Invoice]
 */
protected static function buildQuickPayFormSchema($record, array $context = ['type' => 'customer']): array
{
    $isInvoiceContext = $context['type'] === 'invoice';
    $invoice = $context['invoice'] ?? null;
    
    $schema = [];
    
    // CONDITIONAL: Meter Selection (only for customer context)
    if (!$isInvoiceContext) {
        $schema[] = Select::make('meter_id')
            ->label('Select Meter')
            ->required()
            ->options(function () use ($record) {
                return \App\Models\MeterAssignment::where('customer_id', $record->id)
                    ->where('is_active', true)
                    ->with('meter')
                    ->get()
                    ->mapWithKeys(fn($assignment) => [
                        $assignment->meter_id => $assignment->meter->meter_number . 
                            ' - ' . ($assignment->meter->location ?? 'No location') . 
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
                    $paymentService = app(\App\Services\CustomerPaymentService::class);
                    $context = $paymentService->getCustomerPaymentContext($record, $state);
                    
                    // Update form fields based on selected meter
                    $set('meter_balance', $meter->balance);
                    $set('meter_overpayment', $meter->overpayment);
                    $set('amount', $context['suggested_amount']);
                    $set('latest_invoice_info', 
                        $context['has_unpaid_invoice'] ? 
                            "Invoice #{$context['latest_invoice']->invoice_number} - KES " . 
                            number_format($context['latest_invoice']->balance, 2) : 
                            'No outstanding invoices'
                    );
                }
            })
            ->helperText('Select which meter this payment is for');
    } else {
        // Invoice context: Auto-populate meter info and hide selection
        $meter = $invoice->meter;
        $paymentService = app(\App\Services\CustomerPaymentService::class);
        $paymentContext = $paymentService->getCustomerPaymentContext($record, $meter->id);
        
        $schema[] = Hidden::make('meter_id')
            ->default($meter->id);
        
        $schema[] = Placeholder::make('meter_context')
            ->label('Payment For')
            ->content("Meter: {$meter->meter_number} | Location: " . ($meter->location ?? 'N/A'))
            ->columnSpanFull();
        
        $schema[] = Hidden::make('meter_balance')
            ->default($meter->balance);
        
        $schema[] = Hidden::make('meter_overpayment')
            ->default($meter->overpayment);
        
        $schema[] = Hidden::make('latest_invoice_info')
            ->default("Invoice #{$invoice->invoice_number} - KES " . number_format($invoice->balance, 2));
    }
    
    // STEP 2: Meter Financial Info (for customer context only, invoice context shows differently)
    if (!$isInvoiceContext) {
        $schema[] = Placeholder::make('meter_info')
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
            ->columnSpanFull();
        
        // Hidden fields for customer context
        $schema[] = Hidden::make('meter_balance');
        $schema[] = Hidden::make('meter_overpayment');
        $schema[] = Hidden::make('latest_invoice_info');
    } else {
        // Invoice context: Show specific invoice info
        $schema[] = Placeholder::make('invoice_info')
            ->label('Invoice Information')
            ->content(function () use ($invoice) {
                return "Invoice: #{$invoice->invoice_number} | " .
                       "Balance Due: KES " . number_format($invoice->balance, 2) . " | " .
                       "Total: KES " . number_format($invoice->total_amount, 2);
            })
            ->columnSpanFull();
    }
    
    // STEP 3: Payment Amount (same for both contexts, but default varies)
    $schema[] = TextInput::make('amount')
        ->label('Payment Amount')
        ->required()
        ->numeric()
        ->prefix('KES')
        ->default($isInvoiceContext ? $invoice->balance : null)
        ->helperText(fn($get) => 
            $get('meter_balance') > 0 ? 
                "Suggested: KES " . number_format($get('meter_balance'), 2) . " (meter balance)" : 
                'Any amount will be recorded as advance payment/credit'
        )
        ->reactive();
    
    // Payment allocation preview (same for both contexts)
    $schema[] = Placeholder::make('payment_allocation')
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
        ->columnSpanFull();
    
    // STEP 4: Payment Details (same for both contexts)
    $schema[] = Select::make('method')
        ->label('Payment Method')
        ->options([
            'mpesa' => 'M-Pesa',
            'bank' => 'Bank Transfer',
            'cash' => 'Cash',
            'cheque' => 'Cheque',
        ])
        ->default('mpesa')
        ->required();
    
    $schema[] = TextInput::make('reference')
        ->label('Reference Number')
        ->placeholder('M-Pesa code, cheque number, etc.')
        ->required();
    
    $schema[] = Hidden::make('status')
        ->default('paid');
    
    $schema[] = Toggle::make('send_sms')
        ->label('Send SMS Notification')
        ->default(true);
    
    return $schema;
}
```

### Step 2: Create Context-Aware Shared Action Handler
```php
// In CustomerActionHelper.php

use App\Services\CustomerPaymentService;

/**
 * Handle Quick Pay action (context-aware for customer or invoice)
 * 
 * @param array $data Form data
 * @param mixed $record The customer record
 * @param array $context Context information
 */
protected static function handleQuickPayAction(array $data, $record, array $context = ['type' => 'customer']): void
{
    try {
        $paymentService = app(CustomerPaymentService::class);
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
}
```

### Step 3: Refactor Customer Context Actions
```php
// In CustomerActionHelper.php

public static function getQuickPayTableAction(): TableAction
{
    return TableAction::make('quickPay')
        ->label('Quick Pay')
        ->icon('heroicon-o-currency-dollar')
        ->color('success')
        ->form(fn($record) => self::buildQuickPayFormSchema($record, ['type' => 'customer']))
        ->action(fn(array $data, $record) => self::handleQuickPayAction($data, $record))
        ->requiresConfirmation()
        ->modalHeading(fn($record): string => 'Quick Pay for ' . $record->name)
        ->modalDescription('Select meter and enter payment details')
        ->modalSubmitActionLabel('Process Payment')
        ->modalWidth('2xl')
        ->extraAttributes([
            'x-data' => '{}',
            'x-on:click' => 'if($el.hasAttribute("disabled")) return; $el.setAttribute("disabled", ""); setTimeout(() => { $el.removeAttribute("disabled") }, 1000)',
        ]);
}

public static function getQuickPayHeaderAction(): PageAction
{
    return PageAction::make('quickPay')
        ->label('Quick Pay')
        ->icon('heroicon-o-currency-dollar')
        ->color('success')
        ->form(fn($record) => self::buildQuickPayFormSchema($record, ['type' => 'customer']))
        ->action(fn(array $data, $record) => self::handleQuickPayAction($data, $record))
        ->requiresConfirmation()
        ->modalHeading(fn($record): string => 'Quick Pay for ' . $record->name)
        ->modalDescription('Select meter and enter payment details')
        ->modalSubmitActionLabel('Process Payment')
        ->modalWidth('2xl');
}
```

### Step 3b: Create Invoice Context Actions
```php
// In InvoiceTableHelper.php (or CustomerActionHelper.php)

use App\Services\CustomerPaymentService;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Actions\Action as PageAction;

/**
 * Get Quick Pay action for invoice table
 */
public static function getQuickPayTableAction(): TableAction
{
    return TableAction::make('quickPay')
        ->label('Quick Pay')
        ->icon('heroicon-o-currency-dollar')
        ->color('success')
        ->visible(fn($record) => $record->balance > 0) // Only show for unpaid invoices
        ->form(function ($record) {
            return \App\Filament\Helpers\CustomerActionHelper::buildQuickPayFormSchema(
                $record->customer,
                ['type' => 'invoice', 'invoice' => $record]
            );
        })
        ->action(function (array $data, $record) {
            \App\Filament\Helpers\CustomerActionHelper::handleQuickPayAction(
                $data, 
                $record->customer,
                ['type' => 'invoice', 'invoice' => $record]
            );
        })
        ->requiresConfirmation()
        ->modalHeading(fn($record): string => 'Quick Pay for Invoice #' . $record->invoice_number)
        ->modalDescription(fn($record): string => 
            'Process payment for ' . $record->customer->name . 
            ' - Meter: ' . $record->meter->meter_number
        )
        ->modalSubmitActionLabel('Process Payment')
        ->modalWidth('2xl');
}

/**
 * Get Quick Pay action for invoice view page (header)
 */
public static function getQuickPayHeaderAction(): PageAction
{
    return PageAction::make('quickPay')
        ->label('Quick Pay')
        ->icon('heroicon-o-currency-dollar')
        ->color('success')
        ->visible(fn($record) => $record->balance > 0) // Only show for unpaid invoices
        ->form(function ($record) {
            return \App\Filament\Helpers\CustomerActionHelper::buildQuickPayFormSchema(
                $record->customer,
                ['type' => 'invoice', 'invoice' => $record]
            );
        })
        ->action(function (array $data, $record) {
            \App\Filament\Helpers\CustomerActionHelper::handleQuickPayAction(
                $data, 
                $record->customer,
                ['type' => 'invoice', 'invoice' => $record]
            );
        })
        ->requiresConfirmation()
        ->modalHeading(fn($record): string => 'Quick Pay for Invoice #' . $record->invoice_number)
        ->modalDescription(fn($record): string => 
            'Process payment for ' . $record->customer->name . 
            ' - Meter: ' . $record->meter->meter_number
        )
        ->modalSubmitActionLabel('Process Payment')
        ->modalWidth('2xl');
}
```

### Step 4: Update CustomerPaymentService
```php
// In CustomerPaymentService.php

// UPDATE: Make meter_id required
public function getCustomerPaymentContext(User $customer, int $meterId): array
{
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

// REMOVE: getLatestUnpaidInvoice() - no longer needed
// REMOVE: getPaymentFormDefaults() - no longer needed
// REMOVE: getPaymentHelpText() - no longer needed
```

---

## Testing Checklist

### Unit Tests
- [ ] `CustomerPaymentServiceTest::test_get_customer_payment_context_requires_meter_id`
- [ ] `CustomerPaymentServiceTest::test_process_quick_payment_with_meter_id`
- [ ] Update any tests that call removed methods

### Feature Tests
- [ ] Test Quick Pay from Customer table (table action)
- [ ] Test Quick Pay from ViewCustomer page (header action)
- [ ] Test meter selection updates form fields correctly
- [ ] Test payment allocation preview calculates correctly
- [ ] Test payment processing with different amounts (underpay, exact, overpay)
- [ ] Test payment for meter with no invoices (advance payment)
- [ ] Test payment for meter with outstanding invoice
- [ ] Test SMS notification is sent correctly

### Manual Testing
- [ ] Navigate to Customers table and use Quick Pay
- [ ] Navigate to ViewCustomer page and use Quick Pay
- [ ] Verify meter selection dropdown shows correct meters
- [ ] Verify financial info updates when meter is selected
- [ ] Verify payment allocation preview is accurate
- [ ] Process a payment and verify success notification
- [ ] Check invoice is updated correctly
- [ ] Check meter balance is updated correctly
- [ ] Verify SMS is sent (if enabled)

---

## Rollout Plan

### Phase 1: Preparation (No User Impact)
1. Create shared form builder and action handler methods
2. Run existing tests to ensure nothing breaks
3. Code review

### Phase 2: Refactor Actions (No User Impact)
1. Update table action to use shared methods
2. Update header action to use shared methods
3. Run tests again
4. Manual testing in staging

### Phase 3: Clean Up Service (Breaking Changes)
1. Update `getCustomerPaymentContext()` to require meter_id
2. Remove legacy methods
3. Update any affected tests
4. Full regression testing

### Phase 4: Deploy
1. Deploy to staging
2. Full QA testing
3. Deploy to production
4. Monitor for issues

---

## Benefits Summary

### Code Quality ✨
- **Eliminated Duplication**: Single form implementation used everywhere
- **Better Abstraction**: Shared methods reduce maintenance burden
- **Cleaner Service**: Removed legacy code paths
- **Context-Aware**: One implementation adapts to different contexts

### Architecture 🏗️
- **Fully Meter-Centric**: All payments tied to specific meters
- **Consistent Behavior**: Same experience from table and header
- **Maintainable**: Changes to Quick Pay only need to be made once
- **Flexible**: Easy to add new contexts in the future

### User Experience 👥
- **No Breaking Changes**: Users see the same functionality
- **More Explicit**: Must select meter in customer context
- **Better Context**: See meter-specific financial info before paying
- **Fewer Steps**: Invoice context skips meter selection (UX improvement)
- **Smart Defaults**: Payment amount pre-filled from invoice balance

### Testing 🧪
- **Easier to Test**: Single code path to test
- **Better Coverage**: Shared code means fewer edge cases
- **Faster Tests**: Fewer permutations to test
- **Context Testing**: Can test both customer and invoice contexts

---

## Risk Assessment

### Low Risk ✅
- Creating shared methods (additive change)
- Updating header action to use shared methods (internal change)

### Medium Risk ⚠️
- Updating `getCustomerPaymentContext()` signature (breaking change)
  - **Mitigation**: Search entire codebase for usage first
  - **Mitigation**: Update all call sites in same commit

### High Risk 🚨
- None identified

---

## Timeline Estimate

- **Phase 1 (Context-Aware Shared Methods)**: 3-4 hours
- **Phase 2 (Update Customer Actions)**: 1-2 hours
- **Phase 3 (Clean Up Service)**: 1-2 hours
- **Phase 4 (Create Invoice Context Actions)**: 2-3 hours
- **Phase 5 (Update Components)**: 1-2 hours
- **Phase 6 (Testing)**: 3-4 hours
- **Total**: 11-17 hours

---

## Context Comparison: Customer vs Invoice

### Customer Context (ViewCustomer page)
```
┌─────────────────────────────────────────────┐
│ Quick Pay for John Doe                      │
├─────────────────────────────────────────────┤
│                                             │
│ [Select Meter] ▼                            │
│ └─ MTR-001 - Main House (Balance: 1,500)   │
│ └─ MTR-002 - Shop (Balance: 800)           │
│                                             │
│ Meter Financial Information:                │
│ Balance: KES 1,500 | Credit: KES 0         │
│ Latest Invoice: #INV-123 - KES 1,500       │
│                                             │
│ Payment Amount: [1500.00]                   │
│ Payment Allocation: KES 1,500 to balance   │
│                                             │
│ Payment Method: [M-Pesa ▼]                  │
│ Reference: [ABC123XYZ]                      │
│ [✓] Send SMS Notification                   │
│                                             │
│         [Cancel]  [Process Payment]         │
└─────────────────────────────────────────────┘
```

### Invoice Context (ViewInvoice page)
```
┌─────────────────────────────────────────────┐
│ Quick Pay for Invoice #INV-123              │
├─────────────────────────────────────────────┤
│                                             │
│ Payment For: Meter: MTR-001 | Location: ... │
│                                             │
│ Invoice Information:                        │
│ Invoice: #INV-123 | Balance Due: KES 1,500 │
│ Total: KES 2,000                            │
│                                             │
│ Payment Amount: [1500.00] ← Pre-filled!     │
│ Payment Allocation: KES 1,500 to balance   │
│                                             │
│ Payment Method: [M-Pesa ▼]                  │
│ Reference: [ABC123XYZ]                      │
│ [✓] Send SMS Notification                   │
│                                             │
│         [Cancel]  [Process Payment]         │
└─────────────────────────────────────────────┘
```

**Key Differences:**
- **Customer Context**: User selects meter → sees meter info → enters amount
- **Invoice Context**: Meter auto-selected → invoice info shown → amount pre-filled
- **Result**: Invoice context is 1 step shorter and more focused!

---

## Questions to Resolve

1. ✅ Should we keep any customer-level payment context for backward compatibility?
   - **Answer**: No, fully meter-centric is the goal

2. ✅ What should happen if a customer has no active meters?
   - **Answer**: Quick Pay action should be disabled with appropriate message

3. ✅ Should we add a "default meter" concept for customers with multiple meters?
   - **Answer**: No, explicit selection is better for accuracy

4. ✅ Should we support invoice context Quick Pay?
   - **Answer**: YES! This is now included in the refactor plan

5. Should we support batch payments (multiple meters at once)?
   - **Answer**: Out of scope for this refactor, can be future enhancement

