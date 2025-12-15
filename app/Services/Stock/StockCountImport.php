<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\Counter;
use App\Models\StockMovement;
use App\Models\DailySession;
use App\Constants\StockMovementType;
use Illuminate\Support\Facades\DB;

class StockCountImportService
{
    public function commit(
        array $rows,
        int $tenantId,
        int $userId
    ): void {
        $openSessionId = DailySession::where('tenant_id', $tenantId)
            ->where('is_open', true)
            ->value('id');

        DB::transaction(function () use ($rows, $tenantId, $userId, $openSessionId) {

            foreach ($rows as $row) {

                $row = collect($row)
                    ->mapWithKeys(fn ($v, $k) => [strtolower(trim($k)) => $v])
                    ->toArray();

                $product = trim($row['product'] ?? '');
                $sku     = trim($row['sku'] ?? '');
                $counter = trim($row['counter'] ?? '');
                $qty     = isset($row['quantity']) ? (int) $row['quantity'] : null;

                if (! $product || $qty === null) {
                    continue;
                }

                /**
                 * -------------------------
                 * ITEM (STRICT)
                 * -------------------------
                 */
                $item = Item::where('tenant_id', $tenantId)
                    ->where('name', $product)
                    ->when($sku, fn ($q) => $q->where('code', $sku))
                    ->first();

                if (! $item) {
                    continue;
                }

                /**
                 * -------------------------
                 * COUNTER (STRICT)
                 * -------------------------
                 */
                $counterModel = null;

                if ($counter) {
                    $counterModel = Counter::where('tenant_id', $tenantId)
                        ->where('name', $counter)
                        ->first();

                    if (! $counterModel) {
                        continue;
                    }
                }

                /**
                 * -------------------------
                 * PHYSICAL COUNT ENTRY
                 * -------------------------
                 */
                StockMovement::create([
                    'tenant_id'     => $tenantId,
                    'session_id'    => $openSessionId,
                    'counter_id'    => $counterModel?->id,
                    'item_id'       => $item->id,
                    'movement_type' => StockMovementType::CLOSING,
                    'quantity'      => $qty,
                    'notes'         => $row['notes'] ?? null,
                    'movement_date' => now(),
                    'created_by'    => $userId,
                ]);
            }
        });
    }
}
