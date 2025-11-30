<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ItemResource\Pages;
use App\Filament\Tenant\Resources\ItemResource\RelationManagers;
use App\Models\Item;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
class ItemResource extends Resource
{
    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

     protected static ?string $navigationGroup = 'Stock Management';

    protected static ?string $navigationLabel = 'Products';


    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make('Product Details')
                    ->schema([
                          Hidden::make('tenant_id')
                            ->default(fn() => Auth::user()->tenant_id),
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('SKU / Code')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('brand')
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\Select::make('category')
                            ->options([
                                'beer' => 'Beer',
                                'spirits' => 'Spirits',
                                'wine' => 'Wine',
                                'soft_drink' => 'Soft Drink',
                                'food' => 'Food',
                                'other' => 'Other',
                            ])
                            ->nullable(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Pricing & Stock')
                    ->schema([
                        Forms\Components\TextInput::make('unit')
                            ->required()
                            ->placeholder('BTT / GL / PCS'),

                        Forms\Components\TextInput::make('cost_price')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('selling_price')
                            ->numeric()
                            ->nullable(),

                        Forms\Components\TextInput::make('reorder_level')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(4),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                //

                  Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('SKU')
                    ->sortable(),

                Tables\Columns\TextColumn::make('brand')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit')
                    ->sortable(),

                Tables\Columns\TextColumn::make('selling_price')
                    ->money('KES', divideBy: 1)
                    ->sortable(),

                Tables\Columns\TextColumn::make('reorder_level')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
        ];
    }
}
