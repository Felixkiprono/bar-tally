<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\DailySaleResource\Pages;
use App\Filament\Tenant\Resources\DailySaleResource\RelationManagers;
use App\Models\Counter;
use App\Models\DailySale;
use App\Models\DailySession;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Item;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;

class DailySaleResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-c-document-currency-dollar';
    protected static ?string $navigationGroup = 'Cashier';
    protected static ?string $navigationLabel = 'Record Sales';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isManager() || $user->isTenantAdmin() || $user->isAdmin() || $user->isManager() || $user->isCashier();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
                Forms\Components\Section::make('Record Sale')
                    ->description('Enter items sold at your assigned bar counter')
                    ->schema([

                        // Select counter scoped to user->bar_id
                        Forms\Components\Select::make('counter_id')
                            ->label('Counter')
                            ->options(
                                Counter::query()
                                    // ->where('bar_id',  Auth::user()->bar_id)
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),

                        // Select product
                        Forms\Components\Select::make('item_id')
                            ->label('Product')
                            ->options(
                                Item::query()
                                    ->where('tenant_id',  Auth::user()->tenant_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity Sold')
                            ->numeric()
                            ->minValue(1)
                            ->required(),

                        Forms\Components\Hidden::make('movement_date')
                            ->label('Movement Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2)
                            ->nullable(),

                        Forms\Components\Hidden::make('tenant_id')
                            ->default(fn() => Auth::user()->tenant_id),

                        Forms\Components\Hidden::make('created_by')
                            ->default(fn() => Auth::id()),

                        Forms\Components\Hidden::make('session_id')
                            ->default(
                                fn() =>
                                DailySession::where('tenant_id', Auth::user()->tenant_id)
                                    ->where('is_open', true)
                                    ->first()
                                    ?->id
                            ),

                        Forms\Components\Hidden::make('movement_type')
                            ->default(StockMovementType::SALE),
                    ])
                    ->columns(2),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn($query) =>
                $query->where('movement_type', StockMovementType::SALE)->where('tenant_id', auth()->user()->tenant_id)->where('movement_date', today())
            )
            ->columns([

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),

                Tables\Columns\TextColumn::make('counter.name')
                    ->label('Counter')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Product')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\BadgeColumn::make('quantity')
                    ->label('Qty')
                    ->formatStateUsing(fn($state) => $state)
                    ->colors([
                        'danger' => fn($state) => $state < 0,
                    ]),
                Tables\Columns\TextColumn::make('item.selling_price')
                    ->label('Price')
                    ->money('kes', true) // true = show decimals
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('kes', true)
                    ->state(function ($record) {
                        $quantity = abs($record->quantity);
                        $price = $record->item->selling_price ?? 0;
                        return $quantity * $price;
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Cashier')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
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
            'index' => Pages\ListDailySales::route('/'),
            'create' => Pages\CreateDailySale::route('/create'),
        ];
    }
}
