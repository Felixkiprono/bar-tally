<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use function Spatie\Multitenancy\Actions\initializeTenancy;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure we're using SQLite in-memory database for tests
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        
        // Disable multitenancy for tests to avoid complications
        config(['multitenancy.switch_tenant_tasks' => []]);
        config(['multitenancy.queues_are_tenant_aware_by_default' => false]);
        
        // Manually run migrations for SQLite
        $this->artisan('migrate:fresh', [
            '--database' => 'sqlite',
            '--path' => 'database/migrations',
            '--realpath' => true,
        ]);
    }
    
    protected function setupTenancy()
    {
        $this->createApplication(); // Bootstrap the application

        $tenant = \App\Models\Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com',
            'database' => 'test_tenant_db',
        ]);

        tenancy()->initialize($tenant);

        return $tenant;
    }
    
    protected function tearDown(): void
    {
        // Clean up after each test
        parent::tearDown();
    }
}
