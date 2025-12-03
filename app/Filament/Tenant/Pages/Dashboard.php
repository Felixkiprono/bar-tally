<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
class Dashboard extends BaseDashboard
{


    public function getWidgets(): array
    {
        return [
        ];
    }

    public function getTitle(): string
    {
        return 'Dashboard';
    }
}
