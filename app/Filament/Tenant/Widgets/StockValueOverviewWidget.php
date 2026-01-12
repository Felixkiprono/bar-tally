<?php

namespace App\Filament\Tenant\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\StockMovement;
use App\Constants\StockMovementType;
use App\Models\Item;

class StockValueOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenantId = auth()->user()->tenant_id;

        // Load movements with prices
        $movements = StockMovement::with('item')
            ->where('tenant_id', $tenantId)
            ->get();

        $restocks = $movements->where('movement_type', StockMovementType::RESTOCK);
        $sales    = $movements->where('movement_type', StockMovementType::SALE);

        /* =========================
         | VALUES (KES)
         * ========================= */

        $stockInValue = $restocks->sum(
            fn ($m) => $m->quantity * ($m->item->cost_price ?? 0)
        );

        $salesValue = $sales->sum(
            fn ($m) => $m->quantity * ($m->item->selling_price ?? 0)
        );

        $costOfSales = $sales->sum(
            fn ($m) => $m->quantity * ($m->item->cost_price ?? 0)
        );

        $currentStockValue = max($stockInValue - $costOfSales, 0);

        $profit = $salesValue - $costOfSales;

        $profitMargin = $salesValue > 0
            ? round(($profit / $salesValue) * 100, 1)
            : 0;

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

        $varianceToday = StockMovement::where('tenant_id', $tenantId)
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

                $itemId = $movements->first()->item_id;
                $cost = (float) Item::find($itemId)?->cost_price ?? 0;

                return $variance * $cost;
            });
        /* =========================
         | STATS
         * ========================= */

        return [

            Stat::make('Stock In Value', 'KES ' . number_format($stockInValue, 0))
                ->description('Inventory received 💼')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make('Total Sales Value', 'KES ' . number_format($salesValue, 0))
                ->description('Revenue generated 💰')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Variance Value Today', 'KES ' . number_format($varianceToday, 2))
                ->description('Stock variance cost')
                ->color($varianceToday >= 0 ? 'success' : 'danger'),

            Stat::make('Current Stock Value', 'KES ' . number_format($currentStockValue, 0))
                ->description('Unsold inventory 📦')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color($currentStockValue > 0 ? 'success' : 'danger'),

            Stat::make('Gross Profit', 'KES ' . number_format($profit, 0))
                ->description("Margin {$profitMargin}% 📈")
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($profit >= 0 ? 'success' : 'danger'),
        ];
    }
}
