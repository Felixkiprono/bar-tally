<?php

namespace App\Services\Sale;

use App\Models\Item;
use App\Models\Counter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SalesTemplateService
{
    /**
     * Generate sales CSV template (Stock-style)
     */
    public function downloadTemplate(int $tenantId): BinaryFileResponse
    {
        $items = Item::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get(['name', 'code']);

        $counters = Counter::where('tenant_id', $tenantId)
            ->orderBy('name')
            ->pluck('name')
            ->toArray();

        if (empty($counters)) {
            abort(422, 'No counters configured for this tenant.');
        }

        /**
         * CSV HEADER
         * product | sku | total_quantity | Counter A | Counter B | ...
         */
        $headers = array_merge(
            ['product', 'sku'],
            $counters
        );

        $csv = implode(',', $headers) . "\n";

        /**
         * ROWS (must match header count exactly)
         */
        foreach ($items as $item) {
            $row = [
                $item->name,
                $item->code,
            ];

            // one column per counter
            foreach ($counters as $_) {
                $row[] = 0;
            }

            $csv .= implode(',', $row) . "\n";
        }

        $fileName = 'sales_template_' . now()->format('Ymd_His') . '.csv';
        $path = storage_path('app/' . $fileName);

        file_put_contents($path, $csv);

        return response()
            ->download($path)
            ->deleteFileAfterSend(true);
    }


}
