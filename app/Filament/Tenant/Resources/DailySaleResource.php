<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\DailySaleResource\Pages;
use App\Models\Counter;
use App\Models\DailySession;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Item;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Session;
use App\Services\Sale\SalesTemplateService;


class DailySaleResource extends Resource
{
    protected static ?string $model = StockMovement::class;
    protected static ?string $navigationIcon = 'heroicon-c-document-currency-dollar';
    protected static ?string $navigationGroup = 'Cashier';
    protected static ?string $navigationLabel = 'Record POS Sales';

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

                Forms\Components\Section::make('Record Sale')
                    ->description('Enter items sold at your assigned bar counter')
                    ->schema([

                        // CASHIER VIEW (label + hidden field)
                        Forms\Components\Group::make([
                            Forms\Components\Placeholder::make('counter_display')
                                ->label('Counter')
                                ->content(
                                    fn() =>
                                    Auth::user()->counters()->first()?->name ??
                                        'No Counter Assigned'
                                ),
                            // ->hintIcon('heroicon-o-rectangle-stack'),

                            Forms\Components\Hidden::make('counter_id')
                                ->default(fn() => Auth::user()->counters()->first()?->id),
                        ])
                            ->visible(fn() => Auth::user()->isCashier()),

                        // MANAGER / ADMIN VIEW
                        Forms\Components\Select::make('counter_id')
                            ->label('Counter')
                            ->options(
                                Counter::where('tenant_id', Auth::user()->tenant_id)
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->visible(fn() => !Auth::user()->isCashier())
                            ->required()
                            ->hint('Select where this sale was made'),
                        // ->hintIcon('heroicon-o-building-storefront'),

                        // PRODUCT
                        Forms\Components\Select::make('item_id')
                            ->label('Product')
                            // ->icon('heroicon-o-cube')
                            ->searchable()
                            ->options(
                                Item::where('tenant_id', Auth::user()->tenant_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->required(),

                        // QUANTITY
                        Forms\Components\TextInput::make('quantity')
                            ->label('Quantity Sold')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        // ->icon('heroicon-o-hashtag'),

                        // NOTES
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes (optional)')
                            ->placeholder('e.g. Happy hour discount or special remark...')
                            ->rows(2)
                            ->columnSpanFull(),

                        // Hidden data
                        Forms\Components\Hidden::make('movement_date')
                            ->default(now()),

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
                    ->columns(1), // ONE COLUMN FORM

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)

            ->headerActions([
                Action::make('downloadTemplate')
                    ->label('Download Sales Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(
                        fn(SalesTemplateService $service) =>
                        $service->downloadTemplate(auth()->user()->tenant_id)
                    ),

                Action::make('importSales')
                    ->label('Import Sales')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->required()
                            ->disk('local')
                            ->directory('imports/tmp')
                            ->preserveFilenames(),
                    ])
                    ->action(function (array $data, \App\Services\Sale\SalesImportService $service) {
                        $result = $service->preparePreview($data['file']);

                        Session::put('sale-import-rows', $result['rows']);
                        Session::put('sale-import-file', $result['file']);

                        return redirect()->route('filament.tenant.pages.sales-import-preview');
                    }),
            ])

            /* =========================
         | GROUPED QUERY (CRITICAL)
         * ========================= */
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
                    ->where('movement_type', StockMovementType::SALE)
                    ->groupBy('item_id', 'movement_date', 'created_at')
            )

            ->columns([

                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Product')
                    ->weight('bold')
                    ->searchable(),

                Tables\Columns\TextColumn::make('item.code')
                    ->label('SKU')
                    ->color('gray'),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Total Sold')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_sale_value')
                    ->label('Total Sale Value')
                    ->alignEnd()
                    ->state(
                        fn($record) =>
                        $record->total_quantity * ($record->item->selling_price ?? 0)
                    )
                    ->formatStateUsing(
                        fn($state) =>
                        'KES ' . number_format($state, 0)
                    )
                    ->weight('bold')
                    ->color('success'),

                /* =========================
             | COUNTER COLUMNS
             * ========================= */
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
                                    ->where('movement_type', StockMovementType::SALE)
                                    ->sum('quantity')
                            )
                            ->formatStateUsing(fn($state) => $state ?: '–')
                            ->color(fn($state) => $state > 0 ? 'primary' : 'gray')
                    ),
            ])

            ->defaultSort('movement_date', 'desc')
            ->emptyStateHeading('No Sales Recorded')
            ->emptyStateDescription('Sales you record today will appear here.');
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
