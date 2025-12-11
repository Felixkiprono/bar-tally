<?php

namespace App\Support;

use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;

class InventoryImportHelper
{
    /**
     * Import a single row of data (stock or sale)
     */
    public static function importRow(array $row, string $movementType = 'restock', $counterId = null)
    {
        $tenantId = Auth::user()->tenant_id;

        $productName = trim($row['product'] ?? '');
        $quantity    = (int)($row['quantity'] ?? 0);
        $sku         = $row['sku'] ?? null;

        // Skip invalid rows
        if (!$productName || $quantity <= 0) {
            return;
        }

        // Create or update item
        $item = Item::firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'name'      => $productName,
                'code'      => $sku,
            ],
            [
                'brand'         => $row['brand'] ?? null,
                'category'      => self::normalizeCategory($row['category'] ?? null),
                'unit'          => $row['unit'] ?? 'PCS',
                'cost_price'    => $row['cost_price'] ?? 0,
                'selling_price' => $row['selling_price'] ?? 0,
                'reorder_level' => $row['reorder_level'] ?? 0,
                'created_by'    => Auth::id(),
                'is_active'     => 1,
            ]
        );

        // Create stock/sale movement
        StockMovement::create([
            'tenant_id'     => $tenantId,
            'item_id'       => $item->id,
            'counter_id'    => $counterId,
            'movement_type' => $movementType,
            'quantity'      => $quantity,
            'movement_date' => now(),
            'notes'         => $row['notes'] ?? null,
            'created_by'    => Auth::id(),
        ]);
    }

    /**
     * Normalize category names
     */
    public static function normalizeCategory($category)
    {
        if (!$category) return null;

        $category = strtoupper(trim($category));

        return Item::CATEGORIES[$category] ?? $category;
    }
}
