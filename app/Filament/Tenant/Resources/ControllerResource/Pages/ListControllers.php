<?php

namespace App\Filament\Tenant\Resources\ControllerResource\Pages;

use App\Filament\Tenant\Resources\ControllerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Models\Counter;
use App\Models\Item;
use App\Models\DailySession;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use App\Constants\StockMovementType;

class ListControllers extends ListRecords
{
    protected static string $resource = ControllerResource::class;

    protected static ?string $title = 'Physical Count Records';


    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->color('primary')
                ->icon('heroicon-o-check-circle')
                ->label('New Physical Count')
                ->slideOver(),
        ];
        // $user = Auth::user();

        // return [

        //     Actions\CreateAction::make()
        //       ->color('primary')
        //         ->icon('heroicon-o-check-circle')
        //     ->label('New Physical Count')
        //     ->slideOver(),
        //     Actions\Action::make('closingCount')
        //         ->label('Record Closing Count')
        //         ->color('primary')
        //         ->icon('heroicon-o-check-circle')
        //         ->slideOver() // Optional: makes it beautiful
        //         ->form([
        //             \Filament\Forms\Components\Card::make()
        //                 ->schema([
        //                     \Filament\Forms\Components\Select::make('counter_id')
        //                         ->options(
        //                             Counter::quer()->pluck('name', 'id')
        //                         )
        //                         ->label('Counter'),

        //                     \Filament\Forms\Components\Select::make('item_id')
        //                         ->options(
        //                             Item::where('tenant_id', $user->tenant_id)->pluck('name', 'id')
        //                         )
        //                         ->label('Product')
        //                         ->required(),

        //                     \Filament\Forms\Components\TextInput::make('quantity')
        //                         ->numeric()
        //                         ->label('Closing Stock Count')
        //                         ->required(),

        //                     \Filament\Forms\Components\DatePicker::make('movement_date')
        //                         ->default(today())
        //                         ->required(),

        //                     \Filament\Forms\Components\Textarea::make('notes')
        //                         ->rows(2),

        //                     \Filament\Forms\Components\Hidden::make('tenant_id')
        //                         ->default($user->tenant_id),

        //                     \Filament\Forms\Components\Hidden::make('created_by')
        //                         ->default($user->id),

        //                     \Filament\Forms\Components\Hidden::make('movement_type')
        //                         ->default(StockMovementType::CLOSING),

        //                     \Filament\Forms\Components\Hidden::make('session_id')
        //                         ->default(
        //                             DailySession::where('tenant_id', $user->tenant_id)
        //                                 ->where('is_open', true)
        //                                 ->value('id')
        //                         ),
        //                 ])
        //                 ->columns(2)
        //         ])
        //         ->action(function (array $data) {
        //             StockMovement::create($data);
        //         }),

        // ];
    }
}
