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
    /**
     * STEP 1: Prepare preview (CSV / XLSX)
     */
    public function preparePreview(string $uploadedPath): array
    {
        $extension = pathinfo($uploadedPath, PATHINFO_EXTENSION);

        $permanentFile = 'imports/permanent/' . Str::uuid() . '.' . $extension;
        Storage::disk('local')->copy($uploadedPath, $permanentFile);

        $absolutePath = Storage::disk('local')->path($permanentFile);

        if (! file_exists($absolutePath)) {
            throw new \RuntimeException("Import file not found.");
        }

        $rows = SalesImportHandler::loadRows($absolutePath);

        return [
            'rows' => $rows,
            'file' => $permanentFile,
        ];
    }

    /**
     * STEP 2: Commit sales import (single authority)
     */
    public function commit(array $rows, int $tenantId, int $userId): void
    {
        $counters = Counter::where('tenant_id', $tenantId)
            ->get()
            ->keyBy(fn ($c) => strtolower(trim($c->name)));

        DB::transaction(function () use ($rows, $tenantId, $userId, $counters) {

            foreach ($rows as $row) {

                // Normalize keys
                $row = collect($row)
                    ->mapWithKeys(fn ($v, $k) => [strtolower(trim($k)) => $v])
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
