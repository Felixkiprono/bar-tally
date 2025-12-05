<?php

namespace App\Filament\Tenant\Pages;

use App\Constants\Stock;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Filament\Pages\Actions\Action;
use App\Models\DailySession as DailySessionModel;
use App\Models\StockMovement;
use Filament\Notifications\Notification;
use App\Constants\StockMovementType;

class DailySession extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-document-text';
    protected static string $view = 'filament.tenant.pages.daily-session';

    protected static ?string $navigationGroup = 'Daily Operations';
    protected static ?string $navigationLabel = 'Daily Session';

    public ?DailySessionModel $session = null;
    public ?DailySessionModel $unfinishedDay = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (!$user) return false;

        return $user->isManager() || $user->isTenantAdmin() || $user->isAdmin();
    }
    public function mount()
    {
        $this->session = DailySessionModel::with(['opener', 'closer'])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereDate('date', today())
            ->first();
        // Find ANY previous open session
        $this->unfinishedDay = DailySessionModel::where('tenant_id', Auth::user()->tenant_id)
            ->where('is_open', true)
            ->whereDate('date', '<', today())
            ->orderBy('date')
            ->first();
    }


    protected function getHeaderActions(): array
    {
        if ($this->unfinishedDay) {
            return [
                Action::make('closePreviousDay')
                    ->label("Close Previous Day ({$this->unfinishedDay->date->format('d M Y')})")
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn() => $this->closePreviousDay())
                    ->icon('heroicon-s-x-circle'),
            ];
        }
        // No open session → show OPEN DAY button
        if (!$this->session || !$this->session->is_open) {
            return [
                Action::make('openDay')
                    ->label('Open Day')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(fn() => $this->openDay())
                    ->icon('heroicon-s-play'),
            ];
        }

        // Session open → show CLOSE DAY button
        return [
            Action::make('closeDay')
                ->label('Close Day')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn() => $this->closeDay())
                ->icon('heroicon-s-stop'),
        ];
    }
    public function closePreviousDay()
    {
        if (!$this->unfinishedDay) return;

        $this->unfinishedDay->update([
            'is_open' => false,
            'closed_by' => Auth::id(),
            'closing_time' => now(),
        ]);

        Notification::make()
            ->title("Previous Day Closed")
            ->body("Successfully closed session for {$this->unfinishedDay->date->format('d M Y')}.")
            ->success()
            ->send();

        $this->mount(); // Reload state
    }

    public function openDay()
    {
        $tenantId = Auth::user()->tenant_id;

        /*
    |--------------------------------------------------------------------------
    | 1. Check if today's session already exists
    |--------------------------------------------------------------------------
    */
        $existingToday = DailySessionModel::where('tenant_id', $tenantId)
            ->whereDate('date', today())
            ->first();

        if ($existingToday) {
            $this->session = $existingToday;

            if ($existingToday->is_open) {
                Notification::make()
                    ->title('Day is already open')
                    ->body('You already opened today\'s session. You cannot open it twice.')
                    ->danger()
                    ->send();
            } else {
                Notification::make()
                    ->title('Day already closed')
                    ->body('Today\'s session was already closed. You cannot reopen it.')
                    ->danger()
                    ->send();
            }
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | 2. Check if ANY previous day is still open
    |--------------------------------------------------------------------------
    */
        $unfinishedDay = DailySessionModel::where('tenant_id', $tenantId)
            ->where('is_open', true)
            ->whereDate('date', '<', today())  // ONLY previous days
            ->orderBy('date')
            ->first();

        if ($unfinishedDay) {
            Notification::make()
                ->title('Previous Session Still Open')
                ->body(
                    "The session for <b>{$unfinishedDay->date->format('d M Y')}</b> is still open.
                Please close it before opening a new day."
                )
                ->danger()
                ->send();

            $this->session = $unfinishedDay;
            return;
        }

        /*
    |--------------------------------------------------------------------------
    | 3. No conflicts → Open today's day
    |--------------------------------------------------------------------------
    */
        $this->session = DailySessionModel::create([
            'tenant_id'     => $tenantId,
            'date'          => today(),
            'opened_by'     => Auth::id(),
            'opening_time'  => now(),
            'is_open'       => true,
        ]);

        // Move opening stock from the most recent closed day
        $this->moveOpeningStock();

        Notification::make()
            ->title('Day Opened Successfully')
            ->body('Today\'s session has been opened.')
            ->success()
            ->send();
    }

    // public function moveOpeningStock()
    // {
    //     if (!$this->session) return;
    //     $items = StockMovement::where('tenant_id', Auth::user()->tenant_id)
    //         ->where('movement_type', 'closing_stock')
    //         ->get();
    //     if (!count($items)) {
    //         session()->flash('error', 'No closing stock found from previous day to move!');
    //         return;
    //     }
    //     foreach ($items as $item) {
    //         StockMovement::create([
    //             'tenant_id'     => Auth::user()->tenant_id,
    //             'item_id'       => $item->item_id,
    //             'counter_id'    => $item->counter_id,
    //             'quantity'      => $item->quantity,
    //             'movement_type' => StockMovementType::OPENING,
    //             'movement_date' => today(),
    //             'session_id'    => $this->session->id,
    //             'created_by'    => Auth::id(),
    //         ]);
    //     }

    //     session()->flash('success', 'Opening stock moved successfully!');
    // }

    public function moveOpeningStock()
    {
        if (!$this->session) return;

        $yesterday = today()->subDay();

        $items = StockMovement::where('tenant_id', Auth::user()->tenant_id)
            ->where('movement_type', StockMovementType::CLOSING)
            ->whereDate('movement_date', $yesterday)
            ->get();

        if ($items->isEmpty()) {
            Notification::make()
                ->title('No Closing Stock Found')
                ->body("No closing stock was found for {$yesterday->format('d M Y')}.")
                ->warning()
                ->send();

            return;
        }

        foreach ($items as $item) {
            StockMovement::create([
                'tenant_id'     => Auth::user()->tenant_id,
                'item_id'       => $item->item_id,
                'counter_id'    => $item->counter_id,
                'quantity'      => $item->quantity,
                'movement_type' => StockMovementType::OPENING,
                'movement_date' => today(),
                'session_id'    => $this->session->id,
                'created_by'    => Auth::id(),
            ]);
        }

        Notification::make()
            ->title('Opening Stock Moved')
            ->body('Opening stock has been moved from yesterday.')
            ->success()
            ->send();
    }
}
