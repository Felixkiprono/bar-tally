<?php

namespace App\Console\Commands;

use App\Jobs\TestJob;
use App\Models\Tenant;
use Illuminate\Console\Command;

class TestJobCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:job {tenant? : The ID of the tenant to run the job for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch TestJob for testing purposes';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tenantId = $this->argument('tenant');

        if (!$tenantId) {
            // If no tenant ID provided, try to get from current tenant
            if (app()->bound('currentTenant') && app('currentTenant')) {
                $tenantId = app('currentTenant')->id;
            } else {
                $this->error('No tenant ID provided and no current tenant found');
                return 1;
            }
        }

        // Verify tenant exists
        if (!Tenant::find($tenantId)) {
            $this->error("Tenant with ID {$tenantId} not found");
            return 1;
        }

        $this->info("Dispatching TestJob for tenant ID: {$tenantId}");
        TestJob::dispatch($tenantId);
        $this->info('TestJob dispatched successfully');
    }
}
