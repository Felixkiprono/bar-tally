<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'tenant_id',
        'bar_id'
        ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }


    // Define roles as constants
    const ROLE_SUPER_ADMIN = 'super_admin';
    const ROLE_TENANT_ADMIN = 'tenant_admin';
    const ROLE_CUSTOMER = 'customer';
    const ROLE_ADMIN = 'admin';
    const ROLE_STAFF = 'staff';
    const ROLE_SYSTEM_ADMIN = 'system_admin';
    const ROLE_METER_READER = 'meter_reader';


    public static function getRoles()
    {
        return [
            self::ROLE_SYSTEM_ADMIN => 'System Admin',
            self::ROLE_TENANT_ADMIN => 'Tenant Admin',
            self::ROLE_STAFF => 'Staff',
            self::ROLE_METER_READER => 'Meter Reader',
        ];
    }

    // Relationship to tenant
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    // Check if user is a super admin
    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    // Check if user is a tenant admin
    public function isTenantAdmin(): bool
    {
        return $this->role === self::ROLE_TENANT_ADMIN;
    }



    // Check if user is an admin
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    // Check if user is a staff
    public function isStaff(): bool
    {
        return $this->role === self::ROLE_STAFF;
    }

    // Implementation of FilamentUser interface
    public function canAccessPanel(Panel $panel): bool
    {
        // Super admins can access any panel
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Tenant admins can access their tenant's panel
        if ($this->isTenantAdmin() && $panel->getId() === 'tenant') {
            return true;
        }

        if ($this->isMeterReader() && $panel->getId() === 'tenant') {
            return true;
        }

        return false;
    }

    // Add query scopes
    public function scopeCustomers($query)
    {
        return $query->where('role', self::ROLE_CUSTOMER);
    }

    public function scopeStaff($query)
    {
        return $query->where('role', self::ROLE_STAFF);
    }

    public function scopeAdmins($query)
    {
        return $query->where('role', self::ROLE_ADMIN);
    }

    public function scopeTenantAdmins($query)
    {
        return $query->where('role', self::ROLE_TENANT_ADMIN);
    }

    public function scopeNonCustomers($query)
    {
        return $query->where('role', '!=', self::ROLE_CUSTOMER);
    }


    public function openDailySessions()
    {
        return $this->hasMany(DailySession::class, 'opened_by');
    }
    public function closeDailySessions()
    {
        return $this->hasMany(DailySession::class, 'closed_by');
    }



}
