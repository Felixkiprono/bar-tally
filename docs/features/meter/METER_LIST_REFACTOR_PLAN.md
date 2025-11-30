# Meter List Refactoring Plan

## 🎯 **Overview**
Comprehensive refactoring of the Meter List page to improve UX, add current customer display, implement filtering/export functionality, create a proper View page with relation managers, and modernize the interface to match the Invoice and Payment list improvements.

---

## 📋 **Current State Analysis**

### **Existing Columns:**
1. Status (badge - Connected/Disconnected)
2. Meter Number (bold, searchable)
3. Serial Number (searchable)
4. Meter Type (badge) - **DUPLICATE**
5. Meter Size
6. Balance (KES, toggleable)
7. Credit/Overpayment (KES, toggleable)
8. Financial Status (badge, toggleable)
9. Photo (circular image)
10. Meter Type (badge, colors) - **DUPLICATE**
11. Location (searchable, limited to 30 chars)
12. Initial Reading
13. Installation Date
14. Installed By
15. Last Reading
16. Last Reading Date
17. Current Reading
18. Manufacturer
19. Brand
20. Model
21. Created At (toggleable, hidden by default)

**Total: 21 columns (way too many!)**

### **Current Features:**
- ✅ Pagination: 10, 25, 50, 100 (default 50)
- ✅ Default sort: created_at DESC
- ✅ Grouping: Status (default), Location
- ✅ 3 Filters: Meter Type, Status, Installation Date
- ❌ No current customer displayed
- ❌ No export functionality
- ❌ No proper view page
- ❌ No relation managers (invoices, payments, bills, assignments)
- ❌ Duplicate columns (meter_type appears twice)
- ❌ Too many visible columns

### **Issues to Address:**
1. **Missing Current Customer**: Can't see who the meter is assigned to without opening
2. **Table Too Wide**: 21 columns make it impossible to see everything
3. **Duplicate Columns**: Meter type appears twice
4. **Limited Filtering**: Only 3 basic filters
5. **No Export**: Can't export meter registry
6. **No View Page**: View action doesn't have a dedicated page
7. **No History**: Can't see assignment history inline
8. **Grouping Not Useful**: Status grouping not as useful as filtering
9. **Reading Info Scattered**: Current, last, initial readings all separate

---

## 🎨 **Proposed New Design**

### **1. Table Layout - Compressed & Customer-Visible**

#### **New Column Structure:**
```
┌──────────────────────────────────────────────────────────────────────────────────────┐
│ [Customer ▼] [Status ▼] [Meter Type ▼] [Financial Status ▼] [From | To]             │
│                                                           Installation Date Range      │
├──────────────────────────────────────────────────────────────────────────────────────┤
│ Meter #  │ Customer    │ Location │ Type │ Status │ Balance/Credit │ Last Bill │ Actions │
├──────────────────────────────────────────────────────────────────────────────────────┤
│ MTR-001  │ John Doe    │ Block A  │ RES  │ ●●     │ 5,000 / 0      │ 05 Oct    │ [⋮] [👁]│
│          │      ↗      │          │      │ ●●     │                │           │         │
└──────────────────────────────────────────────────────────────────────────────────────┘
```

#### **Column Details:**

1. **Meter Number Column** (bold, clickable to view)
   - Meter number (bold, primary color)
   - Clickable → View Meter page
   - Searchable
   - Sortable

2. **Current Customer Column** (new!)
   - Customer name (if assigned)
   - "Unassigned" placeholder if no active assignment
   - Clickable → Customer View
   - Searchable
   - Shows assignment status via color/style

3. **Location Column**
   - Physical location
   - Searchable
   - Truncated if too long with tooltip

4. **Type Column**
   - Meter type badge (RES, COM, IND, GEN)
   - Color-coded
   - Sortable

5. **Status Column** (combined multi-indicator)
   - Line 1: Connection Status (Connected/Disconnected badge)
   - Line 2: Financial Status (Outstanding/Credit/Clear badge)
   - Compact display

6. **Balance/Credit Column** (combined financial)
   - Format: "Balance / Credit"
   - Example: "5,000 / 0" or "0 / 1,500"
   - Red for balance > 0, Green for credit > 0, Gray for both 0
   - Sortable by balance

7. **Last Bill Date Column**
   - Last invoice date for this meter
   - Shows "Never" if no invoices
   - Sortable
   - Format: "d M Y"

8. **Actions Column**
   - Dropdown menu (⋮):
     - Edit
     - Deactivate (if active)
     - Activate (if inactive)
     - View Readings
     - View Financial Summary
   - View button (👁) → View Meter page

#### **Hidden Columns (Moved to View Page or Toggleable):**
- Serial Number (toggleable)
- Meter Size (toggleable)
- Photo (view page)
- Initial Reading (view page)
- Installation Date (toggleable)
- Installed By (view page)
- Last/Current Reading (view page)
- Manufacturer, Brand, Model (view page)
- Created At (toggleable)
- Total Billed/Paid (view page)

---

### **2. Filters (Comprehensive, Single Row)**

**Filter Layout:**
```
┌──────────────────────────────────────────────────────────────────────┐
│ [Customer ▼] [Status ▼] [Meter Type ▼] [Financial ▼] [From | To]   │
│                                                Installation Date Range │
└──────────────────────────────────────────────────────────────────────┘
```

#### **Filter Specifications:**

1. **Customer Filter** (new!)
   - Type: Select (searchable)
   - Shows: All customers with assigned meters
   - Label: "Customer"
   - Preload: Yes

2. **Connection Status Filter**
   - Type: Select (single)
   - Options: All, Connected (Active), Disconnected (Inactive)
   - Label: "Status"
   - Default: All

3. **Meter Type Filter**
   - Type: Select (multiple)
   - Options: Residential, Commercial, Industrial, General
   - Label: "Meter Type"

4. **Financial Status Filter** (new!)
   - Type: Select (multiple)
   - Options: Outstanding (balance > 0), Credit (overpayment > 0), Clear (both 0)
   - Label: "Financial Status"

5. **Installation Date Range Filter**
   - Type: Custom filter with 2 date pickers
   - Fields: From Date, To Date
   - Label: "Installation Date Range"
   - Columns: 2 (side-by-side)
   - Column span: 1

**Filter Settings:**
- Layout: `AboveContent`
- Form Columns: `5` (one per filter)
- Persist in Session: Yes
- Always visible
- Remove grouping entirely

---

### **3. Export Functionality**

#### **Export Button:**
- Location: Bulk actions (select rows to export)
- Label: "Export Selected"
- Icon: `heroicon-o-arrow-down-tray`
- Color: Success
- Action: Export selected meters to XLSX

#### **Export File:**
- Filename: `meters-{timestamp}.xlsx`
- Format: Excel (.xlsx)
- Columns:
  1. Meter Number
  2. Serial Number
  3. Meter Type
  4. Meter Size
  5. Current Customer
  6. Customer Phone
  7. Location
  8. Connection Status
  9. Financial Status
  10. Balance
  11. Credit/Overpayment
  12. Total Billed
  13. Total Paid
  14. Last Invoice Date
  15. Installation Date
  16. Installed By
  17. Last Reading
  18. Last Reading Date
  19. Current Reading
  20. Manufacturer
  21. Brand
  22. Model
  23. Created At

#### **Export Class:**
- Create: `app/Exports/MetersExport.php`
- Implements: `FromQuery`, `WithHeadings`, `WithMapping`, `WithStyles`, `WithEvents`
- Features:
  - Auto-sized columns
  - Bold header row
  - Currency formatting for financial columns
  - Date formatting
  - Auto-filters on headers
  - Current customer calculation

---

### **4. View Meter Page** (NEW!)

#### **Route:**
- Path: `/{record}`
- Class: `ViewMeter extends ViewRecord`

#### **Page Structure:**

**Header:**
- Title: "Meter {meter_number}"
- Subtitle: "Serial: {serial_number}"
- Actions:
  - Edit Meter
  - Deactivate/Activate (conditional)
  - Record Reading (opens modal)
  - Generate Bill (if active assignment)

**Infolist Sections:**

1. **Meter Information**
   - Grid: 3 columns
   - Fields:
     - Meter Number (large, bold, copyable)
     - Serial Number (copyable)
     - Meter Type (badge)
     - Meter Size
     - Status (badge - Connected/Disconnected)
     - Photo (if exists)

2. **Current Assignment** (conditional - if assigned)
   - Grid: 2 columns
   - Fields:
     - Customer Name (bold, clickable link)
     - Customer Phone (copyable)
     - Customer Email (copyable)
     - Assignment Date
     - Initial Reading (at assignment)
     - Connection Fee
     - Connection Fee Paid (badge)

3. **Installation Details**
   - Grid: 2 columns
   - Fields:
     - Installation Date
     - Installed By
     - Location (full text)
     - Manufacturer
     - Brand
     - Model

4. **Current Readings**
   - Grid: 3 columns
   - Fields:
     - Last Reading (large, bold)
     - Last Reading Date
     - Current Reading
     - Initial Reading (lifetime)

5. **Financial Summary**
   - Grid: 4 columns
   - Fields:
     - Current Balance (large, bold, red if > 0)
     - Credit/Overpayment (large, bold, green if > 0)
     - Total Billed (lifetime)
     - Total Paid (lifetime)
     - Last Invoice Date
     - Financial Status (badge)

6. **Technical Specifications**
   - Grid: 2 columns
   - Collapsible, collapsed by default
   - Fields:
     - Manufacturer
     - Brand
     - Model
     - Meter Size
     - Any metadata

**Relation Managers:**

1. **Assignment History** (primary tab)
   - Shows all meter assignments (active and inactive)
   - Columns:
     - Customer Name (clickable)
     - Assignment Date
     - Disconnection Date
     - Initial Reading
     - Final Reading (at disconnection)
     - Connection Fee
     - Connection Fee Paid
     - Status (Active/Inactive badge)
   - Actions: View Customer

2. **Invoices**
   - Shows all invoices for this meter
   - Uses existing invoice columns
   - Columns:
     - Invoice Number (clickable)
     - Invoice Date
     - Customer Name (clickable)
     - Total Amount
     - Paid Amount
     - Balance
     - Status
   - Actions: View Invoice, Make Payment
   - Default sort: Invoice Date DESC

3. **Payments**
   - Shows all payments for this meter
   - Columns:
     - Payment Date
     - Customer Name (clickable)
     - Invoice Number (clickable, or "Advance")
     - Amount
     - Method
     - Reference
     - Status
   - Actions: View Payment
   - Default sort: Payment Date DESC

4. **Bills/Meter Readings**
   - Shows billing history
   - Columns:
     - Bill Date
     - Customer Name (clickable)
     - Previous Reading
     - Current Reading
     - Consumption
     - Amount
     - Status
   - Actions: View Bill
   - Default sort: Bill Date DESC

---

### **5. Action Buttons & Permissions**

#### **List Actions (per row):**

**Dropdown Menu (⋮):**
1. **Edit**
   - Icon: `heroicon-o-pencil`
   - Color: Warning
   - Action: Navigate to edit page

2. **Deactivate/Activate** (conditional)
   - Visible: Based on current status
   - Icon: `heroicon-o-power` or `heroicon-o-bolt`
   - Color: Danger (deactivate) or Success (activate)
   - Action: Toggle meter status with confirmation
   - Updates: Meter status and active assignments

3. **View Readings**
   - Icon: `heroicon-o-chart-bar`
   - Color: Info
   - Action: Navigate to readings/bills tab on view page

4. **View Financial Summary**
   - Icon: `heroicon-o-currency-dollar`
   - Color: Success
   - Action: Navigate to financial tab on view page

**Standalone Button:**
5. **View**
   - Icon: `heroicon-o-eye`
   - Color: Info
   - Action: Navigate to view page

#### **Header Actions (on View Page):**
1. **Edit Meter**
   - Navigate to edit page

2. **Deactivate/Activate**
   - Conditional based on status
   - Confirmation modal

3. **Record Reading**
   - Opens modal with reading form
   - Auto-generates bill if applicable

4. **Generate Bill**
   - Visible if: Active assignment exists
   - Opens bill generation flow

---

### **6. Helper Class - MeterTableHelper**

Create: `app/Filament/Helpers/MeterTableHelper.php`

**Purpose:** Centralize table configuration for reusability

**Methods:**

1. `getColumns(bool $includeActions = true): array`
   - Returns all table columns
   - Optionally excludes actions column for relation managers

2. `getMeterNumberColumn(): TextColumn`
   - Meter number column (bold, clickable)

3. `getCurrentCustomerColumn(): TextColumn`
   - Current customer name with assignment status

4. `getLocationColumn(): TextColumn`
   - Location with truncation

5. `getTypeColumn(): TextColumn`
   - Meter type badge

6. `getStatusColumn(): TextColumn`
   - Combined connection + financial status

7. `getBalanceCreditColumn(): TextColumn`
   - Combined balance/credit display

8. `getLastBillDateColumn(): TextColumn`
   - Last invoice date

9. `getActions(bool $isViewPage = false): array`
   - Returns action buttons/dropdown
   - Different actions for list vs view page

10. `getFilters(): array`
    - Returns all filter configurations

11. `getDeactivateFormSchema(): array`
    - Form schema for deactivation modal

---

### **7. Additional Enhancements**

#### **Row Styling:**
- Inactive/Disconnected meters: Light gray background (`bg-gray-50`)
- Meters with outstanding balance: Light red left border (`border-l-4 border-red-500`)
- Meters with credit: Light green left border (`border-l-4 border-green-500`)

#### **Badges & Colors:**

**Meter Type Colors:**
- Residential: `primary` (blue)
- Commercial: `success` (green)
- Industrial: `warning` (orange)
- General: `gray`

**Connection Status Colors:**
- Connected/Active: `success` (green)
- Disconnected/Inactive: `danger` (red)

**Financial Status Colors:**
- Outstanding: `danger` (red)
- Credit: `success` (green)
- Clear: `gray`

#### **Search Functionality:**
- Global search: Meter number, serial number, location, customer name
- Fast and responsive

#### **Performance:**
- Eager load relationships: `currentAssignment.customer`, `invoices`, `payments`
- Indexes: Already exist on `meter_number`, `serial_number`, `tenant_id`, `status`
- Computed columns: Current customer calculated efficiently

---

## 📝 **Implementation Steps**

### **Phase 1: Helper Class & Export**
1. ✅ Create `MeterTableHelper.php`
2. ✅ Create `MetersExport.php`
3. ✅ Move column definitions to helper
4. ✅ Move filter definitions to helper
5. ✅ Move action definitions to helper

### **Phase 2: Table Refactoring**
1. ✅ Update `MeterResource::table()` to use helper
2. ✅ Add Current Customer column
3. ✅ Remove duplicate meter_type column
4. ✅ Combine Status columns (connection + financial)
5. ✅ Combine Balance/Credit column
6. ✅ Add Last Bill Date column
7. ✅ Add clickable links (meter, customer)
8. ✅ Add 5 filters in one row
9. ✅ Add export bulk action
10. ✅ Remove grouping
11. ✅ Add row selection

### **Phase 3: View Meter Page**
1. ✅ Create `ViewMeter.php` page
2. ✅ Define infolist with 6 sections
3. ✅ Add header actions (Edit, Deactivate/Activate, Record Reading, Generate Bill)
4. ✅ Add navigation route
5. ✅ Make all relevant fields clickable

### **Phase 4: Relation Managers**
1. ✅ Create `AssignmentHistoryRelationManager.php`
2. ✅ Create `MeterInvoicesRelationManager.php`
3. ✅ Create `MeterPaymentsRelationManager.php`
4. ✅ Create `MeterBillsRelationManager.php`
5. ✅ Set Assignment History as default tab

### **Phase 5: Actions & Modals**
1. ✅ Update Deactivate/Activate action
2. ✅ Add Record Reading action with modal
3. ✅ Add Generate Bill action
4. ✅ Add success/error notifications

### **Phase 6: Testing & Polish**
1. ✅ Test filtering combinations
2. ✅ Test export functionality
3. ✅ Test deactivate/activate actions
4. ✅ Test navigation links
5. ✅ Test relation managers
6. ✅ Verify responsive design
7. ✅ Check linter errors

---

## 🎯 **Key Improvements Summary**

1. **✅ Current Customer Display**: See who is assigned at a glance
2. **✅ Compressed Table**: From 21 columns to 8 essential columns
3. **✅ Comprehensive Filtering**: 5 filters including customer and financial status
4. **✅ Export**: Bulk export to Excel with 23 columns
5. **✅ View Page**: Dedicated meter details page
6. **✅ Relation Managers**: Assignment history, invoices, payments, bills
7. **✅ Better Status Display**: Combined connection + financial status
8. **✅ Financial Clarity**: Balance/Credit in one column
9. **✅ Clean Layout**: No duplicate columns, no grouping
10. **✅ Modern UI**: Consistent with invoice and payment improvements

---

## 📌 **Notes**

- **Backward Compatibility**: Existing meter creation and edit flows unchanged
- **Performance**: Eager loading for current customer prevents N+1 queries
- **Security**: Proper authorization via existing Gates
- **Consistency**: Matches invoice and payment list UX patterns
- **Mobile Responsive**: Filament tables are responsive by default

---

## 🚀 **Expected Outcome**

A modern, efficient, customer-visible meter registry with:
- **Better UX**: Clear, compact, informative
- **Better Functionality**: Filter, export, view details, see assignments
- **Better Navigation**: Click to related records
- **Better History**: Full assignment and billing history
- **Better Maintainability**: Centralized helper class
- **Better Performance**: Optimized queries with eager loading

---

## 📊 **Comparison: Before vs After**

| Feature | Before | After |
|---------|--------|-------|
| Current Customer | ❌ Not visible | ✅ Dedicated column |
| Table Width | ❌ 21 columns | ✅ 8 columns |
| Duplicate Columns | ❌ Meter type x2 | ✅ No duplicates |
| Filters | ❌ 3 basic | ✅ 5 comprehensive |
| Export | ❌ None | ✅ Excel with 23 cols |
| View Page | ❌ Redirects to edit | ✅ Dedicated view |
| Relation Managers | ❌ None | ✅ 4 managers |
| Grouping | ❌ Status/Location | ✅ None (cleaner) |
| Financial Display | ❌ 3 separate cols | ✅ 1 combined col |
| Assignment History | ❌ Not visible | ✅ Relation manager |

---

*Ready to proceed with implementation?* ✅

