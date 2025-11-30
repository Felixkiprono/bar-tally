<?php

namespace App\Filament\Resources\TenantAdminResource\Pages;

use App\Filament\Resources\TenantAdminResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTenantAdmin extends EditRecord
{
    protected static string $resource = TenantAdminResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}