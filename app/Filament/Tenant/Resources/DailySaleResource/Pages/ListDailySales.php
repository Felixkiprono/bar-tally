<?php

namespace App\Filament\Tenant\Resources\DailySaleResource\Pages;

use App\Filament\Tenant\Resources\DailySaleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Actions\Action;

class ListDailySales extends ListRecords
{
    protected static string $resource = DailySaleResource::class;

    protected static ?string $title = 'Sales Records';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('Add Sale')->slideOver(),

        ];
    }
}
