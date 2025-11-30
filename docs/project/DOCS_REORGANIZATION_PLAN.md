# Documentation Reorganization Plan

## Proposed Structure

```
docs/
├── README.md (NEW - Main index for all documentation)
│
├── guides/                         # User and system guides
│   ├── SYSTEM_DOCUMENTATION.md
│   └── BUSINESS_USER_GUIDE.md
│
├── features/                       # Feature implementation docs by domain
│   ├── invoice/
│   │   ├── INVOICE_SERVICE_REFACTOR_PROPOSAL.md
│   │   ├── INVOICE_DISPLAY_UPDATE.md
│   │   ├── INVOICE_DISPLAY_IMPROVEMENTS.md
│   │   ├── VIEW_INVOICE_UPDATE.md
│   │   ├── INVOICE_LIST_ENHANCEMENTS_PLAN.md
│   │   ├── BILL_INVOICE_CONSOLIDATION_FIX.md
│   │   ├── BILL_CONSOLIDATION_SIMPLE_APPROACH.md
│   │   └── BILL_CONSOLIDATION_PATTERN.md
│   │
│   ├── payment/
│   │   ├── AUTO_APPLY_OVERPAYMENT_PLAN.md
│   │   ├── AUTO_APPLY_OVERPAYMENT_IMPLEMENTATION_SUMMARY.md
│   │   ├── AUTO_APPLY_OVERPAYMENT_TEST_COVERAGE.md
│   │   ├── QUICK_PAY_METER_CENTRIC_REFACTOR.md
│   │   ├── QUICK_PAY_METER_CENTRIC_IMPLEMENTATION_SUMMARY.md
│   │   ├── PAYMENT_LIST_IMPLEMENTATION_SUMMARY.md
│   │   └── PAYMENT_LIST_REFACTOR_PLAN.md
│   │
│   ├── meter/
│   │   ├── METER_CENTRIC_REFACTOR_PLAN.md
│   │   ├── METER_CENTRIC_VERIFICATION.md
│   │   ├── METER_LIST_IMPLEMENTATION_SUMMARY.md
│   │   └── METER_LIST_REFACTOR_PLAN.md
│   │
│   └── messaging/
│       ├── MESSAGING_SYSTEM_COMPLETE_SUMMARY.md
│       └── MESSAGE_COMPOSER_HELPER_USAGE.md
│
├── migrations/                     # Migration and data backfill docs
│   ├── METER_FINANCIAL_MIGRATION_FLOW.md
│   ├── INVOICE_AMOUNTS_BACKFILL.md
│   └── COMMAND_ORGANIZATION.md
│
├── project/                        # Project organization and meta docs
│   ├── IMPLEMENTATION_SUMMARY.md
│   ├── TEST_ORGANIZATION_PLAN.md
│   └── TEST_REORGANIZATION_COMPLETE.md
│
└── tests/                          # Test documentation (already organized)
    ├── README.md
    ├── INVOICE_TESTS.md
    ├── BILL_TESTS.md
    └── PAYMENT_TESTS.md
```

## Benefits

✅ **Clear Organization by Context** - Docs grouped by purpose
✅ **Easy Navigation** - Related docs are co-located
✅ **Scalable** - Each domain can grow independently
✅ **Consistent** - Mirrors app/ and tests/ structure
✅ **Discovery** - Easier to find relevant documentation

## Migration Steps

1. Create new directory structure
2. Move files to appropriate directories
3. Create main docs/README.md index
4. Update any internal cross-references
5. Verify all links still work

