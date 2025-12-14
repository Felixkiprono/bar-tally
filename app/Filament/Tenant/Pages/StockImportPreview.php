<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use App\Models\Item;
use App\Models\StockMovement;
use App\Services\Stock\StockImportService;


class StockImportPreview extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static string $view = 'filament.tenant.pages.stock-import-preview';

    public $rows = [];

    public function mount()
    {
        $this->rows = Session::get('stock-import-rows', []);

        if (empty($this->rows)) {
            return redirect()->route('filament.tenant.resources.stock-movements.index');
        }
    }

    public function import(StockImportService $service)
    {
        $rows = Session::get('stock-import-rows', []);

        if (empty($rows)) {
            session()->flash('error', 'No data to import.');
            return redirect()->route('filament.tenant.resources.stocks.index');
        }

        try {
            $service->commit(
                $rows,
                Auth::user()->tenant_id,
                Auth::id()
            );
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        Session::forget([
            'stock-import-rows',
            'stock-import-file',
        ]);

        session()->flash('success', 'Stock intake imported successfully!');
        return redirect()->route('filament.tenant.resources.stocks.index');
    }


    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
}
