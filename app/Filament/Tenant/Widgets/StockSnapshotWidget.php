<?php

namespace App\Filament\Tenant\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use App\Models\StockMovement;
use App\Models\Item;
use Illuminate\Support\Facades\DB;

class StockSnapshotWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $tenantId = auth()->user()->tenant_id;

        // =========================
        // PRODUCTS
        // =========================
        $totalProducts = Item::where('tenant_id', $tenantId)->count();

        // =========================
        // RESTOCK & SALES
        // =========================
        $restocked = StockMovement::where('tenant_id', $tenantId)
            ->where('movement_type', 'restock')
            ->sum('quantity');

        $sales = StockMovement::where('tenant_id', $tenantId)
            ->where('movement_type', 'sale')
            ->with('item')
            ->get();

        $soldQty = $sales->sum('quantity');

        $salesValue = $sales->sum(
            fn($m) =>
            $m->quantity * ($m->item->selling_price ?? 0)
        );

        $costValue = $sales->sum(
            fn($m) =>
            $m->quantity * ($m->item->cost_price ?? 0)
        );

        $profit = $salesValue - $costValue;

        $netStock = $restocked - $soldQty;

        // =========================
        // STOCK PER COUNTER (AFTER SALES)
        // =========================
        $counterStock = StockMovement::select(
            'counter_id',
            DB::raw("SUM(CASE WHEN movement_type = 'restock' THEN quantity ELSE -quantity END) as stock")
        )
            ->where('tenant_id', $tenantId)
            ->whereNotNull('counter_id')
            ->groupBy('counter_id')
            ->with('counter')
            ->get();

        $totalCounterStock = $counterStock->sum('stock');

        $topCounter = $counterStock
            ->sortByDesc('stock')
            ->first();

        return [

            /* ========================
         * EXISTING CARDS (unchanged)
         * ======================== */

            Stat::make('Total Products', $totalProducts)
                ->description('Active products 📦')
                ->descriptionIcon('heroicon-m-cube')
                ->color('primary'),

            Stat::make(
                'Stock In Today',
                StockMovement::where('tenant_id', $tenantId)
                    ->where('movement_type', 'restock')
                    ->whereDate('movement_date', today())
                    ->sum('quantity')
            )
                ->description('Items received today ➕')
                ->descriptionIcon('heroicon-m-arrow-down-tray')
                ->color('success'),

            Stat::make(
                'Total Movements',
                StockMovement::where('tenant_id', $tenantId)->count()
            )
                ->description('All stock activities 🔄')
                ->descriptionIcon('heroicon-m-arrows-right-left')
                ->color('info'),

            Stat::make(
                'Counters Active Today',
                StockMovement::where('tenant_id', $tenantId)
                    ->whereDate('movement_date', today())
                    ->distinct('counter_id')
                    ->count('counter_id')
            )
                ->description('Counters with stock updates 🏪')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('warning'),

            /* ========================
         * SALES & PROFIT
         * ======================== */

            Stat::make('Total Sales Qty', $soldQty)
                ->description('Units sold 🛒')
                ->descriptionIcon('heroicon-m-shopping-cart')
                ->color('warning'),

            Stat::make('Total Sales Value', 'KES ' . number_format($salesValue, 0))
                ->description('Revenue generated 💰')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),

            Stat::make('Gross Profit', 'KES ' . number_format($profit, 0))
                ->description('Sales minus cost 📈')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($profit >= 0 ? 'success' : 'danger'),

            /* ========================
         * STOCK HEALTH (NEW)
         * ======================== */

            Stat::make('Stock Remaining', $netStock)
                ->description('Current stock after sales 📦')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color($netStock > 0 ? 'success' : 'danger'),

            Stat::make('Total Counter Stock', $totalCounterStock)
                ->description('Stock across all counters 🏪')
                ->descriptionIcon('heroicon-m-building-storefront')
                ->color('info'),

            Stat::make(
                'Top Stocked Counter',
                optional($topCounter?->counter)->name ?? '—'
            )
                ->description(
                    $topCounter
                        ? "{$topCounter->stock} items available 📊"
                        : 'No stock data'
                )
                ->descriptionIcon('heroicon-m-trophy')
                ->color('success'),
        ];
    }
}
