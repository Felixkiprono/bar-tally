<?php

namespace App\Filament\Tenant\Resources\CounterInventoryResource\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryChart extends ChartWidget
{

    protected static ?string $heading = 'Stock vs Sold Overview';

    protected function getData(): array
    {
        $tenantId = Auth::user()->tenant_id;

        // Aggregate stock per item
        $rows = DB::table('items')
            ->leftJoin('stock_movements as sm', function ($join) use ($tenantId) {
                $join->on('items.id', '=', 'sm.item_id')
                    ->where('sm.tenant_id', '=', $tenantId);
            })
            ->where('items.tenant_id', $tenantId)
            ->groupBy('items.id', 'items.name')
            ->selectRaw("
                items.name as item_name,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'restock' THEN sm.quantity END), 0) as stock_in,
                COALESCE(SUM(CASE WHEN sm.movement_type = 'sale' THEN sm.quantity END), 0) as stock_out
            ")
            ->get();

        $labels = $rows->pluck('item_name')->toArray();
        $stockIn = $rows->pluck('stock_in')->toArray();
        $stockOut = $rows->pluck('stock_out')->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Stock In',
                    'data' => $stockIn,
                    'backgroundColor' => '#3b82f6',
                ],
                [
                    'label' => 'Sold',
                    'data' => $stockOut,
                    'backgroundColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }
    protected function getColumns(): int
    {
        return 2; // or 3 depending on your layout
    }
 protected function getOptions(): array
{
    return [
        'responsive' => true,
        'maintainAspectRatio' => false,
        'plugins' => [
            'legend' => ['display' => true],
        ],
        'scales' => [
            'x' => [
                'ticks' => [
                    'maxRotation' => 45,
                    'minRotation' => 45,
                ],
            ],
        ],
    ];
}
protected function getChartWrapperAttributes(): array
{
    return [
        'style' => 'overflow-x: auto; width: 100%;',
    ];
}


    protected function getType(): string
    {
        return 'bar';
    }
}
