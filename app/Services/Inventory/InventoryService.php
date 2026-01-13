<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\Counter;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Export items below reorder level (counter-aware, import-safe)
     */
    public function exportBelowReorderCsv()
    {
        $counters = Counter::orderBy('name')
            ->pluck('name', 'id')
            ->toArray();

        /**
         * Stock per item per counter (DERIVED)
         */
        $stock = DB::table('stock_movements')
            ->select(
                'item_id',
                'counter_id',
                DB::raw("
                    SUM(
                        CASE
                            WHEN movement_type IN ('opening_stock', 'restock') THEN quantity
                            WHEN movement_type = 'sale' THEN -quantity
                            ELSE 0
                        END
                    ) AS stock
                ")
            )
            ->groupBy('item_id', 'counter_id')
            ->get()
            ->groupBy('item_id');

        /**
         * Filter items BELOW reorder level
         */
        $items = Item::query()
            ->get()
            ->filter(function ($item) use ($stock) {
                $total = ($stock[$item->id] ?? collect())->sum('stock');
                return $total < $item->reorder_level;
            });

        $filename = 'below_reorder_level_' . now()->format('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () use ($items, $counters, $stock) {

            $handle = fopen('php://output', 'w');

            /**
             * CSV HEADER
             */
            fputcsv($handle, array_merge(
                ['product', 'sku', 'reorder_level', 'current_total'],
                collect($counters)->map(fn ($n) => "current_$n")->values()->toArray(),
                collect($counters)->map(fn ($n) => "ADD_$n")->values()->toArray()
            ));

            /**
             * CSV ROWS
             */
            foreach ($items as $item) {

                $itemStock = $stock[$item->id] ?? collect();

                $currentPerCounter = [];
                $currentTotal = 0;

                foreach ($counters as $counterId => $counterName) {
                    $qty = (int) optional(
                        $itemStock->firstWhere('counter_id', $counterId)
                    )->stock ?? 0;

                    $currentPerCounter[$counterName] = $qty;
                    $currentTotal += $qty;
                }

                $row = [
                    $item->name,
                    $item->id,                 // SKU / identifier
                    $item->reorder_level,
                    $currentTotal,
                ];

                // current_* (read-only)
                foreach ($currentPerCounter as $qty) {
                    $row[] = $qty;
                }

                // ADD_* (user-editable)
                foreach ($currentPerCounter as $_) {
                    $row[] = 0;
                }

                fputcsv($handle, $row);
            }

            fclose($handle);

        }, $filename);
    }

    /**
     * Import restock CSV
     * Only ADD_* columns are applied
     */
    public function importRestockCsv(array $rows): void
    {
        $counters = Counter::pluck('id', 'name')->toArray();

        foreach ($rows as $row) {

            $itemId = $row['sku'] ?? null;

            if (!$itemId) {
                continue;
            }

            foreach ($row as $column => $value) {

                if (!str_starts_with($column, 'ADD_')) {
                    continue;
                }

                $qty = (int) $value;
                if ($qty <= 0) {
                    continue;
                }

                $counterName = str_replace('ADD_', '', $column);

                if (!isset($counters[$counterName])) {
                    continue;
                }

                StockMovement::create([
                    'item_id'       => $itemId,
                    'counter_id'    => $counters[$counterName],
                    'movement_type' => 'restock',
                    'quantity'      => $qty,
                    'movement_date' => today(),
                ]);
            }
        }
    }
}
