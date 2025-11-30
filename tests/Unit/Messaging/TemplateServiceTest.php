<?php

namespace Tests\Unit\Messaging;

use App\Models\MessageTemplate;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Messages\TemplateService;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TemplateServiceTest extends TestCase
{
    protected Tenant $tenant;
    protected User $admin;
    protected TemplateService $templateService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create Tenant
        $this->tenant = Tenant::factory()->create();

        // Create admin user
        $this->admin = User::factory()->admin()->create(['tenant_id' => $this->tenant->id]);

        // Mock Auth
        Auth::shouldReceive('user')->andReturn($this->admin);
        Auth::shouldReceive('id')->andReturn($this->admin->id);

        // Initialize service
        $this->templateService = app(TemplateService::class);
    }

    #[Test]
    public function it_validates_placeholders_correctly()
    {
        $validContent = 'Hello {customer_name}, your balance is {balance}';
        $invalidContent = 'Hello [CUSTOMER_NAME], your balance is [AMOUNT]';

        $validResult = $this->templateService->validateTemplatePlaceholders($validContent);
        $invalidResult = $this->templateService->validateTemplatePlaceholders($invalidContent);

        $this->assertTrue($validResult['valid']);
        $this->assertEmpty($validResult['placeholders']);

        $this->assertFalse($invalidResult['valid']);
        $this->assertContains('[CUSTOMER_NAME]', $invalidResult['placeholders']);
        $this->assertContains('[AMOUNT]', $invalidResult['placeholders']);
    }

    #[Test]
    public function it_creates_custom_template()
    {
        $templateData = [
            'name' => 'Test Template',
            'context' => 'GENERAL',
            'category' => 'GENERAL',
            'content' => 'Hello {customer_name}',
            'is_active' => true,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
        ];

        $template = $this->templateService->createTemplate($templateData);

        $this->assertInstanceOf(MessageTemplate::class, $template);
        $this->assertEquals('Test Template', $template->name);
        $this->assertEquals('GENERAL', $template->context);
        $this->assertFalse($template->is_system);
        $this->assertTrue($template->is_active);
    }

    #[Test]
    public function it_updates_template()
    {
        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'name' => 'Original Name',
            'content' => 'Original content',
            'is_system' => false,
        ]);

        $updated = $this->templateService->updateTemplate($template, [
            'name' => 'Updated Name',
            'content' => 'Updated content',
        ]);

        $this->assertEquals('Updated Name', $updated->name);
        $this->assertEquals('Updated content', $updated->content);
    }

    #[Test]
    public function it_prevents_changing_is_system_flag()
    {
        $systemTemplate = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'name' => 'System Template',
            'context' => 'INVOICE',
            'category' => 'INVOICE',
            'is_system' => true,
        ]);

        // Try to change is_system flag
        $updated = $this->templateService->updateTemplate($systemTemplate, [
            'is_system' => false,  // Should be ignored
        ]);

        // Verify is_system flag wasn't changed
        $this->assertTrue($updated->is_system, 'is_system flag should remain true');
    }

    #[Test]
    public function it_activates_and_deactivates_templates()
    {
        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'is_active' => true,
        ]);

        // Deactivate
        $deactivated = $this->templateService->deactivateTemplate($template);
        $this->assertFalse($deactivated->is_active);

        // Activate
        $activated = $this->templateService->activateTemplate($deactivated);
        $this->assertTrue($activated->is_active);
    }

    #[Test]
    public function it_deletes_custom_template()
    {
        $template = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'is_system' => false,
        ]);

        $this->templateService->deleteTemplate($template);

        $this->assertDatabaseMissing('message_templates', [
            'id' => $template->id,
        ]);
    }

    #[Test]
    public function it_prevents_deleting_system_template()
    {
        $systemTemplate = MessageTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'is_system' => true,
        ]);

        $this->expectException(\Exception::class);

        $this->templateService->deleteTemplate($systemTemplate);
    }

    #[Test]
    public function it_restores_system_templates_for_tenant()
    {
        // Restore system templates
        $this->templateService->restoreSystemTemplates($this->tenant->id);

        // Check that system templates were created
        $this->assertDatabaseHas('message_templates', [
            'tenant_id' => $this->tenant->id,
            'context' => 'INVOICE',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('message_templates', [
            'tenant_id' => $this->tenant->id,
            'context' => 'PAYMENT',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('message_templates', [
            'tenant_id' => $this->tenant->id,
            'context' => 'REMINDER',
            'is_system' => true,
        ]);
    }

    #[Test]
    public function it_seeds_starter_custom_templates()
    {
        $this->templateService->seedStarterTemplates($this->tenant->id);

        // Check that starter templates were created
        $starterCount = MessageTemplate::where('tenant_id', $this->tenant->id)
            ->where('is_system', false)
            ->count();

        $this->assertGreaterThan(0, $starterCount);
    }

    #[Test]
    public function it_detects_has_unresolved_placeholders()
    {
        $withPlaceholders = 'Hello [NAME], your balance is [AMOUNT]';
        $withoutPlaceholders = 'Hello {customer_name}, your balance is {balance}';

        $this->assertTrue($this->templateService->hasUnresolvedPlaceholders($withPlaceholders));
        $this->assertFalse($this->templateService->hasUnresolvedPlaceholders($withoutPlaceholders));
    }

    #[Test]
    public function it_validates_template_tags_against_context()
    {
        $validContent = 'Hello {customer_name}, balance: {balance}';
        $invalidContent = 'Hello {invalid_tag}';  // Tag not in GENERAL context

        $validResult = $this->templateService->validateTemplate($validContent, 'GENERAL');
        $this->assertTrue($validResult['valid'], 'Valid tags should pass validation');
        $this->assertEmpty($validResult['errors']);

        $invalidResult = $this->templateService->validateTemplate($invalidContent, 'GENERAL');
        $this->assertFalse($invalidResult['valid'], 'Invalid tags should fail validation');
        $this->assertNotEmpty($invalidResult['errors']);
    }
}

