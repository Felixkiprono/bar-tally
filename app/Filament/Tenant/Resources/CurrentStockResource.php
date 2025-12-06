<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CurrentStockResource\Pages;
use App\Filament\Tenant\Resources\CurrentStockResource\RelationManagers;
use App\Models\CurrentStock;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\DB;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;

class CurrentStockResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Stock Management';
    protected static ?string $navigationLabel = 'Current Stock Levels';

    public static function canViewAny(): bool
    {
        return auth()->user()->isManager()
            || auth()->user()->isTenantAdmin()
            || auth()->user()->isStockist();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                fn() =>
                StockMovement::query()
                    ->select([
                        'item_id as id',
                        'item_id',
                        DB::raw("SUM(CASE WHEN movement_type = '" . StockMovementType::RESTOCK . "' THEN quantity ELSE 0 END) AS total_received"),
                        DB::raw("SUM(CASE WHEN movement_type = '" . StockMovementType::SALE . "' THEN quantity ELSE 0 END) AS total_sold"),
                        DB::raw("
                SUM(CASE WHEN movement_type = '" . StockMovementType::RESTOCK . "' THEN quantity ELSE 0 END)
                -
                SUM(CASE WHEN movement_type = '" . StockMovementType::SALE . "' THEN quantity ELSE 0 END)
                AS current_stock
            "),
                        'items.reorder_level',
                    ])
                    ->join('items', 'items.id', '=', 'stock_movements.item_id')
                    ->where('stock_movements.tenant_id', auth()->user()->tenant_id)
                    ->groupBy('item_id', 'items.reorder_level')
                    ->orderBy('item_id', 'asc')
            )
            ->defaultSort('item_id', 'asc')

            ->columns([
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('total_received')
                    ->label('Received')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_sold')
                    ->label('Sold')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('item.reorder_level')
                    ->label('Reorder Level')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('current_stock')
                    ->label('Current Stock')
                    ->formatStateUsing(function ($state, $record) {
                        $level = $record->item->reorder_level;

                        if ($state == 0) {
                            return "0 (Out of Stock)";
                        }

                        if ($state < $level) {
                            return "{$state} (Below Reorder)";
                        }

                        if ($state == $level) {
                            return "{$state} (At Reorder Level)";
                        }

                        return "{$state} (Sufficient)";
                    })
                    ->colors([
                        'danger'  => fn($record) => $record->current_stock == 0,                                   // Out of stock
                        'warning' => fn($record) => $record->current_stock > 0
                            && $record->current_stock < $record->item->reorder_level,   // Below reorder
                        'warning' => fn($record) => $record->current_stock == $record->item->reorder_level,       // At reorder
                        'success' => fn($record) => $record->current_stock > $record->item->reorder_level,        // Sufficient
                    ])

            ])

            ->filters([])

            ->actions([])

            ->bulkActions([]);
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
            'index' => Pages\ListCurrentStocks::route('/'),
        ];
    }
}
