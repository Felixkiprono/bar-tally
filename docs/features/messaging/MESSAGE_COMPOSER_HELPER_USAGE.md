# MessageComposerHelper Usage Guide

## 📋 Overview

`MessageComposerHelper` is a pure Filament form component generator for SMS message composition. It provides a consistent, beautiful UI for composing messages with tags, templates, live preview, and footer management.

---

## ✨ Features

- ✅ **Template Selector** - Load saved templates
- ✅ **Context Selector** - Switch between message contexts (INVOICE, PAYMENT, etc.)
- ✅ **Tag Insertion at Cursor** - Click tags to insert at cursor position
- ✅ **Live Preview** - See message with Faker sample data
- ✅ **Footer Management** - Checkbox to append global footer
- ✅ **Reactive Stats** - Character count, SMS count, status (includes footer!)
- ✅ **Color-Coded Stats** - Green/Blue/Yellow/Red based on length
- ✅ **Full Dark Mode** - All native Filament styling
- ✅ **Works Everywhere** - Modals, pages, resources

---

## 🚀 Basic Usage

```php
use App\Filament\Helpers\MessageComposerHelper;

// In your form schema
Forms\Components\Section::make('Compose SMS')
    ->schema(
        MessageComposerHelper::getFields()
    )
```

That's it! You now have a full-featured message composer.

---

## ⚙️ Configuration Options

```php
MessageComposerHelper::getFields(
    fieldName: 'message',                    // Field name (default: 'message')
    defaultContext: 'GENERAL',               // Default context
    allowedContexts: ['GENERAL', 'REMINDER'],// Restrict contexts (null = all)
    showTemplateSelector: true,              // Show template dropdown
    showContextSelector: true,               // Show context dropdown
    showFooterOption: true                   // Show footer checkbox
)
```

---

## 📖 Examples

### 1. **Customer SMS (Full Featured)**

```php
MessageComposerHelper::getFields(
    fieldName: 'message',
    defaultContext: 'GENERAL',
    allowedContexts: ['GENERAL', 'REMINDER'],
    showTemplateSelector: true,
    showContextSelector: true,
    showFooterOption: true
)
```

**Features:**
- Template selector
- Context switching
- Footer management
- All available tags

---

### 2. **Invoice SMS**

```php
MessageComposerHelper::getFields(
    fieldName: 'message_content',
    defaultContext: 'INVOICE',
    allowedContexts: ['INVOICE', 'GENERAL', 'REMINDER'],
    showTemplateSelector: true,
    showContextSelector: true,
    showFooterOption: true
)
```

**Features:**
- Defaults to INVOICE context
- Can switch to GENERAL or REMINDER
- Invoice-specific tags available

---

### 3. **Template Editor (Minimal)**

```php
MessageComposerHelper::getFields(
    fieldName: 'content',
    defaultContext: 'GENERAL',
    allowedContexts: null,  // All contexts available
    showTemplateSelector: false,  // No template selector (creating template!)
    showContextSelector: false,   // Context selected elsewhere in form
    showFooterOption: false       // Templates are base messages only
)
```

**Features:**
- Just textarea and tags
- No selectors or footer
- Clean template editing

---

### 4. **Reminder Rules**

```php
MessageComposerHelper::getFields(
    fieldName: 'message_template',
    defaultContext: 'REMINDER',
    allowedContexts: ['REMINDER'],
    showTemplateSelector: true,   // Can load existing reminder templates
    showContextSelector: false,   // Fixed to REMINDER
    showFooterOption: false       // Template definition
)
```

**Features:**
- Fixed REMINDER context
- Can load existing templates
- REMINDER-specific tags only

---

## 🎨 What Users See

```
┌────────────────────────────────────────────────┐
│ Template [dropdown]    Context [dropdown]      │
├────────────────────────────────────────────────┤
│ Message                                        │
│ ┌──────────────────────────────────────────┐   │
│ │ Type your message here...                │   │
│ └──────────────────────────────────────────┘   │
├────────────────────────────────────────────────┤
│ Available Tags (click to insert at cursor)     │
│ [{customer_name}] [{balance}] [{location}]     │
├────────────────────────────────────────────────┤
│ [✓] Append Global Footer                      │
│ ℹ️ Footer visible in preview below             │
├────────────────────────────────────────────────┤
│ Preview (with sample data)                     │
│ ┌──────────────────────────────────────────┐   │
│ │ Dear John Smith, your balance is         │   │
│ │ 1,234.56...                              │   │
│ │                                          │   │
│ │ Thank you for your business!             │   │
│ └──────────────────────────────────────────┘   │
├────────────────────────────────────────────────┤
│ Characters: 152 chars | SMS: 1 SMS | Ready    │
└────────────────────────────────────────────────┘
```

---

## 🎯 Available Contexts & Tags

### GENERAL
`{customer_name}`, `{phone}`, `{location}`, `{balance}`, `{overpayment}`

### INVOICE
`{customer_name}`, `{invoice_number}`, `{meter_number}`, `{invoice_date}`, `{due_date}`, `{amount}`, `{arrears}`, `{overpayment}`, `{balance}`, `{penalty}`, `{current_reading}`, `{previous_reading}`, `{units}`

### PAYMENT
`{customer_name}`, `{amount}`, `{payment_date}`, `{balance}`, `{overpayment}`, `{meter_number}`, `{payment_method}`, `{transaction_id}`

### REMINDER
`{customer_name}`, `{balance}`, `{due_date}`, `{days_overdue}`, `{meter_number}`

### METER_READING
`{customer_name}`, `{meter_number}`, `{reading_date}`, `{current_reading}`, `{previous_reading}`, `{consumption}`

---

## 🔧 Integration Points

Currently integrated in:
1. ✅ CustomerSmsHelper (individual/bulk/header actions)
2. ✅ BulkSendSms page
3. ✅ MessageTemplateResource (template editor)
4. ✅ ReminderRuleResource (reminder rules)
5. ✅ InvoiceTableHelper (invoice SMS)

---

## 💡 Technical Details

### How Tag Insertion Works

Uses Alpine.js `x-data` on the textarea:
```javascript
insertTag(tag) {
    const textarea = $el;
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const newText = text.substring(0, start) + tag + text.substring(end);
    $wire.set("message", newText);
    setTimeout(() => {
        textarea.focus();
        textarea.selectionStart = textarea.selectionEnd = start + tag.length;
    }, 10);
}
```

Tag buttons use Alpine.js directive:
```html
<button @click="$el.closest('form').querySelector('textarea[name="message"]').insertTag('{tag}')">
```

### How Stats Update

Stats calculate directly from the message field:
```php
$message = $get($fieldName) ?? '';
$appendFooter = $get('append_footer') ?? true;

// Include footer in count
if ($appendFooter && !empty($message)) {
    $footer = Configuration::getSmsFooter($tenantId);
    if ($footer) {
        $totalLength += strlen("\n\n" . $footer);
    }
}
```

### How Preview Works

Uses Faker to generate realistic sample data:
```php
$faker = \Faker\Factory::create();
$replacements = [
    '{customer_name}' => $faker->name(),
    '{balance}' => number_format($faker->randomFloat(2, 100, 5000), 2),
    // ... etc
];
```

---

## 🎨 Styling

All styling uses native Filament components with Tailwind CSS:
- Tag pills: `bg-primary-600 dark:bg-primary-500 text-white`
- Stats: Color-coded text and badges
- Preview: `bg-primary-50 dark:bg-primary-950` with primary borders
- Footer: Same styling as preview

---

## 📝 Best Practices

1. **Always set append_footer default:**
   ```php
   ->fillForm(['append_footer' => true])
   ```

2. **Choose appropriate contexts:**
   - Customer general SMS: `['GENERAL', 'REMINDER']`
   - Invoice SMS: `['INVOICE', 'GENERAL', 'REMINDER']`
   - Template editor: All contexts
   - Reminder rules: `['REMINDER']` only

3. **Field naming:**
   - Use `'message'` for most cases
   - Use `'content'` for templates
   - Use `'message_template'` for reminder rules
   - Use `'message_content'` for invoice SMS

4. **Selectors:**
   - Hide template selector when creating templates
   - Hide context selector when context is fixed
   - Hide footer for template definitions

---

## 🔍 Troubleshooting

### Stats not updating
- Ensure `->live()` is set on the message textarea
- Stats calculate from `$get($fieldName)` directly

### Tags not inserting
- Check that Alpine.js is loaded
- Verify textarea has the `x-data` with `insertTag()` function
- Check browser console for JavaScript errors

### Preview not showing
- Ensure message field has content
- Check that `$get($fieldName)` is reading the correct field

---

**Last Updated:** October 2025  
**Version:** 2.0  
**Status:** Production Ready ✅


