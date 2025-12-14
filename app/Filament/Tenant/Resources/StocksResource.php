<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\StocksResource\Pages;
use App\Filament\Tenant\Resources\StocksResource\RelationManagers;
use App\Models\StockMovement;
use App\Models\Stocks;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;
use Filament\Tables\Actions\Action;
use App\Support\SalesImportHandler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use App\Services\Stock\StockTemplateService;
use App\Services\Stock\StockImportService;

class StocksResource extends Resource
{
    protected static ?string $model = StockMovement::class;
    protected static ?string $navigationIcon = 'heroicon-o-square-2-stack';
    protected static ?string $navigationGroup = 'Stock Management';
    protected static ?string $navigationLabel = 'Purchase/Recieve Stock';
    protected static ?int $navigationSort = 2;


    /*
    |--------------------------------------------------------------------------
    | PERMISSIONS
    |--------------------------------------------------------------------------
    */

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        return $user->isManager() || $user->isTenantAdmin() || $user->isAdmin() || $user->isStockist();
    }



    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Forms\Components\Section::make('Receive Stock')
                    ->description('Record stock received into central store')
                    ->schema([

                        Forms\Components\Select::make('item_id')
                            ->label('Item')
                            ->options(
                                Item::query()
                                    ->where('tenant_id', Auth::user()->tenant_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity Received')
                            ->numeric()
                            ->required()
                            ->minValue(1),

                        Forms\Components\Hidden::make('movement_type')
                            ->default(StockMovementType::RESTOCK),

                        Forms\Components\DatePicker::make('movement_date')
                            ->label('Date')
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
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                // DOWNLOAD TEMPLATE
                Action::make('downloadTemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function (StockTemplateService $service) {
                        return $service->downloadTemplate(auth()->user()->tenant_id);
                    })
                    ->color('success'),

                // IMPORT STOCK
                Action::make('importStock')
                    ->label('Import Stock')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV or Excel')
                            ->required()
                            ->disk('local')
                            ->directory('imports/tmp')
                            ->preserveFilenames(),
                    ])
                    ->action(function (array $data, StockImportService $service) {

                        $result = $service->preparePreview($data['file']);

                        Session::put('stock-import-rows', $result['rows']);
                        Session::put('stock-import-file', $result['file']);

                        return redirect()->route('filament.tenant.pages.stock-import-preview');
                    })
            ])
            ->modifyQueryUsing(
                fn($query) =>
                $query->where('movement_type', StockMovementType::RESTOCK)->where('tenant_id', auth()->user()->tenant_id)->where('movement_date', today())
            )

            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('TimeStamp')
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
                    ->label('Qty (Counter / Total)')
                    ->state(function ($record) {

                        $grandTotal = \App\Models\StockMovement::query()
                            ->where('tenant_id', $record->tenant_id)
                            ->where('movement_type', \App\Constants\StockMovementType::RESTOCK)
                            ->whereDate('movement_date', $record->movement_date)
                            ->where('item_id', $record->item_id)
                            ->sum('quantity');

                        return "{$record->quantity} / {$grandTotal}";
                    })
                    ->colors([
                        'success' => fn($state) => true,
                    ])
                    ->formatStateUsing(fn($state) => "{$state}"),

                // Tables\Columns\TextColumn::make('notes')
                //     ->limit(30)
                //     ->placeholder('-'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Recorded By')
                    ->sortable(),
            ])

            ->defaultSort('movement_date', 'desc')

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
            'index' => Pages\ListStocks::route('/'),
            'create' => Pages\CreateStocks::route('/create'),
        ];
    }
}
