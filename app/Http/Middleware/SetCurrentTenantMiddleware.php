<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SetCurrentTenantMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $tenantFound = false;
        $tenantId = null;
        $debugInfo = [];

        try {
            // Get the host/domain from the request
            $host = $request->getHost();
            $debugInfo['request_host'] = $host;

            // Check the tenants table structure
            try {
                $tableExists = Schema::hasTable('tenants');
                $debugInfo['tenants_table_exists'] = $tableExists;

                if ($tableExists) {
                    $columns = Schema::getColumnListing('tenants');
                    $debugInfo['tenants_columns'] = $columns;

                    // Check for domain field
                    $hasDomainField = in_array('domain', $columns);
                    $debugInfo['has_domain_field'] = $hasDomainField;
                }
            } catch (\Exception $e) {
                $debugInfo['schema_check_error'] = $e->getMessage();
            }

            // Get all tenants for debugging
            try {
                $allTenants = DB::table('tenants')->get();
                $debugInfo['total_tenants'] = count($allTenants);
                $debugInfo['all_tenants'] = $allTenants->toArray();
            } catch (\Exception $e) {
                $debugInfo['db_query_error'] = $e->getMessage();
            }

            // Find the tenant by domain
            $tenant = Tenant::where('domain', $host)->first();
            $debugInfo['tenant_query_result'] = $tenant ? 'Found' : 'Not found';

            // If tenant found, bind it to the application container
            if ($tenant) {
                app()->instance('currentTenant', $tenant);
                $tenantFound = true;
                $tenantId = $tenant->id;
                $debugInfo['tenant_id'] = $tenant->id;
                $debugInfo['tenant_name'] = $tenant->name;
                //Log::info("Tenant set successfully", ['tenant_id' => $tenant->id, 'domain' => $host]);
            } else {
                Log::warning("No tenant found for domain", ['domain' => $host]);
            }

        } catch (\Exception $e) {
            // Log the error but allow the request to continue
            $debugInfo['error'] = $e->getMessage();
            $debugInfo['error_trace'] = $e->getTraceAsString();

            Log::error("Error setting tenant context", [
                'message' => $e->getMessage(),
                'domain' => $request->getHost(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        // Store debug info in the request for the test route
        $request->attributes->set('tenant_debug_info', $debugInfo);

        // Get the response and add debug headers
        $response = $next($request);

        if (method_exists($response, 'header')) {
            $response->header('X-Tenant-Detected', $tenantFound ? 'Yes' : 'No');
            if ($tenantId) {
                $response->header('X-Tenant-ID', $tenantId);
            }
        }

        return $response;
    }
}
