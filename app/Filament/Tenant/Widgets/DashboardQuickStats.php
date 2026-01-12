<?php

namespace App\Filament\Tenant\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\Item;
use App\Models\StockMovement;

class DashboardQuickStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenantId = auth()->user()->tenant_id;
        $salesToday = StockMovement::where('tenant_id', $tenantId)
            ->where('movement_type', 'sale')
            ->whereDate('movement_date', today())
            ->sum('quantity');

        $stockInToday = StockMovement::where('tenant_id', $tenantId)
            ->where('movement_type', 'restock')
            ->whereDate('movement_date', today())
            ->sum('quantity');

        $varianceValueToday = StockMovement::where('tenant_id', $tenantId)
            ->whereDate('movement_date', today())
            ->get()
            ->groupBy('item_id')
            ->sum(function ($movements) {
                $opening = $movements->where('movement_type', 'opening_stock')->sum('quantity');
                $restock = $movements->where('movement_type', 'restock')->sum('quantity');
                $sold = $movements->where('movement_type', 'sale')->sum('quantity');
                $closing = $movements->where('movement_type', 'closing_stock')->sum('quantity');

                $expected = $opening + $restock - $sold;
                $variance = $expected - $closing;

                $cost = (float) optional($movements->first())->cost_price;

                return $variance * $cost;
            });

        return [
            Stat::make('Sales Today', $salesToday)
                ->description('Units sold 🛒')
                ->color('success'),

            Stat::make('Stock In Today', $stockInToday)
                ->description('Items received ➕')
                ->color('info'),

            Stat::make('Variance Value Today', 'KES ' . number_format($varianceValueToday, 2))
                ->description('Stock variance cost')
                ->color($varianceValueToday >= 0 ? 'success' : 'danger'),

        ];
    }
}
