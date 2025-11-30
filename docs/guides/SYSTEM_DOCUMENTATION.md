# Hydra Billing System - Complete Documentation

## Table of Contents

1. [System Overview](#system-overview)
2. [Architecture & Technology Stack](#architecture--technology-stack)
3. [Core Features](#core-features)
4. [System Components](#system-components)
5. [Multitenancy Implementation](#multitenancy-implementation)
6. [Billing & Invoice Management](#billing--invoice-management)
7. [Meter Management System](#meter-management-system)
8. [Payment Processing](#payment-processing)
9. [Customer Management](#customer-management)
10. [SMS & Messaging System](#sms--messaging-system)
11. [Admin Panel Features](#admin-panel-features)
12. [Accounting & Financial Management](#accounting--financial-management)
13. [Reports & Analytics](#reports--analytics)
14. [API & Integration](#api--integration)
15. [Security & Authentication](#security--authentication)
16. [Job Queue System](#job-queue-system)
17. [Configuration Management](#configuration-management)
18. [Database Schema](#database-schema)
19. [Development & Testing](#development--testing)
20. [Deployment & Infrastructure](#deployment--infrastructure)

---

## System Overview

**Hydra Billing** is a comprehensive multi-tenant utility billing management system built with Laravel 12 and Filament PHP. It's designed to handle water, electricity, or other utility billing for multiple organizations (tenants) from a single application instance.

### Key Characteristics
- **Multi-tenant SaaS Architecture**: Each tenant operates with complete data isolation
- **Utility Billing Focus**: Specialized for meter-based consumption billing
- **Modern Tech Stack**: Laravel 12, Filament 3.x, PHP 8.2+
- **Real-time Processing**: Queue-based job processing with Laravel Horizon
- **Mobile-first Design**: Responsive admin interface with SMS integration

---

## Architecture & Technology Stack

### Core Technologies
- **Backend Framework**: Laravel 12.x
- **Admin Panel**: Filament 3.x
- **PHP Version**: 8.2+
- **Database**: MySQL/PostgreSQL (with SQLite for testing)
- **Queue System**: Redis with Laravel Horizon
- **Multitenancy**: Spatie Laravel Multitenancy
- **PDF Generation**: DomPDF
- **Excel Processing**: Maatwebsite Excel
- **SMS Integration**: Multiple providers (Leopard, Tilil, Buniflow)

### Architecture Patterns
- **Service Layer Pattern**: Business logic encapsulated in service classes
- **Repository Pattern**: Data access abstraction
- **Factory Pattern**: For SMS providers and data generation
- **Observer Pattern**: Model events and listeners
- **Command Pattern**: Console commands for batch processing
- **Strategy Pattern**: Multiple SMS providers with unified interface

---

## Core Features

### 1. Multi-Tenant Management
- Complete tenant isolation with domain-based routing
- Separate data contexts per tenant
- Tenant-specific configurations and customizations
- Scalable architecture supporting unlimited tenants

### 2. Comprehensive Billing System
- Automated meter reading-based bill generation
- Multiple bill types (consumption, connection, disconnection, penalties)
- Flexible rate configurations per tenant/customer
- Bulk billing operations
- Bill correction and reversal capabilities

### 3. Advanced Payment Processing
- Multiple payment methods (M-Pesa, Bank, Cash)
- Overpayment handling and advance payments
- Payment reversals and corrections
- Automated accounting entries
- Receipt generation

### 4. Meter Management
- Complete meter lifecycle management
- Customer-meter assignment tracking
- Reading validation and confirmation
- Photo capture for meter readings
- Meter status tracking (active, faulty, disconnected)

### 5. Customer Relationship Management
- Customer profile management
- Contact information tracking
- Balance and overpayment tracking
- Communication history
- Bulk customer operations

### 6. Communication System
- Multi-provider SMS integration
- Template-based messaging
- Automated notifications (invoices, payments, reminders)
- Bulk messaging capabilities
- Message delivery tracking

---

## System Components

### Models & Entities

#### Core Business Models
- **Tenant**: Multi-tenant organization data
- **User**: Customers, admins, and staff with role-based access
- **Meter**: Physical utility meters with tracking data
- **MeterAssignment**: Links customers to meters with lifecycle tracking
- **MeterReading**: Consumption readings with validation
- **Bill**: Individual billing items with various types
- **Invoice**: Consolidated customer statements
- **Payment**: Payment transactions with accounting integration

#### Supporting Models
- **Account**: Chart of accounts for financial tracking
- **Journal**: Double-entry accounting records
- **Configuration**: Tenant-specific settings and rates
- **MessageTemplate**: SMS/email templates
- **Contact**: Additional customer contact information

### Service Classes

#### Business Logic Services
- **BillService**: Core billing logic and rate calculations
- **InvoiceService**: Invoice generation and management
- **CustomerPaymentService**: Payment processing workflows
- **MeterAssignmentService**: Meter-customer relationship management
- **PaymentService**: Payment processing and accounting
- **SmsManager**: Multi-provider SMS handling

#### Specialized Services
- **BillBatchService**: Bulk billing operations
- **InvoiceActionService**: Invoice operations (payment, reversal, correction)
- **PaymentReversalService**: Payment correction handling
- **ReminderRuleService**: Automated reminder processing

---

## Multitenancy Implementation

### Domain-Based Tenant Resolution
```php
// Custom tenant finder resolves tenant by domain
class CustomDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        $host = $request->getHost();
        return Tenant::query()->where('domain', $host)->first();
    }
}
```

### Data Isolation Strategy
- **Tenant ID Column**: Every model includes `tenant_id` for data segregation
- **Query Scoping**: Automatic tenant filtering in Eloquent queries
- **Middleware Protection**: `SetCurrentTenantMiddleware` ensures tenant context
- **Container Binding**: Current tenant bound to service container

### Tenant Management Features
- Tenant creation with domain assignment
- Tenant-specific configurations
- Isolated user management per tenant
- Separate financial accounts per tenant

---

## Billing & Invoice Management

### Bill Types & Categories

#### Meter Reading Related
- `REGULAR_READING`: Standard consumption billing
- `SPECIAL_READING`: Non-standard reading cycles
- `ESTIMATED_READING`: Estimated consumption when meter inaccessible
- `CORRECTED_READING`: Corrections to previous readings
- `BACKDATED_READING`: Historical reading adjustments

#### Connection Related
- `NEW_CONNECTION`: Initial service connection fees
- `RECONNECTION`: Service restoration charges
- `CONNECTION_UPGRADE`: Service level improvements
- `CONNECTION_TRANSFER`: Account transfers
- `CONNECTION_EXTENSION`: Service area extensions

#### Disconnection Related
- `VOLUNTARY_DISCONNECTION`: Customer-requested disconnection
- `NON_PAYMENT_DISCONNECTION`: Service suspension for non-payment
- `SAFETY_DISCONNECTION`: Emergency disconnections
- `TEMPORARY_DISCONNECTION`: Temporary service suspension
- `PERMANENT_DISCONNECTION`: Permanent service termination

#### Additional Charges
- `LATE_PAYMENT_PENALTY`: Overdue payment penalties
- `ADMINISTRATIVE_FEE`: Administrative charges
- `INSPECTION_FEE`: Service inspection costs
- `MAINTENANCE_FEE`: Equipment maintenance charges
- `DAMAGE_CHARGES`: Equipment damage costs
- `TAMPERING_CHARGES`: Meter tampering penalties

### Billing Process Flow

1. **Meter Reading Capture**
   - Manual reading entry with photo validation
   - Reader assignment and confirmation workflow
   - Reading validation against previous readings

2. **Bill Generation**
   - Automatic consumption calculation
   - Rate application (tenant/customer-specific)
   - Service fee inclusion
   - Bill creation with proper categorization

3. **Invoice Consolidation**
   - Grouping bills by customer and meter assignment
   - Previous balance inclusion
   - Overpayment credit application
   - Final invoice generation

4. **Customer Notification**
   - SMS notification with invoice details
   - Template-based message generation
   - Multi-contact delivery (primary + additional contacts)

### Rate Management
- **Tenant-level Rates**: Default rates per tenant
- **Customer-specific Rates**: Override rates for individual customers
- **Configuration-based**: Stored in `configurations` table with keys like `CUSTOMER_CONFIG_RATE_{tenant_id}_{customer_id}`
- **Service Costs**: Additional charges like `SERVICE_COST` configuration

---

## Meter Management System

### Meter Lifecycle

#### Installation & Setup
- Meter registration with unique meter numbers
- Installation date and location tracking
- Initial reading capture
- Installer information recording
- Photo documentation

#### Assignment Management
- Customer-meter relationship tracking
- Assignment date recording
- Connection fee payment tracking
- Active/inactive status management
- Assignment history preservation

#### Reading Management
- Reading value capture with date/time
- Reader assignment and tracking
- Photo evidence requirement
- Confirmation workflow
- Consumption calculation

#### Status Tracking
- **Active**: Normal operation
- **Disconnected**: Service suspended
- **Faulty**: Equipment malfunction
- **Retired**: End of service life

### Meter Configuration
- Rate configurations per meter type
- Service cost settings
- Reading validation rules
- Billing cycle configurations

---

## Payment Processing

### Payment Methods
- **M-Pesa**: Mobile money integration
- **Bank Transfer**: Direct bank payments
- **Cash**: Physical cash payments
- **Other**: Flexible payment method support

### Payment Processing Flow

1. **Payment Receipt**
   - Payment amount and method capture
   - Reference number tracking
   - Payment date recording

2. **Invoice Application**
   - Latest unpaid invoice identification
   - Payment allocation to invoice balance
   - Overpayment handling for excess amounts

3. **Accounting Integration**
   - Bank account debit entries
   - Accounts receivable credit entries
   - Customer prepayment liability for overpayments
   - Journal entry creation

4. **Customer Balance Updates**
   - Outstanding balance reduction
   - Overpayment credit addition
   - Account status updates

### Advanced Payment Features

#### Overpayment Management
- Automatic overpayment detection
- Credit application to future invoices
- Advance payment recording
- Customer prepayment liability tracking

#### Payment Reversals
- Payment correction capabilities
- Accounting entry reversals
- Customer notification of reversals
- Audit trail maintenance

#### Quick Payment Processing
- Latest invoice identification
- Suggested payment amounts
- One-click payment processing
- Immediate balance updates

---

## Customer Management

### Customer Profile Management
- Complete customer information tracking
- Contact details management
- Service address recording
- Account status monitoring

### Customer Types & Roles
- **ROLE_CUSTOMER**: Standard utility customers
- **ROLE_TENANT_ADMIN**: Tenant administrators
- **ROLE_SUPER_ADMIN**: System administrators
- **ROLE_METER_READER**: Field staff for readings

### Customer Balance Tracking
- Outstanding balance monitoring
- Overpayment credit tracking
- Payment history maintenance
- Account aging analysis

### Bulk Customer Operations
- Mass customer import via CSV
- Bulk SMS messaging
- Batch payment processing
- Group customer management

---

## SMS & Messaging System

### Multi-Provider SMS Integration

#### Supported Providers
- **Leopard SMS**: Primary SMS provider
- **Tilil SMS**: Alternative provider
- **Buniflow SMS**: Backup provider

#### Provider Management
```php
class SmsManager
{
    public static function getProvider(): SmsProvider
    {
        $providerName = config('services.sms.default', 'leopard');
        
        switch (strtolower($providerName)) {
            case 'leopard':
                return new Leopard();
            case 'tilil':
                return new Tilil();
            case 'buniflow':
            default:
                return new Buniflow();
        }
    }
}
```

### Message Templates
- **InvoiceNotification**: New invoice alerts
- **PaymentConfirmation**: Payment receipt confirmations
- **ReminderMessages**: Payment reminders
- **CustomMessages**: Ad-hoc communications

### Messaging Features
- Template-based message generation
- Variable substitution (customer name, amounts, dates)
- Multi-contact delivery (primary + additional contacts)
- Queue-based message processing
- Delivery status tracking
- Bulk messaging capabilities

### Message Queuing
- Background SMS processing via Laravel queues
- Failed message retry mechanisms
- Message delivery tracking
- Performance optimization for bulk sends

---

## Admin Panel Features

### Filament-based Administration
The system uses Filament 3.x for a modern, responsive admin interface with the following resource management capabilities:

#### Customer Management
- **CustomerResource**: Complete customer lifecycle management
- Customer creation, editing, and profile management
- Meter assignment tracking
- Invoice and payment history
- Contact management
- Bulk operations (import, SMS, payments)

#### Billing & Invoice Management
- **BillResource**: Individual bill management with bulk creation
- **InvoiceResource**: Invoice generation and management
- Bill-to-invoice relationship tracking
- Payment application and corrections
- Invoice actions (payment, reversal, SMS)

#### Meter Management
- **MeterResource**: Meter registration and lifecycle tracking
- **MeterReadingResource**: Reading capture and validation
- **MeterAssignmentResource**: Customer-meter relationship management
- **MeterConfigurationResource**: Rate and configuration management

#### Payment Processing
- **PaymentResource**: Payment recording and management
- Payment method tracking
- Reference number management
- Customer payment history
- Payment reversals and corrections

#### Financial Management
- **JournalResource**: Accounting entry management
- **BankAccountResource**: Bank account configuration
- Double-entry bookkeeping oversight
- Financial report generation

#### System Configuration
- **ConfigurationResource**: Tenant-specific settings
- **MessageTemplateResource**: SMS template management
- **UserResource**: User and role management
- **BillTypeResource**: Bill category management

#### Analytics & Reporting
- **StatsResource**: System analytics dashboard
- **ReportResource**: Financial and operational reports
- **CustomerStatementResource**: Customer account statements
- Real-time dashboard widgets
- Performance metrics tracking

### Navigation Structure
```
Customer Management
├── Customers
├── Customer Statements
└── Customer Reports

Billing & Invoicing
├── Bills
├── Invoices
├── Bill Types
└── Bulk Operations

Meter Management
├── Meters
├── Meter Readings
├── Meter Assignments
└── Meter Configurations

Financial Management
├── Payments
├── Bank Accounts
├── Journal Entries
└── Account Balances

Communications
├── SMS Messages
├── Message Templates
└── SMS Settings

Reports & Analytics
├── Analytics Dashboard
├── Financial Reports
├── Customer Reports
└── Operational Reports

Settings
├── General Configuration
├── User Management
├── Invoice Configuration
└── System Settings
```

---

## Accounting & Financial Management

### Double-Entry Bookkeeping
The system implements proper double-entry accounting with the following account structure:

#### Asset Accounts
- **BANK-001**: Main bank account for payment receipts
- **AR-CONTROL**: Accounts receivable control account

#### Liability Accounts
- **CUSTOMER-PREPAYMENT**: Customer overpayments and advance payments

#### Revenue Accounts
- Utility service revenue accounts
- Connection fee revenue
- Penalty and fee revenue

### Journal Entry System
Every financial transaction creates appropriate journal entries:

#### Payment Processing Entries
```php
// Bank account debit (asset increase)
Journal::create([
    'account_id' => $bankAccount->id,
    'amount' => $paymentAmount,
    'type' => 'debit',
    'description' => 'Payment received'
]);

// AR control credit (asset decrease)
Journal::create([
    'account_id' => $arControl->id,
    'amount' => $invoicePayment,
    'type' => 'credit',
    'description' => 'Invoice payment'
]);

// Customer prepayment credit for overpayments (liability increase)
Journal::create([
    'account_id' => $prepaymentAccount->id,
    'amount' => $overpaymentAmount,
    'type' => 'credit',
    'description' => 'Customer overpayment'
]);
```

### Financial Reporting
- Trial balance generation
- Customer account statements
- Revenue reports
- Outstanding balance reports
- Payment collection reports

---

## Reports & Analytics

### Dashboard Analytics
- Customer count and growth metrics
- Revenue tracking and trends
- Outstanding balance summaries
- Payment collection rates
- Meter reading completion rates

### Financial Reports
- **Revenue Reports**: Income analysis by period
- **Outstanding Balance Reports**: Aging analysis
- **Payment Collection Reports**: Collection efficiency
- **Customer Statement Reports**: Individual account summaries

### Operational Reports
- **Meter Reading Reports**: Reading completion tracking
- **Bill Generation Reports**: Billing cycle analysis
- **SMS Delivery Reports**: Communication effectiveness
- **User Activity Reports**: System usage tracking

### Export Capabilities
- PDF report generation via DomPDF
- Excel export via Maatwebsite Excel
- CSV data export for external analysis
- Scheduled report generation

---

## API & Integration

### RESTful API Endpoints
- Customer management endpoints
- Payment processing APIs
- Meter reading submission
- Invoice retrieval
- SMS messaging APIs

### Mobile App Integration
- Meter reader mobile app support
- Customer portal APIs
- Payment gateway integration
- Real-time data synchronization

### Third-party Integrations
- SMS provider APIs
- Payment gateway integration
- Accounting system exports
- Bank reconciliation imports

---

## Security & Authentication

### Multi-level Authentication
- **Super Admin**: System-wide administration
- **Tenant Admin**: Tenant-specific administration
- **Meter Reader**: Field staff with limited access
- **Customer**: Self-service portal access

### Security Features
- Role-based access control (RBAC)
- Tenant data isolation
- Password encryption
- Session management
- CSRF protection
- SQL injection prevention

### Audit Trail
- User action logging
- Financial transaction tracking
- Data modification history
- Access attempt monitoring

---

## Job Queue System

### Queue-Based Processing
The system uses Laravel queues with Redis for background processing:

#### Job Types
- **GenerateInvoicesJob**: Batch invoice generation
- **SendSmsJob**: Bulk SMS processing
- **SimpleSendSmsJob**: Individual SMS sending
- **ProcessCustomerImport**: Customer data import
- **SendInvoiceSms**: Invoice notification SMS

#### Queue Management
- Laravel Horizon for queue monitoring
- Failed job retry mechanisms
- Job priority management
- Queue worker scaling

### Background Processing Benefits
- Improved user experience with non-blocking operations
- Scalable SMS processing
- Reliable invoice generation
- Efficient bulk operations

---

## Configuration Management

### Tenant-Specific Configuration
Each tenant can customize system behavior through configuration settings:

#### Rate Configuration
- `METER_READING`: Base rate per unit
- `SERVICE_COST`: Fixed service charges
- `CUSTOMER_CONFIG_RATE_{tenant_id}_{customer_id}`: Customer-specific rates

#### System Configuration
- SMS provider settings
- Payment method configurations
- Invoice templates
- Reminder rules

#### Message Templates
- Invoice notification templates
- Payment confirmation templates
- Reminder message templates
- Custom message templates

---

## Database Schema

### Core Tables

#### Tenants & Users
- `tenants`: Multi-tenant organization data
- `users`: System users with roles
- `contacts`: Additional customer contacts

#### Meters & Readings
- `meters`: Physical meter information
- `meter_assignments`: Customer-meter relationships
- `meter_readings`: Consumption readings
- `meter_configurations`: Rate and configuration data

#### Billing & Invoicing
- `bills`: Individual billing items
- `invoices`: Customer statements
- `invoice_bills`: Bill-invoice relationships
- `bill_types`: Billing categories

#### Financial Management
- `payments`: Payment transactions
- `accounts`: Chart of accounts
- `journals`: Double-entry accounting records
- `receipts`: Payment receipts

#### Configuration & Communication
- `configurations`: System settings
- `message_templates`: Communication templates
- `messages`: Message history
- `reminder_rules`: Automated reminder settings

### Database Relationships
```
Tenant (1) -> (N) Users
Tenant (1) -> (N) Meters
Tenant (1) -> (N) Bills
Tenant (1) -> (N) Invoices

User (1) -> (N) MeterAssignments
Meter (1) -> (N) MeterAssignments
Meter (1) -> (N) MeterReadings

MeterAssignment (1) -> (N) Bills
Bills (N) -> (N) Invoices (through invoice_bills)

Invoice (1) -> (N) Payments
Payment (1) -> (N) Journals
```

---

## Development & Testing

### Testing Strategy
The system implements comprehensive testing with the following approach:

#### Test Environment Setup
```php
// TestCase.php - Base test configuration
protected function setUp(): void
{
    parent::setUp();
    
    // SQLite in-memory database for tests
    config(['database.default' => 'sqlite']);
    config(['database.connections.sqlite.database' => ':memory:']);
    
    // Disable multitenancy for tests
    config(['multitenancy.switch_tenant_tasks' => []]);
    
    // Fresh migrations for each test
    $this->artisan('migrate:fresh');
}
```

#### Testing Conventions
- **Factory Usage**: All models use factories for test data generation
- **Tenant Setup**: Standard tenant creation in test setUp methods
- **User Roles**: Admin and customer users created per test
- **Authentication Mocking**: Auth facade mocking for user context
- **PHPUnit Attributes**: Modern `#[Test]` attributes instead of docblocks

#### Test Categories
- **Unit Tests**: Service class testing, model relationships
- **Feature Tests**: End-to-end workflow testing
- **Integration Tests**: External service integration testing

### Development Standards
- **PSR-12 Coding Standards**: Consistent code formatting
- **SOLID Principles**: Clean architecture implementation
- **Laravel Conventions**: Framework best practices
- **Type Declarations**: Strict typing throughout
- **Documentation**: Comprehensive inline documentation

---

## Deployment & Infrastructure

### Docker Configuration
The system includes Docker support for containerized deployment:

```dockerfile
# Dockerfile for Laravel application
FROM php:8.2-fpm
# PHP extensions, Composer, application setup
```

### Environment Configuration
- **Production**: Optimized for performance and security
- **Staging**: Testing environment with production-like data
- **Development**: Local development with debugging enabled
- **Testing**: Automated testing environment

### Queue Processing
- Redis for queue backend
- Laravel Horizon for queue monitoring
- Supervisor for process management
- Horizontal scaling support

### Database Considerations
- **MySQL/PostgreSQL**: Production database options
- **Database Indexing**: Optimized for query performance
- **Backup Strategy**: Regular automated backups
- **Migration Management**: Version-controlled schema changes

---

## Performance Optimization

### Caching Strategy
- **Query Caching**: Eloquent query result caching
- **Configuration Caching**: Laravel configuration caching
- **Route Caching**: Compiled route caching
- **View Caching**: Blade template caching

### Database Optimization
- **Indexing**: Strategic database indexing
- **Query Optimization**: N+1 query prevention
- **Eager Loading**: Relationship preloading
- **Database Connection Pooling**: Connection management

### Queue Optimization
- **Background Processing**: Non-blocking operations
- **Batch Processing**: Efficient bulk operations
- **Queue Prioritization**: Critical job prioritization
- **Failed Job Handling**: Automatic retry mechanisms

---

## Monitoring & Logging

### Application Monitoring
- **Laravel Telescope**: Development debugging
- **Laravel Horizon**: Queue monitoring
- **Error Tracking**: Exception monitoring
- **Performance Monitoring**: Response time tracking

### Logging Strategy
- **Structured Logging**: Consistent log formatting
- **Log Levels**: Appropriate severity levels
- **Log Rotation**: Automated log management
- **Centralized Logging**: Log aggregation for multi-instance deployments

### Health Checks
- **Database Connectivity**: Connection health monitoring
- **Queue Processing**: Queue worker health checks
- **SMS Provider Status**: Communication service monitoring
- **Disk Space Monitoring**: Storage utilization tracking

---

## Maintenance & Support

### Regular Maintenance Tasks
- **Database Cleanup**: Old record archival
- **Log Rotation**: Log file management
- **Cache Clearing**: Periodic cache refresh
- **Queue Monitoring**: Failed job resolution

### Backup Strategy
- **Database Backups**: Regular automated backups
- **File System Backups**: Application and storage backups
- **Backup Testing**: Regular restore testing
- **Disaster Recovery**: Recovery procedure documentation

### Update Procedures
- **Framework Updates**: Laravel version management
- **Dependency Updates**: Package version management
- **Security Patches**: Timely security updates
- **Feature Deployments**: Controlled feature rollouts

---

## Conclusion

The Hydra Billing System represents a comprehensive, modern approach to utility billing management with multi-tenant capabilities. Its architecture supports scalability, maintainability, and extensibility while providing a rich feature set for utility companies and service providers.

The system's modular design, comprehensive testing strategy, and modern technology stack ensure reliable operation and easy maintenance. The multi-tenant architecture allows for efficient resource utilization while maintaining complete data isolation between tenants.

Key strengths of the system include:
- **Scalable Architecture**: Supports unlimited tenants and customers
- **Comprehensive Feature Set**: Complete billing lifecycle management
- **Modern Technology Stack**: Built with current best practices
- **Flexible Configuration**: Adaptable to various business requirements
- **Robust Testing**: Comprehensive test coverage for reliability
- **Professional UI**: Modern admin interface with Filament
- **Integration Ready**: APIs and webhooks for external integration

This documentation serves as a complete reference for developers, administrators, and stakeholders working with the Hydra Billing System.
