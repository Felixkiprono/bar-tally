<?php

namespace Tests\Unit\Database;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DatabaseSetupTest extends TestCase
{
    use WithFaker;

    #[Test]
    public function database_tables_exist()
    {
        // First create a tenant since users require a tenant_id
        $tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'database' => 'test_tenant_db',
        ]);

        // Create a user first (needed for created_by)
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        // Test that we can create a user with the tenant
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);

        // Test that we can create an account with the tenant and created_by
        $account = Account::factory()->create([
            'tenant_id' => $tenant->id,
            'created_by' => $admin->id,
        ]);
        $this->assertDatabaseHas('accounts', ['id' => $account->id]);

        $this->assertTrue(true);
    }
}
