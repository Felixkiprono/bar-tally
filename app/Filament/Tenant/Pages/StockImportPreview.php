<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
            return redirect()->route('filament.tenant.resources.stock-movements.index');
        }
    }

    public function import()
    {
        foreach ($this->rows as $row) {
            \App\Support\InventoryImportHelper::importRow($row, 'restock');
        }

        Session::forget('stock-import-rows');

        session()->flash('success', 'Stock intake imported successfully!');
        return redirect()->route('filament.tenant.resources.stocks.index');
    }


    // public function import()
    // {
    //     $tenantId = Auth::user()->tenant_id;

    //     foreach ($this->rows as $row) {

    //         $productName = trim($row['product'] ?? '');
    //         $quantity    = $row['quantity'] ?? null;
    //         $sku         = $row['sku'] ?? null;

    //         if (!$productName || !$quantity) {
    //             continue;
    //         }

    //         // Create or find item
    //         $item = Item::firstOrCreate(
    //             [
    //                 'name' => $productName,
    //                 'code' => $sku,
    //             ],
    //             [
    //                 'tenant_id'     => $tenantId,
    //                 'brand'         => $row['brand'] ?? null,
    //                 'category'      => $this->normalizeCategory($row['category'] ?? null),
    //                 'unit'          => $row['unit'] ?? 'PCS',
    //                 'cost_price'    => $row['cost_price'] ?? 0,
    //                 'selling_price' => $row['selling_price'] ?? 0,
    //                 'reorder_level' => $row['reorder_level'] ?? 0,
    //                 'created_by'    => Auth::id(),
    //                 'is_active'     => 1,
    //             ]
    //         );

    //         // Create restock movement
    //         StockMovement::create([
    //             'tenant_id'     => $tenantId,
    //             'item_id'       => $item->id,
    //             'movement_type' => 'restock',
    //             'quantity'      => (int)$quantity,
    //             'notes'         => $row['notes'] ?? 'Warehouse restock',
    //             'movement_date' => now(),
    //             'created_by'    => Auth::id(),
    //         ]);
    //     }

    //     Session::forget('stock-import-rows');

    //     session()->flash('success', 'Stock intake imported successfully!');
    //     return redirect()->route('filament.tenant.resources.stocks.index');
    // }

    public function downloadTemplate(): BinaryFileResponse
    {
        $file = storage_path('app/stock-intake-template.xlsx');
        return response()->download($file, 'stock-intake-template.xlsx');
    }

    protected function normalizeCategory($category)
    {
        if (!$category) return null;

        $category = strtoupper(trim($category));
        return Item::CATEGORIES[$category] ?? $category;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
