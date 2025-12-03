<?php

namespace App\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StockReportTable extends Component
{
    public $date;
    public $item;

    public function mount()
    {
        $this->date = today()->toDateString();
        $this->item = null;
    }

//     public function getRowsProperty()
//     {
//         $conditions = [];

//         $conditions[] = "sm.movement_date = '{$this->date}'";

//         if ($this->item) {
//             $conditions[] = "sm.item_id = {$this->item}";
//         }

//         $where = implode(" AND ", $conditions);

//         $sql = "
//        SELECT
//     CONCAT(sm.item_id, '_', sm.movement_date) AS id,
//     sm.item_id,
//     i.name AS item_name,
//     sm.movement_date,
//     i.selling_price,
//     i.cost_price,

//     SUM(CASE WHEN sm.movement_type = 'opening_stock' THEN sm.quantity ELSE 0 END) AS opening,
//     SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity ELSE 0 END) AS sold,
//     SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity ELSE 0 END) AS restock,
//     SUM(CASE WHEN sm.movement_type = 'closing_stock' THEN sm.quantity ELSE 0 END) AS closing,

//     (
//         SUM(CASE WHEN sm.movement_type = 'opening_stock' THEN sm.quantity ELSE 0 END)
//         + SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity ELSE 0 END)
//         - SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity ELSE 0 END)
//     ) AS expected_closing,

//     CASE
//         WHEN
//             SUM(CASE WHEN sm.movement_type = 'opening_stock' THEN sm.quantity ELSE 0 END) = 0
//             AND SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity ELSE 0 END) = 0
//             AND SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity ELSE 0 END) = 0
//         THEN 0
//         ELSE (
//             (
//                 SUM(CASE WHEN sm.movement_type = 'opening_stock' THEN sm.quantity ELSE 0 END)
//                 + SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity ELSE 0 END)
//                 - SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity ELSE 0 END)
//             )
//             - SUM(CASE WHEN sm.movement_type = 'closing_stock' THEN sm.quantity ELSE 0 END)
//         )
//     END AS variance

// FROM stock_movements sm
// JOIN items i ON i.id = sm.item_id
// WHERE $where
// GROUP BY sm.item_id, sm.movement_date, i.name, i.selling_price, i.cost_price

//         ";

//         return DB::select($sql);
//     }

public function getRowsProperty()
{
    $query = DB::table('stock_movements as sm')
        ->join('items as i', 'i.id', '=', 'sm.item_id')
        ->select(
            'sm.*',
            'i.name as item_name',
            'i.selling_price',
            'i.cost_price'
        )
        ->where('sm.movement_date', $this->date);

    if ($this->item) {
        $query->where('sm.item_id', $this->item);
    }

    return $query->orderBy('sm.item_id')->get();
}



public function exportCsv()
{
    $filename = 'stock_report_' . now()->format('Y-m-d_H-i') . '.csv';

    $header = [
        'Content-Type'        => 'text/csv',
        'Content-Disposition' => "attachment; filename=\"$filename\"",
    ];

    $rows = $this->rows->groupBy('item_id');

    $callback = function () use ($rows) {
        $file = fopen('php://output', 'w');

        // CSV HEADERS
        fputcsv($file, [
            'Item', 'Opening', 'Restock', 'Sold', 'Closing',
            'Expected', 'Variance', 'Cost', 'Selling', 'Profit'
        ]);

        // ACCUMULATORS FOR TOTALS
        $totalOpening = 0;
        $totalRestock = 0;
        $totalSold = 0;
        $totalClosing = 0;
        $totalExpected = 0;
        $totalVariance = 0;
        $totalProfit = 0;

        foreach ($rows as $itemId => $movements) {

            $item = $movements->first();

            // SAME CALCULATIONS AS UI
            $opening = $movements->where('movement_type', 'opening_stock')->sum('quantity');
            $restock = $movements->where('movement_type', 'restock')->sum('quantity');
            $sold    = $movements->where('movement_type', 'sale')->sum('quantity');
            $closing = $movements->where('movement_type', 'closing_stock')->sum('quantity');

            $expected = $opening + $restock - $sold;
            $variance = $expected - $closing;

            $cost    = floatval($item->cost_price ?? 0);
            $selling = floatval($item->selling_price ?? 0);
            $profit  = $sold * ($selling - $cost);

            // PUSH SUMS FOR TOTALS
            $totalOpening += $opening;
            $totalRestock += $restock;
            $totalSold    += $sold;
            $totalClosing += $closing;
            $totalExpected += $expected;
            $totalVariance += $variance;
            $totalProfit   += $profit;

            // WRITE ROW
            fputcsv($file, [
                $item->item_name,
                $opening,
                $restock,
                $sold,
                $closing,
                $expected,
                $variance,
                $cost,
                $selling,
                $profit,
            ]);
        }

        // ADD TOTALS ROW
        fputcsv($file, [
            'TOTALS',
            $totalOpening,
            $totalRestock,
            $totalSold,
            $totalClosing,
            $totalExpected,
            $totalVariance,
            '',
            '',
            $totalProfit,
        ]);

        fclose($file);
    };

    return response()->stream($callback, 200, $header);
}



    public function render()
    {
        return view('livewire.stock-report-table', [
            'rows' => $this->rows,
            'items' => \App\Models\Item::orderBy('name')->get(),
        ]);
    }
}
