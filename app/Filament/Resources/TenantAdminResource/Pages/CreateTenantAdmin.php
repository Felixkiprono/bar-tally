<?php

namespace App\Filament\Resources\TenantAdminResource\Pages;

use App\Filament\Resources\TenantAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateTenantAdmin extends CreateRecord
{
    protected static string $resource = TenantAdminResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}