<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use App\Models\Item;
use App\Models\Counter;
use App\Models\StockMovement;
use App\Models\DailySession;
use App\Constants\StockMovementType;

class StockCountImportPreview extends Page
{
    protected static string $view = 'filament.tenant.pages.stock-count-import-preview';

    public $rows = [];

    public function mount()
    {
        $this->rows = Session::get('physical-count-rows', []);

        if (empty($this->rows)) {
            return redirect()->route('filament.tenant.resources.physical-count.index');
        }
    }

    public function import()
    {
        $tenantId = Auth::user()->tenant_id;

        foreach ($this->rows as $row) {

            $productName  = trim($row['product'] ?? '');
            $sku          = trim($row['sku'] ?? '');     // NEW: lookup by SKU + name
            $closingCount = $row['quantity'] ?? null;
            $counterName  = trim($row['counter'] ?? '');

            if (! $productName || $closingCount === null) {
                continue;
            }

            // -----------------------------
            // 1. STRICT ITEM LOOKUP (name + SKU)
            // -----------------------------
            $item = Item::where('tenant_id', $tenantId)
                ->where('name', $productName)
                ->when($sku, fn($q) => $q->where('code', $sku))
                ->first();

            if (! $item) {
                // Item not found → skip
                continue;
            }

            // -----------------------------
            // 2. STRICT COUNTER LOOKUP
            // -----------------------------
            $counter = null;

            if ($counterName) {
                $counter = Counter::where('tenant_id', $tenantId)
                    ->where('name', $counterName)
                    ->first();

                if (! $counter) {
                    continue; // skip if counter doesn't exist
                }
            }

            // -----------------------------
            // 3. CREATE PHYSICAL COUNT MOVEMENT
            // -----------------------------
            StockMovement::create([
                'tenant_id'     => $tenantId,
                'counter_id'    => $counter?->id,
                'item_id'       => $item->id,
                'movement_type' => StockMovementType::CLOSING,
                'quantity'      => (int) $closingCount,
                'notes'         => $row['notes'] ?? null,
                'movement_date' => now(),
                'created_by'    => Auth::id(),
                'session_id'    => DailySession::where('tenant_id', $tenantId)
                                    ->where('is_open', true)
                                    ->first()?->id,
            ]);
        }

        Session::forget('physical-count-rows');

        session()->flash('success', 'Physical counts imported successfully!');

        return redirect()->route('filament.tenant.resources.controller.index');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
