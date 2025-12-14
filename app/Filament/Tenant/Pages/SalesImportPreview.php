<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Session;
use App\Services\Sale\SalesImportService;

class SalesImportPreview extends Page
{
    protected static string $view = 'filament.tenant.pages.sales-import-preview';
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public array $rows = [];

  public function mount()
{
    $this->rows = collect(Session::get('sale-import-rows', []))
        ->filter(function ($row) {
            return ! empty($row['product'] ?? null)
                && ! empty($row['quantity'] ?? null);
        })
        ->values()
        ->toArray();

    if (empty($this->rows)) {
        return redirect()->route('filament.tenant.resources.daily-sales.index');
    }
}


    public function import(SalesImportService $service)
    {
        $service->commit(
            $this->rows,
            auth()->user()->tenant_id,
            auth()->id()
        );

        Session::forget([
            'sale-import-rows',
            'sale-import-file',
        ]);

        session()->flash('success', 'Sales imported successfully!');
        return redirect()->route('filament.tenant.resources.daily-sales.index');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
