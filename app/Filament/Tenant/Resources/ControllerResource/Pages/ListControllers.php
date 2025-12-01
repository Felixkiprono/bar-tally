<?php

namespace App\Filament\Tenant\Resources\ControllerResource\Pages;

use App\Filament\Tenant\Resources\ControllerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListControllers extends ListRecords
{
    protected static string $resource = ControllerResource::class;

    protected static ?string $title = 'Physical Count Records';

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('New Physical Count')->slideOver(),
        ];
    }
}
