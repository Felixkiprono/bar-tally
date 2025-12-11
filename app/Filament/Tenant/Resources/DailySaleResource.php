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
use Pxlrbt\FilamentExcel\Actions\Tables\ImportAction;
use Pxlrbt\FilamentExcel\Imports\ExcelImport;
use Filament\Tables\Actions\Action;
use App\Support\SalesImportHandler;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;

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
            ->headerActions([
                Action::make('downloadTemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {

                        $tenantId = auth()->user()->tenant_id;

                        // Fetch actual counters & items for user convenience
                        $counters = \App\Models\Counter::where('tenant_id', $tenantId)->pluck('name')->toArray();
                        $items = \App\Models\Item::where('tenant_id', $tenantId)->pluck('name')->toArray();

                        // Build CSV header
                        $csv = "counter,product,quantity,notes\n";

                        // Provide sample rows using real data
                        $sampleRows = [];

                        foreach ($items as $index => $itemName) {
                            $sampleRows[] = [
                                'counter'  => $counters[$index % max(1, count($counters))] ?? 'Counter A',
                                'product'  => $itemName,
                                'quantity' => 0,
                                'notes'    => '',
                            ];
                        }

                        foreach ($sampleRows as $row) {
                            $csv .= implode(",", [
                                $row['counter'],
                                $row['product'],
                                $row['quantity'],
                                $row['notes'],
                            ]) . "\n";
                        }

                        // Store CSV temporarily
                        $fileName = 'sales_import_template_' . date('Ymd_His') . '.csv';
                        $path = storage_path('app/' . $fileName);

                        file_put_contents($path, $csv);

                        return response()->download($path)->deleteFileAfterSend(true);
                    })
                    ->color('success'),
                Action::make('importSales')
                    ->label('Import Sales')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV or Excel')
                            ->required()
                            ->disk('local') // important!
                            ->directory('imports/tmp')
                            ->preserveFilenames()
                    ])
                    ->action(function (array $data) {

                        // absolute path to the temp file created by Filament
                        $tempPath = Storage::disk('local')->path($data['file']);

                        // create a 100% safe permanent path
                        $permanentFilename = uniqid() . '.csv';
                        $permanentRelative = 'imports/permanent/' . $permanentFilename;

                        // copy the file
                        Storage::disk('local')->copy($data['file'], $permanentRelative);

                        // absolute path for Excel reader
                        $absolutePath = Storage::disk('local')->path($permanentRelative);

                        if (! file_exists($absolutePath)) {
                            throw new \Exception("Permanent file not found: $absolutePath");
                        }

                        // load rows from permanent file
                        $rows = \App\Support\SalesImportHandler::loadRows($absolutePath);

                        // store rows in session
                        Session::put('sales-import-rows', $rows);

                        // redirect to preview page
                        return redirect()->route('filament.tenant.pages.sales-import-preview');
                    })
            ])
            ->modifyQueryUsing(
                fn($query) =>
                $query->where('movement_type', StockMovementType::SALE)
                    ->where('tenant_id', Auth::user()->tenant_id)
                    ->whereDate('movement_date', today())
            )
            ->columns([

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('H:i')
                    ->icon('heroicon-o-clock')
                    ->sortable(),

                Tables\Columns\TextColumn::make('counter.name')
                    ->label('Counter')
                    ->weight('bold')
                    ->icon('heroicon-o-rectangle-group')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Product')
                    ->searchable()
                    ->icon('heroicon-o-cube')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('quantity')
                    ->label('Qty')
                    ->colors(['primary'])
                    ->icon('heroicon-o-hashtag')
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.selling_price')
                    ->label('Price')
                    ->money('kes', true)
                    ->icon('heroicon-o-currency-dollar')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('kes', true)
                    ->state(
                        fn($record) =>
                        abs($record->quantity) * ($record->item->selling_price ?? 0)
                    )
                    ->color('success')
                    ->weight('bold')
                    ->icon('heroicon-o-calculator')
                    ->sortable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Cashier')
                    ->icon('heroicon-o-user')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No Sales Recorded')
            ->emptyStateDescription('Sales you record today will appear here.')
            ->emptyStateIcon('heroicon-o-document-text');
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
