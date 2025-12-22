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
        ->striped()
        ->paginated([25, 50, 100])
        ->defaultPaginationPageOption(25)

        /* ROW HIGHLIGHTING */
        ->recordClasses(fn ($record) => match (true) {
            $record->item?->name === 'TOTAL' =>
                'bg-gray-200 dark:bg-gray-800 font-bold',

            $record->current_stock == 0 =>
                'bg-red-50 dark:bg-red-950/40',

            $record->current_stock > 0
                && $record->current_stock < ($record->item?->reorder_level ?? 0) =>
                'bg-amber-50 dark:bg-amber-950/40',

            default => null,
        })

        /* QUERY */
        ->query(
            fn () =>
            StockMovement::query()
                ->select([
                    'item_id as id',
                    'item_id',
                    'items.cost_price',
                    'items.reorder_level',

                    DB::raw("SUM(CASE WHEN movement_type = '" . StockMovementType::RESTOCK . "' THEN quantity ELSE 0 END) AS total_received"),
                    DB::raw("SUM(CASE WHEN movement_type = '" . StockMovementType::SALE . "' THEN quantity ELSE 0 END) AS total_sold"),
                    DB::raw("
                        SUM(CASE WHEN movement_type = '" . StockMovementType::RESTOCK . "' THEN quantity ELSE 0 END)
                        -
                        SUM(CASE WHEN movement_type = '" . StockMovementType::SALE . "' THEN quantity ELSE 0 END)
                        AS current_stock
                    "),
                ])
                ->join('items', 'items.id', '=', 'stock_movements.item_id')
                ->where('stock_movements.tenant_id', auth()->user()->tenant_id)
                ->groupBy('item_id', 'items.cost_price', 'items.reorder_level')
                ->orderBy('item_id', 'asc')
        )

        ->defaultSort('item_id', 'asc')

        /* COLUMNS */
        ->columns([
            Tables\Columns\TextColumn::make('item.name')
                ->label('Product')
                ->weight('bold')
                ->searchable()
                ->sortable()
                ->description(fn ($record) =>
                    $record->item?->name === 'TOTAL'
                        ? null
                        : 'Reorder @ ' . $record->item?->reorder_level
                )
                ->color('primary'),

            Tables\Columns\TextColumn::make('total_received')
                ->label('Received')
                ->numeric()
                ->alignCenter()
                ->color('success'),

            Tables\Columns\TextColumn::make('total_sold')
                ->label('Sold')
                ->numeric()
                ->alignCenter()
                ->color('danger'),

            Tables\Columns\BadgeColumn::make('current_stock')
                ->label('Stock')
                ->alignCenter()
                ->formatStateUsing(function ($state, $record) {
                    $level = $record->item?->reorder_level ?? 0;

                    return match (true) {
                        $state == 0 => 'Out (0)',
                        $state < $level => "{$state} ↓",
                        $state == $level => "{$state} ⚠",
                        default => "{$state} ✓",
                    };
                })
                ->colors([
                    'danger'  => fn ($record) => $record->current_stock == 0,
                    'warning' => fn ($record) =>
                        $record->current_stock > 0
                        && $record->current_stock <= ($record->item?->reorder_level ?? 0),
                    'success' => fn ($record) =>
                        $record->current_stock > ($record->item?->reorder_level ?? 0),
                ]),

            Tables\Columns\TextColumn::make('cost_price')
                ->label('Unit Cost')
                ->alignEnd()
                ->formatStateUsing(fn ($state) => 'KES ' . number_format($state, 0))
                ->color('gray'),

            Tables\Columns\TextColumn::make('received_value')
                ->label('Received Value')
                ->alignEnd()
                ->state(fn ($record) => $record->total_received * $record->cost_price)
                ->formatStateUsing(fn ($state) => 'KES ' . number_format($state, 0))
                ->color('success'),

            Tables\Columns\TextColumn::make('sold_value')
                ->label('Sold Value')
                ->alignEnd()
                ->state(fn ($record) => $record->total_sold * $record->cost_price)
                ->formatStateUsing(fn ($state) => 'KES ' . number_format($state, 0))
                ->color('danger'),

            Tables\Columns\TextColumn::make('stock_value')
                ->label('Stock Value')
                ->alignEnd()
                ->weight('bold')
                ->state(fn ($record) => $record->current_stock * $record->cost_price)
                ->formatStateUsing(fn ($state) => 'KES ' . number_format($state, 0))
                ->color('primary'),
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
