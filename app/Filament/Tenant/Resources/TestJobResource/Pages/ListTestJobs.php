<?php

namespace App\Filament\Tenant\Resources\TestJobResource\Pages;

use App\Filament\Tenant\Resources\TestJobResource;
use Filament\Resources\Pages\ListRecords;

class ListTestJobs extends ListRecords
{
    protected static string $resource = TestJobResource::class;
}