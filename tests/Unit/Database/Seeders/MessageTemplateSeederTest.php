<?php

namespace Tests\Unit\Database\Seeders;

use App\Models\Account;
use App\Models\Invoice;
use App\Models\Meter;
use App\Models\MeterAssignment;
use App\Models\MeterReading;
use App\Models\MessageTemplate;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Messages\MessageResolver;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MessageTemplateSeederTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Create tenant
        $this->tenant = Tenant::factory()->create();
        
        // Create admin user for the seeder
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'tenant_admin',
        ]);
    }

    #[Test]
    public function it_seeds_all_required_system_templates()
    {
        // Run seeder
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        // Check INVOICE template exists and has correct structure
        $invoiceTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'INVOICE')
            ->where('is_system', true)
            ->first();

        $this->assertNotNull($invoiceTemplate, 'INVOICE system template should be seeded');
        $this->assertEquals('Invoice Notification', $invoiceTemplate->name);
        $this->assertTrue($invoiceTemplate->is_active);
        $this->assertEquals('INVOICE', $invoiceTemplate->category);
        
        // Verify critical tags are present in content
        $this->assertStringContainsString('{customer_name}', $invoiceTemplate->content);
        $this->assertStringContainsString('{invoice_number}', $invoiceTemplate->content);
        $this->assertStringContainsString('{meter_number}', $invoiceTemplate->content);
        $this->assertStringContainsString('{balance}', $invoiceTemplate->content);
        $this->assertStringContainsString('{amount}', $invoiceTemplate->content);
    }

    #[Test]
    public function it_seeds_payment_template_with_correct_tags()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $paymentTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'PAYMENT')
            ->where('is_system', true)
            ->first();

        $this->assertNotNull($paymentTemplate, 'PAYMENT system template should be seeded');
        $this->assertEquals('Payment Confirmation', $paymentTemplate->name);
        
        // CRITICAL: Check for the correct tag (not {payment_amount})
        $this->assertStringContainsString('{amount}', $paymentTemplate->content, 
            'Payment template should use {amount} tag, not {payment_amount}');
        $this->assertStringNotContainsString('{payment_amount}', $paymentTemplate->content,
            'Payment template should NOT use {payment_amount} - should be {amount}');
        
        // Verify other critical tags
        $this->assertStringContainsString('{customer_name}', $paymentTemplate->content);
        $this->assertStringContainsString('{payment_date}', $paymentTemplate->content);
        $this->assertStringContainsString('{balance}', $paymentTemplate->content);
        $this->assertStringContainsString('{overpayment}', $paymentTemplate->content);
    }

    #[Test]
    public function it_seeds_reminder_templates()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $reminderTemplates = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'REMINDER')
            ->where('is_system', true)
            ->get();

        $this->assertGreaterThanOrEqual(2, $reminderTemplates->count(), 
            'Should seed at least 2 REMINDER templates (normal and urgent)');
        
        // Verify reminder tags
        foreach ($reminderTemplates as $template) {
            $this->assertStringContainsString('{customer_name}', $template->content);
            $this->assertStringContainsString('{balance}', $template->content);
            $this->assertStringContainsString('{days_overdue}', $template->content);
        }
    }

    #[Test]
    public function it_seeds_meter_reading_template()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $meterReadingTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'METER_READING')
            ->where('is_system', true)
            ->first();

        $this->assertNotNull($meterReadingTemplate, 'METER_READING system template should be seeded');
        
        // Verify meter reading specific tags
        $this->assertStringContainsString('{meter_number}', $meterReadingTemplate->content);
        $this->assertStringContainsString('{current_reading}', $meterReadingTemplate->content);
        $this->assertStringContainsString('{previous_reading}', $meterReadingTemplate->content);
        $this->assertStringContainsString('{consumption}', $meterReadingTemplate->content);
    }

    #[Test]
    public function it_seeds_starter_custom_templates()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $customTemplates = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('is_system', false)
            ->get();

        $this->assertGreaterThan(0, $customTemplates->count(), 
            'Should seed starter custom templates');
        
        // Verify they are active and have proper categories
        foreach ($customTemplates as $template) {
            $this->assertTrue($template->is_active, 
                "Custom template '{$template->name}' should be active by default");
            $this->assertContains($template->category, ['GENERAL', 'REMINDER'], 
                "Custom template '{$template->name}' should have GENERAL or REMINDER category");
        }
    }

    #[Test]
    public function it_does_not_create_duplicate_system_templates()
    {
        // Run seeder twice
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        // Check that we still only have one INVOICE template per tenant
        $invoiceCount = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'INVOICE')
            ->where('is_system', true)
            ->count();

        $this->assertEquals(1, $invoiceCount, 
            'Should not create duplicate system templates when seeder runs multiple times');
    }

    #[Test]
    public function it_validates_no_unresolved_placeholders_in_system_templates()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $systemTemplates = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('is_system', true)
            ->get();

        foreach ($systemTemplates as $template) {
            // Check for unresolved placeholders [PLACEHOLDER_NAME]
            $hasPlaceholders = preg_match('/\[([A-Z][A-Z0-9_]*)\]/', $template->content);
            
            $this->assertFalse((bool) $hasPlaceholders, 
                "System template '{$template->name}' should NOT have unresolved [PLACEHOLDERS]. Found in: {$template->content}");
        }
    }

    #[Test]
    public function it_ensures_invoice_template_matches_actual_production_message()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $invoiceTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'INVOICE')
            ->where('is_system', true)
            ->first();

        // This template should match what was in InvoiceService.php
        // Verify key phrases from the actual production message
        $this->assertStringContainsString('your bill', $invoiceTemplate->content);
        $this->assertStringContainsString('Previous reading', $invoiceTemplate->content);
        $this->assertStringContainsString('Current:', $invoiceTemplate->content);
        $this->assertStringContainsString('Total payable', $invoiceTemplate->content);
        $this->assertStringContainsString('late fee', $invoiceTemplate->content);
    }

    #[Test]
    public function it_ensures_payment_template_matches_actual_production_message()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $paymentTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'PAYMENT')
            ->where('is_system', true)
            ->first();

        // This template should match what was in PaymentService.php
        // Verify key phrases from the actual production message
        $this->assertStringContainsString('received your payment', $paymentTemplate->content);
        $this->assertStringContainsString('current balance', $paymentTemplate->content);
        $this->assertStringContainsString('Thank you', $paymentTemplate->content);
    }

    #[Test]
    public function it_verifies_all_system_templates_have_available_tags()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        $systemTemplates = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('is_system', true)
            ->get();

        foreach ($systemTemplates as $template) {
            $this->assertNotNull($template->available_tags, 
                "System template '{$template->name}' should have available_tags defined");
            
            $this->assertIsArray($template->available_tags, 
                "System template '{$template->name}' available_tags should be an array");
            
            $this->assertGreaterThan(0, count($template->available_tags), 
                "System template '{$template->name}' should have at least one available tag");
        }
    }

    #[Test]
    public function it_can_resolve_invoice_template_without_errors()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        // Create test data
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
        
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);

        $customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        MeterAssignment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'meter_id' => $meter->id,
            'customer_id' => $customer->id,
        ]);
        MeterReading::factory()->create([
            'tenant_id' => $this->tenant->id,
            'meter_id' => $meter->id,
            'reader_id' => $this->admin->id,
        ]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'meter_id' => $meter->id,
        ]);

        $invoiceTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'INVOICE')
            ->where('is_system', true)
            ->first();

        $resolver = app(MessageResolver::class);
        
        // CRITICAL: Attempt to resolve the seeded template
        $resolved = $resolver->resolveInvoiceMessage($invoice, $invoiceTemplate);

        // Verify no tags remain
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $resolved,
            'Seeded INVOICE template should have all tags replaced when resolved');
        
        // Verify it's not empty
        $this->assertNotEmpty($resolved);
        $this->assertGreaterThan(50, strlen($resolved), 'Resolved message should be substantial');
    }

    #[Test]
    public function it_can_resolve_payment_template_without_errors()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        $customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'meter_id' => $meter->id,
        ]);

        $paymentTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'PAYMENT')
            ->where('is_system', true)
            ->first();

        $resolver = app(MessageResolver::class);
        
        // CRITICAL: Attempt to resolve the seeded template
        $resolved = $resolver->resolvePaymentMessage($payment, $paymentTemplate);

        // Verify no tags remain
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $resolved,
            'Seeded PAYMENT template should have all tags replaced when resolved');
        
        // Verify critical data is present
        $this->assertStringContainsString($customer->name, $resolved);
        $this->assertNotEmpty($resolved);
    }

    #[Test]
    public function it_can_resolve_reminder_template_without_errors()
    {
        Artisan::call('db:seed', ['--class' => 'MessageTemplateSeeder']);

        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);
        
        Account::factory()->bank()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->arControl()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);
        Account::factory()->customerPrepayment()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id]);

        $customer = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $meter = Meter::factory()->create(['tenant_id' => $this->tenant->id]);
        MeterAssignment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'meter_id' => $meter->id,
            'customer_id' => $customer->id,
        ]);
        $invoice = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'meter_id' => $meter->id,
        ]);

        $reminderTemplate = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('context', 'REMINDER')
            ->where('is_system', true)
            ->first();

        $resolver = app(MessageResolver::class);
        
        // CRITICAL: Attempt to resolve the seeded template
        $resolved = $resolver->resolveReminderMessage($invoice, null, $reminderTemplate);

        // Verify no tags remain
        $this->assertDoesNotMatchRegularExpression('/\{[a-z_]+\}/', $resolved,
            'Seeded REMINDER template should have all tags replaced when resolved');
        
        $this->assertNotEmpty($resolved);
    }
}


