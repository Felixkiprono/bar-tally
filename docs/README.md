# Hydra Billing - Documentation Index

Welcome to the Hydra Billing documentation! This directory contains comprehensive documentation organized by context and purpose.

---

## 📚 Documentation Structure

### 🎓 [User Guides](./guides/)
High-level system documentation and user guides
- **[System Documentation](./guides/SYSTEM_DOCUMENTATION.md)** - Technical system overview
- **[Business User Guide](./guides/BUSINESS_USER_GUIDE.md)** - End-user documentation

### ✨ [Features](./features/)
Feature implementation documentation organized by domain

#### 📄 [Invoice](./features/invoice/)
- [Invoice Service Refactor Proposal](./features/invoice/INVOICE_SERVICE_REFACTOR_PROPOSAL.md)
- [Invoice Display Update](./features/invoice/INVOICE_DISPLAY_UPDATE.md)
- [Invoice Display Improvements](./features/invoice/INVOICE_DISPLAY_IMPROVEMENTS.md)
- [View Invoice Update](./features/invoice/VIEW_INVOICE_UPDATE.md)
- [Invoice List Enhancements Plan](./features/invoice/INVOICE_LIST_ENHANCEMENTS_PLAN.md)
- [Bill Invoice Consolidation Fix](./features/invoice/BILL_INVOICE_CONSOLIDATION_FIX.md)
- [Bill Consolidation Simple Approach](./features/invoice/BILL_CONSOLIDATION_SIMPLE_APPROACH.md)
- [Bill Consolidation Pattern](./features/invoice/BILL_CONSOLIDATION_PATTERN.md)

#### 💰 [Payment](./features/payment/)
- [Auto Apply Overpayment Plan](./features/payment/AUTO_APPLY_OVERPAYMENT_PLAN.md)
- [Auto Apply Overpayment Implementation Summary](./features/payment/AUTO_APPLY_OVERPAYMENT_IMPLEMENTATION_SUMMARY.md)
- [Auto Apply Overpayment Test Coverage](./features/payment/AUTO_APPLY_OVERPAYMENT_TEST_COVERAGE.md)
- [Quick Pay Meter Centric Refactor](./features/payment/QUICK_PAY_METER_CENTRIC_REFACTOR.md)
- [Quick Pay Meter Centric Implementation Summary](./features/payment/QUICK_PAY_METER_CENTRIC_IMPLEMENTATION_SUMMARY.md)
- [Payment List Implementation Summary](./features/payment/PAYMENT_LIST_IMPLEMENTATION_SUMMARY.md)
- [Payment List Refactor Plan](./features/payment/PAYMENT_LIST_REFACTOR_PLAN.md)

#### 📊 [Meter](./features/meter/)
- [Meter Centric Refactor Plan](./features/meter/METER_CENTRIC_REFACTOR_PLAN.md)
- [Meter Centric Verification](./features/meter/METER_CENTRIC_VERIFICATION.md)
- [Meter List Implementation Summary](./features/meter/METER_LIST_IMPLEMENTATION_SUMMARY.md)
- [Meter List Refactor Plan](./features/meter/METER_LIST_REFACTOR_PLAN.md)

#### 💬 [Messaging](./features/messaging/)
- [Messaging System Complete Summary](./features/messaging/MESSAGING_SYSTEM_COMPLETE_SUMMARY.md)
- [Message Composer Helper Usage](./features/messaging/MESSAGE_COMPOSER_HELPER_USAGE.md)

### 🔄 [Migrations](./migrations/)
Data migration and backfill documentation
- [Meter Financial Migration Flow](./migrations/METER_FINANCIAL_MIGRATION_FLOW.md)
- [Invoice Amounts Backfill](./migrations/INVOICE_AMOUNTS_BACKFILL.md)
- [Customer Balance Recalculation](./migrations/CUSTOMER_BALANCE_RECALCULATION.md)
- [Command Organization](./migrations/COMMAND_ORGANIZATION.md)

### 📋 [Project](./project/)
Project organization and meta documentation
- [Implementation Summary](./project/IMPLEMENTATION_SUMMARY.md)
- [Test Organization Plan](./project/TEST_ORGANIZATION_PLAN.md)
- [Test Reorganization Complete](./project/TEST_REORGANIZATION_COMPLETE.md)

### 🧪 [Tests](./tests/)
Comprehensive test documentation
- **[Test Documentation Index](./tests/README.md)** - Main test documentation hub
- [Invoice Tests](./tests/INVOICE_TESTS.md) - 39 tests covering invoice functionality
- [Bill Tests](./tests/BILL_TESTS.md) - 61 tests covering billing functionality
- [Payment Tests](./tests/PAYMENT_TESTS.md) - 45 tests covering payment functionality

---

## 🗂️ Quick Navigation

### By Feature Domain
- **Invoices**: [Features](./features/invoice/) | [Tests](./tests/INVOICE_TESTS.md)
- **Payments**: [Features](./features/payment/) | [Tests](./tests/PAYMENT_TESTS.md)
- **Bills**: [Features](./features/invoice/) | [Tests](./tests/BILL_TESTS.md)
- **Meters**: [Features](./features/meter/)
- **Messaging**: [Features](./features/messaging/)

### By Document Type
- **Planning Docs**: Docs ending in `_PLAN.md` or `_PROPOSAL.md`
- **Implementation Docs**: Docs ending in `_IMPLEMENTATION_SUMMARY.md`
- **Test Coverage Docs**: Docs in [tests/](./tests/) directory
- **Migration Docs**: Docs in [migrations/](./migrations/) directory

### Common Tasks
- **Running Tests**: See [Test Documentation](./tests/README.md#-quick-start)
- **Running Migrations**: See [Meter Financial Migration](./migrations/METER_FINANCIAL_MIGRATION_FLOW.md)
- **Understanding Features**: Browse [features/](./features/) by domain
- **User Guides**: See [guides/](./guides/)

---

## 📊 Documentation Statistics

| Category | Documents | Status |
|----------|-----------|--------|
| User Guides | 2 | ✅ Complete |
| Invoice Features | 8 | ✅ Complete |
| Payment Features | 7 | ✅ Complete |
| Meter Features | 4 | ✅ Complete |
| Messaging Features | 2 | ✅ Complete |
| Migrations | 4 | ✅ Complete |
| Project Docs | 3 | ✅ Complete |
| Test Docs | 4 | ✅ Complete |
| **Total** | **34** | **✅ Comprehensive** |

---

## 🔍 Finding Documentation

### By Feature Name
```bash
# Search for specific feature
grep -r "overpayment" docs/features/

# Find all refactor plans
find docs -name "*REFACTOR*"

# Find all implementation summaries
find docs -name "*IMPLEMENTATION*"
```

### By Context
- **Need to understand a feature?** → `docs/features/{domain}/`
- **Need to run tests?** → `docs/tests/`
- **Need to migrate data?** → `docs/migrations/`
- **Need user documentation?** → `docs/guides/`
- **Need project context?** → `docs/project/`

---

## 📝 Documentation Conventions

### File Naming
- **Plans**: `{FEATURE}_PLAN.md` or `{FEATURE}_PROPOSAL.md`
- **Implementations**: `{FEATURE}_IMPLEMENTATION_SUMMARY.md`
- **Tests**: `{DOMAIN}_TESTS.md`
- **Guides**: `{TYPE}_GUIDE.md` or `{TYPE}_DOCUMENTATION.md`

### Organization Principles
1. **Domain-Based**: Feature docs organized by business domain
2. **Purpose-Based**: Top-level folders organized by document purpose
3. **Consistent**: Mirrors application and test structure
4. **Discoverable**: Clear naming and logical grouping

---

## 🚀 Recent Documentation Updates

### November 2025
- ✅ Reorganized all documentation into domain-based folders
- ✅ Created comprehensive test documentation
- ✅ Added migration and command organization docs
- ✅ Consolidated implementation summaries by feature

### October 2025
- ✅ Documented invoice service refactor
- ✅ Added payment overpayment test coverage
- ✅ Created meter-centric refactor documentation
- ✅ Added messaging system documentation

---

## 🤝 Contributing to Documentation

### Adding New Documentation
1. **Choose the appropriate directory** based on context:
   - Feature docs → `docs/features/{domain}/`
   - Migration docs → `docs/migrations/`
   - Test docs → `docs/tests/`
   - Project meta docs → `docs/project/`

2. **Follow naming conventions**:
   - Use UPPERCASE for file names
   - Use underscores to separate words
   - Use descriptive names

3. **Update this README**:
   - Add link in appropriate section
   - Update documentation statistics
   - Add to recent updates

### Documentation Standards
- Use clear, descriptive headings
- Include code examples where relevant
- Link to related documentation
- Keep documentation up-to-date with code changes
- Use consistent formatting

---

## 📞 Support

For questions about:
- **Features**: Check relevant `docs/features/{domain}/` directory
- **Tests**: See [Test Documentation](./tests/README.md)
- **Migrations**: See [Migrations](./migrations/) directory
- **System Usage**: See [User Guides](./guides/)

---

**Last Updated**: November 12, 2025  
**Total Documents**: 33  
**Organization**: Domain-Based ✅

---

## 📖 External Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Filament PHP Documentation](https://filamentphp.com/docs)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)


