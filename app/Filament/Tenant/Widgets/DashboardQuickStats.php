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

        return [
            Stat::make('Sales Today', $salesToday)
                ->description('Units sold 🛒')
                ->color('success'),

            Stat::make('Stock In Today', $stockInToday)
                ->description('Items received ➕')
                ->color('info'),



        ];
    }
}
