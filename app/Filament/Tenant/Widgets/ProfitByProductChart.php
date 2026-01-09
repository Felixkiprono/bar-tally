<?php

namespace App\Filament\Tenant\Widgets;

use Filament\Widgets\ChartWidget;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use App\Models\StockMovement;

class ProfitByProductChart extends ChartWidget
{
    protected static ?string $heading = 'Profit by Product';
    protected static ?int $sort = 3;

    public ?array $filters = [];

    protected function getFilters(): array
    {
        return [
            'today' => 'Today',
            '7days' => 'Last 7 Days',
            'month' => 'This Month',
            'year'  => 'This Year',
        ];
    }


    protected function getFormSchema(): array
    {
        return [
            DatePicker::make('from')
                ->label('From')
                ->default(now()->startOfMonth()),

            DatePicker::make('to')
                ->label('To')
                ->default(now()->endOfMonth()),

            Select::make('limit')
                ->label('Top Products')
                ->options([
                    5 => 'Top 5',
                    10 => 'Top 10',
                    20 => 'Top 20',
                ])
                ->default(5),
        ];
    }
    protected function getFormColumns(): int
    {
        return 2;
    }

    protected function hasFiltersForm(): bool
    {
        return true;
    }

    protected function getData(): array
    {
        [$from, $to] = match ($this->filter) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            '7days' => [now()->subDays(7), now()],
            'year'  => [now()->startOfYear(), now()],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };

        return $this->query($from, $to);
    }

    protected function query($from, $to): array
    {
        $tenantId = auth()->user()->tenant_id;
        $rows = StockMovement::query()
            ->where('stock_movements.tenant_id', $tenantId)
            ->where('movement_type', 'sale')
            ->whereBetween('movement_date', [$from, $to])
            ->join('items', 'items.id', '=', 'stock_movements.item_id')
            ->selectRaw('
                items.name as product,
                SUM((items.selling_price - items.cost_price) * quantity) as profit
            ')
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('profit')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Profit (KES)',
                'data' => $rows->pluck('profit'),
            ]],
            'labels' => $rows->pluck('product'),
        ];
    }


    protected function getType(): string
    {
        return 'bar';
    }
}
