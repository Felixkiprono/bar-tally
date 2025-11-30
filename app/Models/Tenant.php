<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;
use Spatie\Multitenancy\Models\Concerns\UsesTenantConnection;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends BaseTenant
{
    //
    use HasFactory, UsesTenantConnection;

    protected $fillable = [
        'name',
        'domain',
        'database',
    ];

    public static function booted()
    {
        static::creating(function (Tenant $tenant) {
            // Create a new database for the tenant when a new tenant is created
            // This is just a placeholder - you'll need to implement this according to your needs
            // \DB::statement("CREATE DATABASE {$tenant->database}");
        });

        static::deleted(function (Tenant $tenant) {
            // Drop the database when a tenant is deleted
            // This is just a placeholder - you'll need to implement this according to your needs
            // \DB::statement("DROP DATABASE {$tenant->database}");
        });
    }

    public function makeCurrent(): static
    {
        // Implement any custom logic needed when making a tenant current
        return parent::makeCurrent();
    }


    /**
     * Get the configurations for the tenant.
     */
    public function configurations(): HasMany
    {
        return $this->hasMany(Configuration::class);
    }

}
