<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    //
    protected $fillable = [
        'tenant_id',
        'brand',
        'code',
        'name',
        'unit',
        'cost_price',
        'selling_price',
        'reorder_level',
        'category',
        'is_active',
        'created_by',
        'updated_by',
    ];
     public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
