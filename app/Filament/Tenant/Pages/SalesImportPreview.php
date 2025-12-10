<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Item;
use App\Models\Counter;
use App\Models\StockMovement;

class SalesImportPreview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.sales-import-preview';
    public $rows = [];

    public function mount()
    {
        $this->rows = Session::get('sales-import-rows', []);

        if (empty($this->rows)) {
            return redirect()->route('filament.tenant.resources.daily-sale.index');
        }
    }
    public function import()
    {
        $tenantId = Auth::user()->tenant_id;

        foreach ($this->rows as $row) {

            $productName = trim($row['product'] ?? '');
            $quantity    = $row['quantity'] ?? null;
            $sku    = $row['sku'] ?? null;

            // Skip rows missing required fields
            if (!$productName || !$quantity) {
                continue;
            }

            /**
             * FIND OR CREATE ITEM
             */
            $item = Item::firstOrCreate(
                [
                    'name'      => $productName,
                    'code' => $sku,
                ],
                [
                    'tenant_id'     => $tenantId,
                    'brand'         => $row['brand'] ?? null,
                    'category'      => $this->normalizeCategory($row['category'] ?? null),
                    'unit'          => $row['unit'] ?? 'PCS',
                    'cost_price'    => $row['cost_price'] ?? 0,
                    'selling_price' => $row['selling_price'] ?? 0,
                    'reorder_level' => $row['reorder_level'] ?? 0,
                    'created_by'    => Auth::id(),
                    'is_active'     => 1,
                ]
            );


            /**
             * COUNTER (OPTIONAL)
             */
            $counterName = $row['counter'] ?? null;

            if ($counterName) {
                $counter = Counter::where('name', $counterName)
                    ->where('tenant_id', $tenantId)
                    ->first();
            } else {
                $counter = null;
            }

            /**
             * MOVEMENT TYPE LOGIC
             */
            $movementType = $counter ? 'sale' : 'restock';

            /**
             * CREATE STOCK MOVEMENT
             */
            StockMovement::create([
                'tenant_id'     => $tenantId,
                'counter_id'    => $counter?->id,
                'item_id'       => $item->id,
                'movement_type' => $movementType,
                'quantity'      => (int)$quantity,
                'notes'         => $row['notes'] ?? null,
                'movement_date' => now(),
                'created_by'    => Auth::id(),
            ]);
        }

        session()->forget('sales-import-rows');

        session()->flash('success', 'Sales imported successfully!');
        return redirect()->route('filament.tenant.resources.daily-sales.index');
    }

    protected function normalizeCategory($category)
    {
        if (!$category) {
            return null;
        }

        $category = strtoupper(trim($category));

        // Validate against allowed categories
        return Item::CATEGORIES[$category] ?? $category;
    }


    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
