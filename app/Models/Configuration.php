<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Configuration extends Model
{
    protected $fillable = [
        'key',
        'context',
        'value',
        'scope',
        'tenant_id',
        'is_active',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeTenant($query)
    {
        return $query->where('tenant_id', Auth::user()->tenant_id);
    }

    /**
     * Get a configuration value by key for the current tenant
     */
    public static function getValue(string $key, mixed $default = null, ?int $tenantId = null): mixed
    {
        $tenantId = $tenantId ?? Auth::user()?->tenant_id;

        if (!$tenantId) {
            return $default;
        }

        $config = static::where('tenant_id', $tenantId)
            ->where('key', $key)
            ->where('is_active', true)
            ->first();

        return $config?->value ?? $default;
    }

    /**
     * Get the SMS footer for the current tenant
     */
    public static function getSmsFooter(?int $tenantId = null): ?string
    {
        return static::getValue('sms_footer', null, $tenantId);
    }

    /**
     * Get the default SMS provider for the current tenant
     */
    public static function getDefaultSmsProvider(?int $tenantId = null): string
    {
        return static::getValue('default_sms_provider', 'leopard', $tenantId);
    }
}
