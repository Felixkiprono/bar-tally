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
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Services\DailySessionService;
use Illuminate\Validation\ValidationException;
class ListControllers extends ListRecords
{
    protected static string $resource = ControllerResource::class;

    protected static ?string $title = 'Physical Count Records';


    protected function getHeaderActions(): array
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $sessionService = app(DailySessionService::class);

        return [
             /* =========================
             | OPEN DAY
             * ========================= */
            Action::make('openDay')
                ->label('Open Day')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn () => ! $sessionService->hasOpenSession($tenantId))
                ->action(function () use ($sessionService, $tenantId) {
                    try {
                        $sessionService->open($tenantId);

                        Notification::make()
                            ->title('Day opened successfully')
                            ->success()
                            ->send();

                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Cannot open day')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->after(fn () => $this->dispatch('$refresh')),

            /* =========================
             | CLOSE DAY
             * ========================= */
            Action::make('closeDay')
                ->label('Close Day')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $sessionService->hasOpenSession($tenantId))
                ->action(function () use ($sessionService, $tenantId) {
                    try {
                        $sessionService->close($tenantId);

                        Notification::make()
                            ->title('Day closed successfully')
                            ->success()
                            ->send();

                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Cannot close day')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->after(fn () => $this->dispatch('$refresh')),

            Actions\CreateAction::make()
                ->color('primary')
                ->icon('heroicon-o-check-circle')
                ->disabled(fn () => !$sessionService->hasOpenSession($tenantId))
                ->label('New Physical Count')
                ->slideOver(),
        ];

    }
}
