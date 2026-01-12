<?php

namespace App\Filament\Tenant\Resources\StocksResource\Pages;

use App\Filament\Tenant\Resources\StocksResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Model;
use App\Services\DailySessionService;
use Illuminate\Support\Facades\Auth;


class ListStocks extends ListRecords
{
    protected static string $resource = StocksResource::class;

    protected static ?string $title = 'Stocks in Store';

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $sessionService = app(DailySessionService::class);
        return [

            Actions\CreateAction::make()
                            ->outlined()
                            ->disabled(fn() => !$sessionService->hasOpenSession($tenantId))
                            ->label('Add Stock')
                            ->slideOver(),
        ];
    }
}
