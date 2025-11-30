<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\Invoice\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Multitenancy\Jobs\TenantAware;

class TestJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The maximum number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 3600; // 60 minutes timeout

    /**
     * The tenant ID for this job.
     *
     * @var int|string|null
     */
    public $tenantId;

    /**
     * Create a new job instance.
     *
     * @param int|string|null $tenantId The tenant ID to use for this job
     */
    public function __construct($tenantId = null)
    {
        if ($tenantId) {
            $this->tenantId = $tenantId;
            return;
        }

        if (app()->bound('currentTenant') && app('currentTenant')) {
            $this->tenantId = app('currentTenant')->id;
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('TestJob: Starting execution');

        if (empty($this->tenantId)) {
            Log::error('TestJob: No tenant ID set');
            return;
        }

        try {
            $tenant = Tenant::find($this->tenantId);
            if (!$tenant) {
                Log::error("TestJob: Tenant {$this->tenantId} not found");
                return;
            }

            $tenant->makeCurrent();
            sleep(2); // Simulate work
            Log::info(message: 'TestJob: Completed successfully');
        } catch (\Exception $e) {
            Log::error('TestJob: Failed - ' . $e->getMessage());
            throw $e;
        }
    }
}