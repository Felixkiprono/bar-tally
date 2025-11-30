<?php

namespace App\Filament\Pages;

use App\Jobs\TestJob;
use Filament\Pages\Page;
use Filament\Actions\Action;

class TestJobPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'Test Job';
    protected static ?string $navigationGroup = 'Testing';
    protected static ?int $navigationSort = 1;
    protected static bool $shouldRegisterNavigation = false; // This hides it from the menu

    protected static string $view = 'filament.pages.test-job';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('trigger')
                ->label('Trigger Test Job')
                ->icon('heroicon-o-play')
                ->action(function () {
                    $tenantId = app('currentTenant')->id;
                    TestJob::dispatch($tenantId);
                })
                ->requiresConfirmation()
                ->modalHeading('Trigger Test Job')
                ->modalDescription('Are you sure you want to trigger the test job?')
                ->modalSubmitActionLabel('Yes, trigger it')
        ];
    }
}