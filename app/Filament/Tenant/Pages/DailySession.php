<?php

namespace App\Filament\Tenant\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Filament\Pages\Actions\Action;
use App\Models\DailySession as DailySessionModel;
use Filament\Notifications\Notification;

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

        Notification::make()
            ->title('Day opened')
            ->body('Today\'s session has been opened successfully.')
            ->success()
            ->send();
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
