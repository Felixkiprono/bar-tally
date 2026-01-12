<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ControllerResource\Pages;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Counter;
use App\Models\Item;
use App\Models\DailySession;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Session;
use App\Services\Stock\PhysicalCountTemplateService;
use App\Services\Stock\StockCountImportService;
use App\Services\DailySessionService;

class ControllerResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-s-lock-closed';

    protected static ?string $navigationGroup = 'Controller';

    protected static ?string $navigationLabel = 'Controller Count';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isManager() || $user->isTenantAdmin() || $user->isAdmin() || $user->isManager() || $user->isController();
    }

    public static function form(Form $form): Form
    {
        $user = Auth::user();

        return $form
            ->schema([

                Forms\Components\Section::make('Closing Count')
                    ->description('Record final physical stock at your counter')
                    ->schema([

                        // Counter selection – only from user's bar
                        Forms\Components\Select::make('counter_id')
                            ->label('Counter')
                            ->options(
                                Counter::query()
                                    ->pluck('name', 'id')
                            )
                            ->searchable(),

                        // Item selection
                        Forms\Components\Select::make('item_id')
                            ->label('Product')
                            ->options(
                                Item::query()
                                    ->where('tenant_id', $user->tenant_id)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('quantity')
                            ->label('Closing Count')
                            ->numeric()
                            ->minValue(0)
                            ->required(),

                        Forms\Components\DatePicker::make('movement_date')
                            ->label('Date')
                            ->default(now())
                            ->required(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->nullable()
                            ->rows(2),

                        Forms\Components\Hidden::make('tenant_id')
                            ->default($user->tenant_id),

                        Forms\Components\Hidden::make('created_by')
                            ->default($user->id),

                        Forms\Components\Hidden::make('movement_type')
                            ->default(StockMovementType::CLOSING),

                        Forms\Components\Hidden::make('session_id')
                            ->default(
                                fn() =>
                                DailySession::where('tenant_id', Auth::user()->tenant_id)
                                    ->where('is_open', true)
                                    ->first()
                                    ?->id
                            ),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $sessionService = app(DailySessionService::class);

        return $table
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)

            /* =========================
         | HEADER ACTIONS
         * ========================= */
            ->headerActions([

                Action::make('downloadTemplate')
                    ->label('Download Count Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('default')
                    ->outlined()
                    ->disabled(fn() => !$sessionService->hasOpenSession($tenantId))
                    ->action(
                        fn(PhysicalCountTemplateService $service) =>
                        $service->downloadTemplate(auth()->user()->tenant_id)
                    ),

                Action::make('importPhysicalCount')
                    ->label('Import Physical Count')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->outlined(false)
                    ->disabled(fn() => !$sessionService->hasOpenSession($tenantId))
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV or Excel')
                            ->required()
                            ->disk('local')
                            ->directory('imports/tmp')
                            ->preserveFilenames(),
                    ])
                    ->action(function (array $data, StockCountImportService $service) {

                        $result = $service->preparePreview($data['file']);

                        Session::put('stock-count-import-rows', $result['rows']);
                        Session::put('stock-count-import-file', $result['file']);

                        return redirect()->route(
                            'filament.tenant.pages.stock-count-import-preview'
                        );
                    }),
            ])

            /* =========================
         | QUERY (PIVOT SOURCE)
         * ========================= */
            ->modifyQueryUsing(
                fn($query) =>
                $query
                    ->selectRaw("
                        CONCAT(item_id, '-', movement_date) AS id,
                        item_id,
                        movement_date,
                        SUM(quantity) AS total_quantity
                    ")
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->where('movement_type', StockMovementType::CLOSING)
                    ->groupBy('item_id', 'movement_date')
            )

            /* =========================
         | COLUMNS (MATCH STOCK)
         * ========================= */
            ->columns([

                Tables\Columns\TextColumn::make('movement_date')
                    ->label('Date')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Product')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('item.code')
                    ->label('SKU')
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_quantity')
                    ->label('Total')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total_count_value')
                    ->label('Total Count Value')
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
                // COUNTER COLUMNS (DYNAMIC – SAME AS STOCK)
                ...Counter::where('tenant_id', auth()->user()->tenant_id)
                    ->orderBy('name')
                    ->get()
                    ->map(
                        fn($counter) =>
                        Tables\Columns\TextColumn::make("counter_{$counter->id}")
                            ->label($counter->name)
                            ->alignCenter()
                            ->state(function ($record) use ($counter) {
                                return StockMovement::where('item_id', $record->item_id)
                                    ->where('counter_id', $counter->id)
                                    ->whereDate('movement_date', $record->movement_date)
                                    ->selectRaw("
                        SUM(
                            CASE
                                WHEN movement_type = 'restock' THEN quantity
                                WHEN movement_type = 'closing' THEN quantity
                                WHEN movement_type = 'sale' THEN -quantity
                                ELSE 0
                            END
                        ) as stock
                    ")
                                    ->value('stock');
                            })
                            ->formatStateUsing(fn($state) => $state > 0 ? $state : '–')
                            ->color(fn($state) => $state > 0 ? 'success' : 'gray')
                    )

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
            'index' => Pages\ListControllers::route('/'),
            'create' => Pages\CreateController::route('/create'),
        ];
    }
}
