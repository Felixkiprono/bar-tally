<?php

namespace App\Filament\Tenant\Resources\CurrentStockResource\Pages;

use App\Filament\Tenant\Resources\CurrentStockResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCurrentStock extends CreateRecord
{
    protected static string $resource = CurrentStockResource::class;
}
