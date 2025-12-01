<?php

namespace App\Filament\Tenant\Resources\ControllerResource\Pages;

use App\Filament\Tenant\Resources\ControllerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateController extends CreateRecord
{
    protected static string $resource = ControllerResource::class;

    protected static ?string $title = 'Closing Physical Count';
}
