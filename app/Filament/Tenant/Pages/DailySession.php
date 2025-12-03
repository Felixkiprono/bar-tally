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

    public function mount()
    {
        $this->session = DailySessionModel::with(['opener', 'closer'])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereDate('date', today())
            ->first();
    }

    protected function getHeaderActions(): array
    {
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

    public function openDay()
    {
        $tenantId = Auth::user()->tenant_id;

        // Check if there is already a session for today
        $existing = DailySessionModel::where('tenant_id', $tenantId)
            ->whereDate('date', today())
            ->first();

        if ($existing) {
            $this->session = $existing;

            if ($existing->is_open) {
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

        // Optionally: make sure previous session (yesterday) is closed
        $lastSession = DailySessionModel::where('tenant_id', $tenantId)
            ->orderByDesc('date')
            ->first();

        if ($lastSession && $lastSession->is_open) {
            Notification::make()
                ->title('Previous day is still open')
                ->body('Please close the previous session before opening a new day.')
                ->danger()
                ->send();

            $this->session = $lastSession;
            return;
        }

        // Create new session
        $this->session = DailySessionModel::create([
            'tenant_id'     => $tenantId,
            'date'          => today(),
            'opened_by'     => Auth::id(),
            'opening_time'  => now(),
            'is_open'       => true,
        ]);

        // Move opening stock
        $this->moveOpeningStock();

        Notification::make()
            ->title('Day opened')
            ->body('Today\'s session has been opened successfully.')
            ->success()
            ->send();
    }

    public function moveOpeningStock()
    {
        if (!$this->session) return;
        $items = StockMovement::where('tenant_id', Auth::user()->tenant_id)
            ->where('movement_type', 'closing_stock')
            ->get();
        if (!count($items)) {
            session()->flash('error', 'No closing stock found from previous day to move!');
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

        session()->flash('success', 'Opening stock moved successfully!');
    }

    public function closeDay()
    {
        if (!$this->session) return;

        $this->session->update([
            'is_open' => false,
            'closed_by' => Auth::user()->id,
            'closing_time' => now(),
        ]);

        // TODO: Handle closing stock input

        session()->flash('success', 'Day closed successfully!');
    }
}
