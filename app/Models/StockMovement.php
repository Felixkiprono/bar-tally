<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    //
     protected $fillable = [
        'tenant_id',
        'session_id',
        'counter_id',
        'item_id',
        'movement_type',
        'quantity',
        'movement_date',
        'notes',
        'created_by',
    ];

    /* Relationships */

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function session()
    {
        return $this->belongsTo(DailySession::class, 'session_id');
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }


    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
