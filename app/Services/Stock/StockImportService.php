<?php

namespace App\Services\Stock;

use Illuminate\Support\Facades\Storage;
use App\Support\SalesImportHandler;
use Illuminate\Support\Str;
use App\Models\Counter;
use App\Models\Item;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use App\Models\Stocks;
use App\Models\StockMovementType;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;



class StockImportService
{

    public function import(StockImportService $service)
    {

        $rows = Session::get('stock-import-rows', []);

        if (empty($rows)) {
            session()->flash('error', 'No data to import.');
            return redirect()->route('filament.tenant.resources.stocks.index');
        }

        $service->commit(
            $rows,
            Auth::user()->tenant_id,
            Auth::id()
        );

        Session::forget([
            'stock-import-rows',
            'stock-import-file',
        ]);

        session()->flash('success', 'Stock intake imported successfully!');
        return redirect()->route('filament.tenant.resources.stocks.index');
    }
    /**
     * Prepare stock import for preview
     */
    public function preparePreview(string $uploadedPath): array
    {
        // Move file to permanent location
        $extension = pathinfo($uploadedPath, PATHINFO_EXTENSION);

        $permanentFile = 'imports/permanent/' . Str::uuid() . '.' . $extension;

        Storage::disk('local')->copy($uploadedPath, $permanentFile);

        $absolutePath = Storage::disk('local')->path($permanentFile);

        if (!file_exists($absolutePath)) {
            throw new \RuntimeException("Import file not found: {$absolutePath}");
        }

        // Parse rows (CSV / XLSX safe)
        $rows = SalesImportHandler::loadRows($absolutePath);

        return [
            'rows' => $rows,
            'file' => $permanentFile,
        ];
    }

    /**
     * STEP 2: Commit import (single authority)
     */
    public function commit(
        array $rows,
        int $tenantId,
        int $userId,
        string $movementType = 'restock'
    ): void {

      $counters = Counter::where('tenant_id', $tenantId)
    ->get()
    ->keyBy(fn ($c) => strtolower(trim($c->name)));

DB::transaction(function () use ($rows, $tenantId, $userId, $movementType, $counters) {

    foreach ($rows as $row) {

        $row = collect($row)
            ->mapWithKeys(fn ($v, $k) => [strtolower(trim($k)) => $v])
            ->toArray();

        logger()->info('Inserting', ['row' => $row]);

        $item = Item::where('tenant_id', $tenantId)
           ->where('code', $row['sku'])
            ->where('tenant_id', $tenantId)
            ->first();

        if (isset($row['total_quantity'])) {

            $total = (int) $row['total_quantity'];
            $distributed = 0;

            foreach ($counters as $counterKey => $counter) {

                $qty = (int) ($row[$counterKey] ?? 0);

                logger()->info('Counter Name', [
                    'counterName' => $counterKey,
                    'qty' => $qty,
                ]);

                if ($qty <= 0) continue;

                $distributed += $qty;

                $this->createMovement(
                    $tenantId,
                    $userId,
                    $item->id,
                    $counter->id,
                    $movementType,
                    $qty,
                    ""
                );
            }

            if ($distributed !== $total) {
                throw new \RuntimeException(
                    "Quantity mismatch: total={$total}, distributed={$distributed}"
                );
            }
        }
    }
});

    }

    /**
     * Internal movement creator (single write path)
     */
    protected function createMovement(
        int $tenantId,
        int $userId,
        int $itemId,
        ?int $counterId,
        string $movementType,
        int $quantity,
        ?string $notes
    ): void {
        logger()->info('Inserting inside createMovement:', ['tenantId' => $tenantId, 'userId' => $userId]);


        StockMovement::create([
            'tenant_id'     => $tenantId,
            'item_id'       => $itemId,
            'counter_id'    => $counterId,
            'movement_type' => $movementType,
            'quantity'      => $quantity,
            'movement_date' => now(),
            'notes'         => $notes,
            'created_by'    => $userId,
        ]);
    }
}
