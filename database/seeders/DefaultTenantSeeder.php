<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DefaultTenantSeeder extends Seeder
{
    /**
     * Create or update the default tenant from APP_URL so the app can resolve
     * the current domain (e.g. barmetriks.app) and avoid "No tenant found for domain".
     * Also creates the first tenant admin login. Run after SuperAdminSeeder.
     */
    public function run(): void
    {
        $appUrl = config('app.url');
        $domain = parse_url($appUrl, PHP_URL_HOST) ?? 'localhost';

        $tenantDatabase = env(
            'TENANT_DATABASE',
            env('DB_DATABASE', 'tenant_' . str_replace('.', '_', $domain))
        );

        $tenant = Tenant::updateOrCreate(
            ['domain' => $domain],
            [
                'name' => env('TENANT_NAME', 'Default'),
                'database' => $tenantDatabase,
            ]
        );

        $adminEmail = env('TENANT_ADMIN_EMAIL', 'admin@' . $domain);
        $adminPassword = env('TENANT_ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            [
                'email' => $adminEmail,
                'tenant_id' => $tenant->id,
            ],
            [
                'name' => env('TENANT_ADMIN_NAME', 'Tenant Admin'),
                'password' => Hash::make($adminPassword),
                'role' => User::ROLE_TENANT_ADMIN,
                'bar_id' => null,
            ]
        );
    }
}
