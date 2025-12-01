<?php

namespace App\Filament\Tenant\Resources\StocksResource\Pages;

use App\Filament\Tenant\Resources\StocksResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateStocks extends CreateRecord
{
    protected static string $resource = StocksResource::class;

    protected static ?string $title = 'Restock Product';
}
