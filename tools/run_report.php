<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$sql = "
    SELECT
        sm.item_id,
        i.name AS item_name,
        i.selling_price,
        i.cost_price,

        SUM(CASE WHEN sm.movement_type = 'opening_stock' THEN sm.quantity ELSE 0 END) AS opening,
        SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity ELSE 0 END) AS sold,
        SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity ELSE 0 END) AS restock,
        SUM(CASE WHEN sm.movement_type = 'closing_stock' THEN sm.quantity ELSE 0 END) AS closing,

        (
            SUM(CASE WHEN sm.movement_type = 'opening_stock' THEN sm.quantity ELSE 0 END)
            + SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity ELSE 0 END)
            - SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity ELSE 0 END)
        ) AS expected_closing,

        (
            (
                SUM(CASE WHEN sm.movement_type = 'opening_stock' THEN sm.quantity ELSE 0 END)
                + SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity ELSE 0 END)
                - SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity ELSE 0 END)
            )
            - SUM(CASE WHEN sm.movement_type = 'closing_stock' THEN sm.quantity ELSE 0 END)
        ) AS variance
    FROM stock_movements sm
    JOIN items i ON i.id = sm.item_id
    WHERE sm.movement_date = '2025-12-03'
    GROUP BY sm.item_id, i.name, i.selling_price, i.cost_price
";

$rows = DB::select(DB::raw($sql));

echo "Rows: " . count($rows) . PHP_EOL;
if (count($rows) > 0) {
    print_r($rows[0]);
} else {
    echo "No rows returned." . PHP_EOL;
}
