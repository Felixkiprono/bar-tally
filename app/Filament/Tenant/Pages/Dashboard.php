<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Panel;
class Dashboard extends BaseDashboard
{


public function panel(Panel $panel): Panel
{
    return $panel
        // ...
        ->sidebarFullyCollapsibleOnDesktop();
}
    public static function canView(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isManager() || $user->isTenantAdmin() || $user->isAdmin();
    }
public function getWidgets(): array
{
    return [
        \App\Filament\Tenant\Widgets\DashboardQuickStats::class,
        \App\Filament\Tenant\Widgets\SalesChart::class,
        \App\Filament\Tenant\Widgets\StockChart::class,
    ];
}

    public function getTitle(): string
    {
        return 'Dashboard';
    }
}
