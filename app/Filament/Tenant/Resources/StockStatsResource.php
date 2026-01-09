<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\StockStatsResource\Pages;
use App\Models\StockMovement;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Tenant\Widgets\StockOverviewWidget;
use App\Filament\Tenant\Widgets\ProfitByProductChart;


class StockStatsResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Reports & Analytics';
    protected static ?string $navigationLabel = 'Analytics';
    protected static ?string $pluralModelLabel = 'Stock Analytics Dashboard';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'stock-analytics';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('tenant_id', auth()->user()->tenant_id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->columns([]);
    }


    public static function getWidgets(): array
    {
        return [
            StockOverviewWidget::class,
            ProfitByProductChart::class,
        ];
    }

    /* 🔒 Read-only */
    public static function canCreate(): bool
    {
        return false;
    }
    public static function canEdit($record): bool
    {
        return false;
    }
    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ViewStockStats::route('/'),
        ];
    }
}
