<?php

namespace App\Filament\Tenant\Resources\StocksResource\Pages;

use App\Filament\Tenant\Resources\StocksResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStocks extends ListRecords
{
    protected static string $resource = StocksResource::class;

        protected static ?string $title = 'Stocks in Store';

    protected function getHeaderActions(): array
    {
        return [

            Actions\CreateAction::make()->label('Add Stock')->slideOver(),
        ];
    }
}
