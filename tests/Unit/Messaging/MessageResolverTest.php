<?php

namespace Tests\Unit\Messaging;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\InvoiceBill;
use App\Models\MessageTemplate;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Messages\MessageResolver;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageResolverTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;
    protected User $customer;
    protected Meter $meter;
    protected MeterAssignment $assignment;
    protected MessageResolver $messageResolver;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant
        $this->tenant = Tenant::factory()->create();

        // Create users
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);
        $this->customer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'John Doe',
            'telephone' => '712345678',
        ]);

        // Create required accounts
        Account::factory()->bank()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);
        Account::factory()->arControl()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);
        Account::factory()->customerPrepayment()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->admin->id,
        ]);

        // Create meter and assignment
        $this->meter = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'meter_number' => 'MTR-001',
            'location' => 'Nairobi',
            'balance' => 1500.00,
            'overpayment' => 200.00,
        ]);

        $this->assignment = MeterAssignment::factory()->create([
            'meter_id' => $this->meter->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
        ]);

        // Mock Auth
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->messageResolver = app(MessageResolver::class);
    }

    #[Test]
    public function it_resolves_general_message_with_meter_data()
    {
        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'GENERAL',
            'content' => 'Dear {customer_name}, meter {meter_number} balance: {balance}, credit: {overpayment}',
        ]);

        $result = $this->messageResolver->resolveGeneralMessage(
            $this->customer,
            $this->meter,
            $template
        );

        $this->assertStringContainsString('Dear John Doe', $result);
        $this->assertStringContainsString('meter MTR-001', $result);
        $this->assertStringContainsString('balance: 1,500.00', $result);
        $this->assertStringContainsString('credit: 200.00', $result);
    }

    #[Test]
    public function it_resolves_invoice_message_correctly()
    {
        // Create meter reading
        $reading = MeterReading::factory()->create([
            'meter_id' => $this->meter->id,
            'tenant_id' => $this->tenant->id,
            'reading_value' => 1500,
        ]);

        // Create invoice
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'invoice_number' => 'INV-001',
            'invoice_date' => now(),
        ]);

        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'INVOICE',
            'content' => 'Invoice {invoice_number} for meter {meter_number}. Amount: {amount}, Balance: {balance}',
        ]);

        $result = $this->messageResolver->resolveInvoiceMessage($invoice, $template);

        $this->assertStringContainsString('Invoice INV-001', $result);
        $this->assertStringContainsString('meter MTR-001', $result);
    }

    #[Test]
    public function it_resolves_payment_message_correctly()
    {
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 1000.00,
            'date' => now(),
        ]);

        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'PAYMENT',
            'content' => 'Dear {customer_name}, payment of {amount} received. Balance: {balance}',
        ]);

        $result = $this->messageResolver->resolvePaymentMessage($payment, $template);

        $this->assertStringContainsString('Dear John Doe', $result);
        $this->assertStringContainsString('payment of 1,000.00', $result);
    }

    #[Test]
    public function it_uses_meter_location_with_customer_fallback()
    {
        // Meter without location
        $meterNoLocation = Meter::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location' => null,
        ]);

        $customerWithLocation = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location' => 'Mombasa',
        ]);

        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'GENERAL',
            'content' => 'Location: {location}',
        ]);

        $result = $this->messageResolver->resolveGeneralMessage(
            $customerWithLocation,
            $meterNoLocation,
            $template
        );

        $this->assertStringContainsString('Location: Mombasa', $result);
    }

    #[Test]
    public function it_merges_extra_data_in_general_message()
    {
        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'GENERAL',
            'content' => 'Balance: {balance}, Custom: {custom_field}',
        ]);

        $result = $this->messageResolver->resolveGeneralMessage(
            $this->customer,
            $this->meter,
            $template,
            ['{custom_field}' => 'Custom Value']
        );

        $this->assertStringContainsString('Custom: Custom Value', $result);
    }

    #[Test]
    public function it_formats_numeric_values_correctly()
    {
        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'GENERAL',
            'content' => 'Balance: {balance}',
        ]);

        $result = $this->messageResolver->resolveGeneralMessage(
            $this->customer,
            $this->meter,
            $template
        );

        // Should be formatted with 2 decimals and thousand separator
        $this->assertStringContainsString('1,500.00', $result);
    }

    #[Test]
    public function it_replaces_all_tags_in_general_message_with_no_tags_remaining()
    {
        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'GENERAL',
            'content' => 'Dear {customer_name}, meter {meter_number} at {location} has balance {balance} and credit {overpayment}. Phone: {phone}',
        ]);

        $result = $this->messageResolver->resolveGeneralMessage(
            $this->customer,
            $this->meter,
            $template
        );

        // CRITICAL: Verify NO tags remain in the resolved message
        $this->assertStringNotContainsString('{customer_name}', $result, 
            'customer_name tag should be replaced');
        $this->assertStringNotContainsString('{meter_number}', $result, 
            'meter_number tag should be replaced');
        $this->assertStringNotContainsString('{location}', $result, 
            'location tag should be replaced');
        $this->assertStringNotContainsString('{balance}', $result, 
            'balance tag should be replaced');
        $this->assertStringNotContainsString('{overpayment}', $result, 
            'overpayment tag should be replaced');
        $this->assertStringNotContainsString('{phone}', $result, 
            'phone tag should be replaced');
        
        // Verify actual values are present
        $this->assertStringContainsString('John Doe', $result);
        $this->assertStringContainsString('MTR-001', $result);
        $this->assertStringContainsString('1,500.00', $result);
        $this->assertStringContainsString('200.00', $result);
    }

    #[Test]
    public function it_replaces_all_tags_in_payment_message_with_no_tags_remaining()
    {
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'meter_id' => $this->meter->id,
            'amount' => 1000.00,
            'date' => now(),
            'method' => 'mpesa',  // Actual column name
            'reference' => 'TXN123456',  // Actual column name
        ]);

        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'context' => 'PAYMENT',
            'content' => 'Dear {customer_name}, payment of {amount} received on {payment_date}. Balance: {balance}, Overpayment: {overpayment}. Method: {payment_method}, ID: {transaction_id}, Meter: {meter_number}',
        ]);

        $result = $this->messageResolver->resolvePaymentMessage($payment, $template);

        // CRITICAL: Verify NO tags remain
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $result,
            'No curly-brace tags should remain in the resolved payment message');
        
        // Verify all values replaced
        $this->assertStringContainsString('John Doe', $result);
        $this->assertStringContainsString('1,000.00', $result);
        $this->assertStringContainsString('Mpesa', $result);  // ucfirst of 'mpesa'
        $this->assertStringContainsString('TXN123456', $result);
    }
}


