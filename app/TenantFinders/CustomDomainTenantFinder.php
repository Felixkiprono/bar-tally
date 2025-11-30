<?php

namespace App\TenantFinders;

use Illuminate\Http\Request;
use Spatie\Multitenancy\Models\Tenant;
use Spatie\Multitenancy\TenantFinder\TenantFinder;

class CustomDomainTenantFinder extends TenantFinder
{
    public function findForRequest(Request $request): ?Tenant
    {
        // If the request has our bypass flag, return null (no tenant needed)
        if ($request->attributes->get('bypassTenantCheck', false)) {
            return null;
        }

        // Otherwise, use the domain to find the tenant
        $host = $request->getHost();

        return Tenant::query()
            ->where('domain', $host)
            ->first();
    }
}