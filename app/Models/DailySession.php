<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySession extends Model
{
    //
     protected $fillable = [
        'tenant_id',
        'date',
        'opened_by',
        'opening_time',
        'closed_by',
        'closing_time',
        'is_open',
    ];

    /* Relationships */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class, 'session_id');
    }
}
