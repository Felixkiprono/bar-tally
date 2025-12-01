<?php

namespace App\Filament\Tenant\Resources\DailySaleResource\Pages;

use App\Filament\Tenant\Resources\DailySaleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateDailySale extends CreateRecord
{
    protected static string $resource = DailySaleResource::class;

    protected static ?string $title = 'Add New Sale';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Make qty negative for sales
        $data['quantity'] = $data['quantity'] * -1;
        return $data;
    }
}
