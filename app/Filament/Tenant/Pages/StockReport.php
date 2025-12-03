<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;

class StockReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static ?string $navigationGroup = 'Reports';
    protected static ?string $navigationLabel = 'Variance Report';

    protected static string $view = 'filament.tenant.pages.stock-report';

}
