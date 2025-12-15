<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\StockStatsResource\Pages;
use App\Filament\Tenant\Resources\StockStatsResource\RelationManagers;
use App\Models\StockMovement;
use App\Models\StockStats;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Tenant\Widgets\StockOverviewWidget;

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
            ->heading('Recent Stock Movements')
            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Date')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('counter.name')
                    ->label('Counter')
                    ->placeholder('Warehouse'),

                Tables\Columns\BadgeColumn::make('movement_type')
                    ->label('Type')
                    ->colors([
                        'success' => 'restock',
                        'danger'  => 'sale',
                    ]),

                Tables\Columns\BadgeColumn::make('quantity')
                    ->label('Qty')
                    ->color('success')
                    ->formatStateUsing(fn($state) => "+{$state}"),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->sortable(),
            ])
            ->defaultSort('movement_date', 'desc')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10);
    }


    public static function getWidgets(): array
    {
        return [
            StockOverviewWidget::class,
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
