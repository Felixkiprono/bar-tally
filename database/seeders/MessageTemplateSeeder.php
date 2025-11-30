<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Messages\TemplateService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class MessageTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * This seeder creates default system message templates for each tenant using TemplateService.
     */
    public function run(): void
    {
        $templateService = app(TemplateService::class);
        
        // Get all tenants
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            Log::warning('MessageTemplateSeeder: No tenants found. Please create tenants first.');
            $this->command->warn('No tenants found. Please create tenants first before seeding message templates.');
            return;
        }

        foreach ($tenants as $tenant) {
            $this->command->info("Seeding message templates for tenant: {$tenant->name}");
            
            // Get first admin user for this tenant
            $adminUser = User::where('tenant_id', $tenant->id)
                ->where('role', 'tenant_admin')
                ->first();

            if (!$adminUser) {
                $this->command->warn("No admin user found for tenant {$tenant->name}. Skipping...");
                continue;
            }

            // Use TemplateService to restore/create system templates
            $systemResult = $templateService->restoreSystemTemplates($tenant->id, $adminUser->id);
            
            $this->command->info("  ✓ System Templates - Created: {$systemResult['created']}, Updated: {$systemResult['updated']}, Total: {$systemResult['total']}");
            
            // Seed starter custom templates
            $customResult = $templateService->seedStarterTemplates($tenant->id, $adminUser->id);
            
            $this->command->info("  ✓ Custom Templates - Created: {$customResult['created']}, Skipped: {$customResult['skipped']}, Total: {$customResult['total']}");
        }

        $this->command->info('Message templates seeding completed!');
    }
}
