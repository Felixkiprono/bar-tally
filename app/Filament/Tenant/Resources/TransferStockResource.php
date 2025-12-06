<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TransferStockResource\Pages;
use App\Filament\Tenant\Resources\TransferStockResource\RelationManagers;
use App\Models\StockMovement;
use App\Models\TransferStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Item;
use App\Models\Counter;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;

class TransferStockResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Stock Management';
    protected static ?string $navigationLabel = 'Transfer Stock';

    public static function form(Form $form): Form
    {

        $user = Auth::user();
        return $form->schema([
            Forms\Components\Section::make('Transfer Stock to Counter')
                ->description('Move stock from warehouse to a specific counter')
                ->schema([

                    Forms\Components\Select::make('item_id')
                        ->label('Item')
                        ->options(Item::where('tenant_id', Auth::user()->tenant_id)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Forms\Components\Select::make('counter_id')
                        ->label('Counter')
                        ->options(Counter::where('tenant_id', Auth::user()->tenant_id)->pluck('name', 'id'))
                        ->searchable()
                        ->required(),

                    Forms\Components\TextInput::make('quantity')
                        ->numeric()
                        ->minValue(1)
                        ->required()
                        ->label('Quantity'),

                    Forms\Components\Textarea::make('notes')
                        ->label('Notes (Optional)')
                        ->rows(3),
                    Forms\Components\Hidden::make('tenant_id')
                        ->default(fn() => Auth::user()->tenant_id),

                    Forms\Components\Hidden::make('created_by')
                        ->default(fn() => Auth::user()->id),
                    Forms\Components\Hidden::make('movement_type')
                        ->default(StockMovementType::TRANSFER_TO_COUNTER),
                    Forms\Components\DatePicker::make('movement_date')
                        ->label('Transfer Date')
                        ->default(now())
                        ->required(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
       return $table
            ->modifyQueryUsing(fn ($query) =>
                $query->where('movement_type', StockMovementType::TRANSFER_TO_COUNTER)
                      ->where('tenant_id', Auth::user()->tenant_id)
            )

            ->columns([
                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('counter.name')
                    ->label('Counter')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(20)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Transferred By'),
            ])

            ->defaultSort('movement_date', 'desc')

            ->filters([
                Tables\Filters\SelectFilter::make('counter_id')
                    ->label('Counter')
                    ->options(Counter::where('tenant_id', Auth::user()->tenant_id)->pluck('name', 'id'))
            ])

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
            'index' => Pages\ListTransferStocks::route('/'),
            'create' => Pages\CreateTransferStock::route('/create'),
        ];
    }
}
