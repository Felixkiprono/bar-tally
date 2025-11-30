<?php

namespace App\Filament\Resources\TenantAdminResource\Pages;

use App\Filament\Resources\TenantAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenantAdmins extends ListRecords
{
    protected static string $resource = TenantAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}