<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ControllerResource\Pages;
use App\Filament\Tenant\Resources\ControllerResource\RelationManagers;
use App\Models\Controller;
use App\Models\StockMovement;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Counter;
use App\Models\Item;
use App\Models\DailySession;
use Filament\Forms\Components\Hidden;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;
use App\Support\PhysicalCountImportHandler;
use App\Support\PhysicalCountExportHandler;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Session;

class ControllerResource extends Resource
{
    protected static ?string $model = StockMovement::class;

    protected static ?string $navigationIcon = 'heroicon-s-lock-closed';

    protected static ?string $navigationGroup = 'Controller';

    protected static ?string $navigationLabel = 'Closing Count';

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
        return $table
            ->headerActions([
                Action::make('importPhysicalCount')
                    ->label('Import Physical Count')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Forms\Components\FileUpload::make('file')
                            ->label('Upload CSV or Excel')
                            ->required()
                            ->disk('local')
                            ->directory('imports/tmp')
                            ->preserveFilenames(),
                    ])
                    ->action(function (array $data) {

                        // temp path (Filament upload)
                        $tempPath = Storage::disk('local')->path($data['file']);

                        // permanent safe path
                        $permanentFilename = uniqid('count_') . '.csv';
                        $permanentRelative = 'imports/permanent/' . $permanentFilename;
                        Storage::disk('local')->copy($data['file'], $permanentRelative);

                        $absolutePath = Storage::disk('local')->path($permanentRelative);

                        if (! file_exists($absolutePath)) {
                            throw new \Exception("Permanent import file not found: {$absolutePath}");
                        }

                        // load rows
                        $rows = \App\Support\PhysicalCountImportHandler::loadRows($absolutePath);

                        Session::put('physical-count-rows', $rows);

                        return redirect()->route('filament.tenant.pages.stock-count-import-preview');
                    }),
                Action::make('downloadTemplate')
                    ->label('Download Count Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {

                        $tenantId = Auth::user()->tenant_id;

                        // Fetch all items for this tenant
                        $items = \App\Models\Item::where('tenant_id', $tenantId)
                            ->orderBy('name')
                            ->get(['name', 'code']);

                        $headers = [
                            'Content-Type'        => 'text/csv',
                            'Content-Disposition' => 'attachment; filename="physical_count_template.csv"',
                        ];

                        return response()->streamDownload(function () use ($items) {

                            $file = fopen('php://output', 'w');

                            // CSV Header
                            fputcsv($file, ['counter', 'sku', 'product', 'quantity', 'notes']);

                            // Body: list all items
                            foreach ($items as $item) {
                                fputcsv($file, [
                                    '',                // counter (user will fill)
                                    $item->code ?? '', // SKU
                                    $item->name,       // Product name
                                    '',                // quantity (user will fill)
                                    '',                // notes (optional)
                                ]);
                            }

                            fclose($file);
                        }, 'physical_count_template.csv', $headers);
                    })


            ])

            ->modifyQueryUsing(
                fn($query) =>
                $query->where('movement_type', StockMovementType::CLOSING)->where('tenant_id', auth()->user()->tenant_id)->where('movement_date', today())
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
                    ->label('Closing Count')
                    ->colors([
                        'success' => fn($state) => $state >= 0,
                    ]),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Controller')
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->limit(30)
                    ->placeholder('-'),
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
            'index' => Pages\ListControllers::route('/'),
            'create' => Pages\CreateController::route('/create'),
        ];
    }
}
