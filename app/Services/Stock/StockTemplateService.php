<?php

namespace App\Services\Stock;

use App\Models\Item;
use App\Models\Counter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class StockTemplateService
{
    /**
     * Generate stock distribution CSV template
     */
    public function downloadTemplate(int $tenantId): BinaryFileResponse
    {
        $items = Item::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();

        $counters = Counter::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        if ($counters === []) {
            abort(422, 'No counters configured for this tenant.');
        }

        // CSV Header
        $headers = array_merge(
            ['product', 'sku', 'total_quantity'],
            $counters,

        );

        $csv = implode(',', $headers) . "\n";

        // Sample rows
        foreach ($items as $item) {
            $row = [
                $item->name,
                $item->code,
                0,
            ];

            foreach ($counters as $counter) {
                $row[] = 0;
            }

            $csv .= implode(',', $row) . "\n";
        }

        // Save file
        $fileName = 'stock_distribution_template_' . now()->format('Ymd_His') . '.csv';
        $path = storage_path('app/' . $fileName);

        file_put_contents($path, $csv);

        return response()
            ->download($path)
            ->deleteFileAfterSend(true);
    }
}
