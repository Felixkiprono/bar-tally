<?php

namespace App\Filament\Tenant\Resources\ItemResource\Pages;

use App\Filament\Tenant\Resources\ItemResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateItem extends CreateRecord
{
    protected static string $resource = ItemResource::class;
}
