<?php

namespace App\Services\Sale;

use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Counter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use App\Support\SalesImportHandler;

class SalesImportService
{
    public function preparePreview(string $uploadedPath): array
    {
        $data = \Maatwebsite\Excel\Facades\Excel::toArray([], $uploadedPath)[0];

        $header = array_map(fn($h) => strtolower(trim($h)), $data[0]);
        unset($data[0]);

        $rows = [];

        foreach ($data as $line) {
            $row = array_combine($header, $line);

            $product = trim($row['product'] ?? '');
            $sku     = trim($row['sku'] ?? '');

            if (! $product) {
                continue;
            }

            foreach ($row as $key => $value) {
                if (in_array($key, ['product', 'sku', 'total_quantity'])) {
                    continue;
                }

                if ($value === null || $value === '' || (int) $value === 0) {
                    continue;
                }

                $rows[] = [
                    'product'  => $product,
                    'sku'      => $sku,
                    'counter'  => $key,
                    'quantity' => (int) $value,
                ];
            }
        }

        return [
            'rows' => $rows,
            'file' => $uploadedPath,
        ];
    }


    /**
     * STEP 2: Commit sales import (single authority)
     */
    public function commit(array $rows, int $tenantId, int $userId): void
    {
        $counters = Counter::where('tenant_id', $tenantId)
            ->get()
            ->keyBy(fn($c) => strtolower(trim($c->name)));

        DB::transaction(function () use ($rows, $tenantId, $userId, $counters) {

            foreach ($rows as $row) {

                // Normalize keys
                $row = collect($row)
                    ->mapWithKeys(fn($v, $k) => [strtolower(trim($k)) => $v])
                    ->toArray();

                $sku      = trim($row['sku'] ?? '');
                $product  = trim($row['product'] ?? '');
                $quantity = (int) ($row['quantity'] ?? 0);

                if (! $product || $quantity <= 0) {
                    continue;
                }

                // Item must exist (sales should NOT silently create products)
                $item = Item::where('tenant_id', $tenantId)
                    ->where('name', $product)
                    ->first();

                if (! $item) {
                    throw new \RuntimeException("Unknown product: {$product}");
                }

                // Optional counter
                $counterId = null;
                if (! empty($row['counter'])) {
                    $key = strtolower(trim($row['counter']));
                    $counterId = $counters[$key]->id ?? null;
                }

                StockMovement::create([
                    'tenant_id'     => $tenantId,
                    'item_id'       => $item->id,
                    'counter_id'    => $counterId,
                    'movement_type' => 'sale',
                    'quantity'      => $quantity,
                    'movement_date' => now(),
                    'notes'         => $row['notes'] ?? null,
                    'created_by'    => $userId,
                ]);
            }
        });
    }
}
