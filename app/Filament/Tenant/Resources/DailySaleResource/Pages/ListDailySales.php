<?php

namespace App\Filament\Tenant\Resources\DailySaleResource\Pages;

use App\Filament\Tenant\Resources\DailySaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use App\Services\DailySessionService;
use Illuminate\Support\Facades\Auth;

class ListDailySales extends ListRecords
{
    protected static string $resource = DailySaleResource::class;

    protected static ?string $title = 'Sales Records';

    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $sessionService = app(DailySessionService::class);
        return [
            Actions\CreateAction::make()
                ->label('Add Sale Entry')
                ->slideOver()
                ->outlined()
                ->disabled(fn() => !$sessionService->hasOpenSession($tenantId)),

        ];
    }
}
