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
use App\Models\Counter;
use Filament\Tables\Actions\Action;
use App\Support\SalesImportHandler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;
use App\Services\Stock\StockTemplateService;
use App\Services\Stock\StockImportService;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Hidden;

class StocksResource extends Resource
{
    protected static ?string $model = StockMovement::class;
    protected static ?string $navigationIcon = 'heroicon-o-square-2-stack';
    protected static ?string $navigationGroup = 'Stock Management';
    protected static ?string $navigationLabel = 'Purchase/Recieve Stock';
    protected static ?int $navigationSort = 2;


    protected function getSubheading(): ?string
    {
        return 'Use Download Template to prepare stock intake, then Import Stock to allocate quantities to each counter (shown as Counter Qty / Total).';
    }

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
                Forms\Components\Section::make('Receive Stock (Excel Style)')
                    ->description('Allocate received stock across counters')
                    ->schema([

                        // ROW 1: Product + Total
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\Select::make('item_id')
                                ->label('Product')
                                ->options(
                                    Item::where('tenant_id', Auth::user()->tenant_id)
                                        ->pluck('name', 'id')
                                )
                                ->searchable()
                                ->required(),

                            Forms\Components\TextInput::make('total_quantity')
                                ->label('Total Quantity')
                                ->numeric()
                                ->required()
                                ->minValue(1),
                        ]),

                        // ROW 2: COUNTERS (EXCEL STYLE)
                        Forms\Components\Fieldset::make('Counter Distribution')
                            ->schema([
                                Forms\Components\Grid::make(
                                    Counter::where('tenant_id', Auth::user()->tenant_id)->count()
                                )
                                    ->schema(
                                        Counter::where('tenant_id', Auth::user()->tenant_id)
                                            ->orderBy('name')
                                            ->get()
                                            ->map(
                                                fn($counter) =>
                                                Forms\Components\TextInput::make("counters.{$counter->id}")
                                                    ->label($counter->name)
                                                    ->numeric()
                                                    ->default(0)
                                                    ->minValue(0)
                                            )
                                            ->toArray()
                                    ),
                            ]),

                        // ROW 3: Date + Notes
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\DatePicker::make('movement_date')
                                ->label('Date')
                                ->default(now())
                                ->required(),

                            Forms\Components\Textarea::make('notes')
                                ->rows(2),
                        ]),

                        // HIDDEN
                        Forms\Components\Hidden::make('movement_type')
                            ->default(StockMovementType::RESTOCK),

                        Forms\Components\Hidden::make('tenant_id')
                            ->default(Auth::user()->tenant_id),

                        Forms\Components\Hidden::make('created_by')
                            ->default(Auth::id()),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
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
                $query
                    ->selectRaw("
            created_at,
            CONCAT(item_id, '-', movement_date) AS id,
            item_id,
            movement_date,
            SUM(quantity) AS total_quantity
        ")
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->where('movement_type', StockMovementType::RESTOCK)
                    ->groupBy('item_id', 'movement_date', 'created_at')
            )

            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable()
                    ->color('gray'),

                /* PRODUCT */
                Tables\Columns\TextColumn::make('item.name')
                    ->label('Product')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('item.code')
                    ->label('SKU')
                    ->color('gray')
                    ->toggleable(),

                /* TOTAL QTY */
                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Total')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),

                ...Counter::where('tenant_id', auth()->user()->tenant_id)
                    ->orderBy('name')
                    ->get()
                    ->map(
                        fn($counter) =>
                        Tables\Columns\TextColumn::make("counter_{$counter->id}")
                            ->label($counter->name)
                            ->alignCenter()
                            ->state(
                                fn($record) =>
                                StockMovement::where('item_id', $record->item_id)
                                    ->where('counter_id', $counter->id)
                                    ->whereDate('movement_date', $record->movement_date)
                                    ->where('movement_type', StockMovementType::RESTOCK) // ✅ IMPORTANT
                                    ->sum('quantity')
                            )
                            ->formatStateUsing(fn($state) => $state > 0 ? $state : '–')
                            ->color(fn($state) => $state > 0 ? 'primary' : 'gray')
                    ),
                Tables\Columns\TextColumn::make('item.cost_price')
                    ->label('Buy Price')
                    ->alignEnd()
                    ->formatStateUsing(
                        fn($state) =>
                        'KES ' . number_format($state, 0)
                    )
                    ->color('gray'),
                Tables\Columns\TextColumn::make('total_cost')
                    ->label('Total Cost')
                    ->alignEnd()
                    ->state(
                        fn($record) =>
                        $record->item->cost_price * $record->total_quantity
                    )
                    ->formatStateUsing(
                        fn($state) =>
                        'KES ' . number_format($state, 0)
                    )
                    ->weight('bold')
                    ->color('primary')




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
