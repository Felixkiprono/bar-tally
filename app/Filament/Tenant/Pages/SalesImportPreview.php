<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Item;
use App\Models\StockMovement;

class StockImportPreview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.stock-import-preview';

    public $rows = [];

    public function mount()
    {
        $this->rows = Session::get('stock-import-rows', []);

        if (empty($this->rows)) {
            return redirect()->route('filament.tenant.resources.stocks.index');
        }
    }

    public function import()
    {
        $tenantId = Auth::user()->tenant_id;

        foreach ($this->rows as $row) {

            $productName = trim($row['product'] ?? '');
            $quantity    = $row['quantity'] ?? null;
            $sku         = $row['sku'] ?? null;

            // Skip missing data rows
            if (!$productName || !$quantity) {
                continue;
            }

            /**
             * FIND OR CREATE ITEM
             */
            $item = Item::firstOrCreate(
                [
                    'name' => $productName,
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
             * CREATE RESTOCK MOVEMENT
             */
            StockMovement::create([
                'tenant_id'     => $tenantId,
                'counter_id'    => null, // Stock intake never has a counter
                'item_id'       => $item->id,
                'movement_type' => 'restock',
                'quantity'      => (int) $quantity,
                'notes'         => $row['notes'] ?? 'Warehouse restock',
                'movement_date' => now(),
                'created_by'    => Auth::id(),
            ]);
        }

        Session::forget('stock-import-rows');

        session()->flash('success', 'Stock intake imported successfully!');
        return redirect()->route('filament.tenant.resources.stocks.index');
    }

    protected function normalizeCategory($category)
    {
        if (!$category) {
            return null;
        }

        $category = strtoupper(trim($category));
        return Item::CATEGORIES[$category] ?? $category;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
