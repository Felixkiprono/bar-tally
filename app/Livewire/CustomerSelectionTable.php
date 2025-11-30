<?php

namespace App\Livewire;

use App\Models\User;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\BulkAction;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CustomerSelectionTable extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms, InteractsWithTable, InteractsWithActions;

    // Configuration properties
    public string $context = 'general';
    public array $visibleColumns = ['name', 'email', 'location', 'balance'];
    public array $availableFilters = ['location', 'status', 'balance'];
    public bool $requirePhone = false;
    public bool $requireEmail = false;

    // Multi-filter state
    public array $activeFilters = [];
    public int $filterCounter = 0;

    // Track selected records - this is used by Filament Tables
    public array $selectedTableRecords = [];

    public function mount(
        string $context = 'general',
        array $visibleColumns = ['name', 'email', 'location', 'balance'],
        array $availableFilters = ['location', 'status', 'balance'],
        bool $requirePhone = false,
        bool $requireEmail = false
    ) {
        $this->context = $context;
        $this->visibleColumns = $visibleColumns;
        $this->availableFilters = $availableFilters;
        $this->requirePhone = $requirePhone;
        $this->requireEmail = $requireEmail;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getCustomersQuery())
            ->columns($this->getTableColumns())
            ->filters([])
            ->actions([])
            ->bulkActions([
                // Confirm selected customers
                BulkAction::make('confirmSelection')
                    ->label('Confirm Selected Customers')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Collection $records) {
                        $selectedIds = $records->pluck('id')->toArray();

                        \Log::info('Confirming customer selection', [
                            'count' => count($selectedIds),
                            'ids' => $selectedIds,
                        ]);

                        $this->dispatch('customerSelectionUpdated', customerIds: $selectedIds);

                        Notification::make()
                            ->title('Selection Confirmed')
                            ->body("Successfully confirmed " . count($selectedIds) . " customer(s) for bulk bill creation.")
                            ->success()
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(false)
                    ->requiresConfirmation(false),
            ])
            ->headerActions([
                // Confirm button at the top
                \Filament\Tables\Actions\Action::make('confirmTopSelection')
                    ->label(fn() => count($this->selectedTableRecords) > 0
                        ? 'Confirm ' . count($this->selectedTableRecords) . ' Selected'
                        : 'Confirm Selection'
                    )
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function () {
                        $selectedIds = $this->selectedTableRecords;

                        if (empty($selectedIds)) {
                            Notification::make()
                                ->title('No Selection')
                                ->body('Please select at least one customer first.')
                                ->warning()
                                ->send();
                            return;
                        }

                        \Log::info('Confirming selection from header', [
                            'count' => count($selectedIds),
                            'ids' => $selectedIds,
                        ]);

                        $this->dispatch('customerSelectionUpdated', customerIds: $selectedIds);

                        Notification::make()
                            ->title('Selection Confirmed')
                            ->body("Successfully confirmed " . count($selectedIds) . " customer(s) for bulk bill creation.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn() => !empty($this->selectedTableRecords)),

                // Clear selection button
                \Filament\Tables\Actions\Action::make('clearSelection')
                    ->label('Clear Selection')
                    ->icon('heroicon-o-x-circle')
                    ->color('gray')
                    ->action(function () {
                        $this->selectedTableRecords = [];

                        Notification::make()
                            ->title('Selection Cleared')
                            ->body('All selections have been cleared.')
                            ->success()
                            ->send();
                    })
                    ->visible(fn() => !empty($this->selectedTableRecords)),
            ])
            ->paginated([1, 10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->searchable();
    }

    protected function getCustomersQuery(): Builder
    {
        $query = User::where('role', User::ROLE_CUSTOMER)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->withCount([
                'meterAssignments as total_meters_count',
                'meterAssignments as active_meters_count' => function ($query) {
                    $query->where('is_active', true);
                }
            ]);

        // Apply context-specific filters
        if ($this->requirePhone) {
            $query->whereNotNull('telephone')->where('telephone', '!=', '');
        }

        if ($this->requireEmail) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        // Apply active multi-filters
        $this->applyActiveFilters($query);

        return $query->orderBy('name');
    }

    protected function applyActiveFilters($query): void
    {
        if (empty($this->activeFilters)) {
            return;
        }

        // Group filters by type for OR logic within same type
        $filtersByType = [];
        foreach ($this->activeFilters as $filter) {
            $filtersByType[$filter['type']][] = $filter;
        }

        // Apply each filter type group
        foreach ($filtersByType as $type => $filters) {
            if ($type === 'location') {
                $values = array_column($filters, 'value');
                $query->whereIn('location', $values);
            } elseif ($type === 'status') {
                $values = array_column($filters, 'value');
                $query->whereIn('status', $values);
            } elseif ($type === 'balance_range') {
                // For balance ranges, we need OR logic for each range
                $query->where(function ($subQuery) use ($filters) {
                    foreach ($filters as $filter) {
                        if ($filter['value'] === '>1') {
                            // All Balances - any user with balance > 1
                            $subQuery->orWhere('balance', '>', 1);
                        } else {
                            $parts = explode('-', $filter['value']);
                            if (count($parts) === 2) {
                                $from = (float) $parts[0];
                                $to = (float) $parts[1];
                                $subQuery->orWhereBetween('balance', [$from, $to]);
                            }
                        }
                    }
                });
            }
        }
    }

    protected function getTableColumns(): array
    {
        $columns = [];

        // Always show name
        $columns[] = TextColumn::make('name')
            ->label('Customer Name')
            ->searchable()
            ->sortable()
            ->weight('bold');

        // Conditional columns based on configuration
        if (in_array('email', $this->visibleColumns)) {
            $columns[] = TextColumn::make('email')
                ->label('Email')
                ->searchable()
                ->copyable()
                ->placeholder('Not provided');
        }

        if (in_array('telephone', $this->visibleColumns)) {
            $columns[] = TextColumn::make('telephone')
                ->label('Phone')
                ->searchable()
                ->copyable()
                ->placeholder('Not provided');
        }

        if (in_array('location', $this->visibleColumns)) {
            $columns[] = TextColumn::make('location')
                ->label('Location')
                ->searchable()
                ->sortable()
                ->placeholder('Not specified');
        }

        if (in_array('meter_status', $this->visibleColumns)) {
            $columns[] = TextColumn::make('meter_status')
                ->label('Active Meters')
                ->sortable(query: function ($query, string $direction) {
                    return $query->orderBy('active_meters_count', $direction);
                })
                ->badge()
                ->color(fn($record) =>
                    $record->total_meters_count === 0 ? 'gray' :
                    ($record->active_meters_count === $record->total_meters_count ? 'success' :
                    ($record->active_meters_count > 0 ? 'warning' : 'danger'))
                );
        }

        if (in_array('balance', $this->visibleColumns)) {
            $columns[] = TextColumn::make('balance')
                ->label('Balance')
                ->money('KES')
                ->sortable()
                ->color(fn($state) => $state > 0 ? 'danger' : ($state < 0 ? 'success' : 'gray'));
        }

        if (in_array('overpayment', $this->visibleColumns)) {
            $columns[] = TextColumn::make('overpayment')
                ->label('Overpayment')
                ->money('KES')
                ->sortable()
                ->color(fn($state) => $state > 0 ? 'success' : 'gray');
        }

        if (in_array('status', $this->visibleColumns)) {
            $columns[] = TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->color(fn($state) => match($state) {
                    'active' => 'success',
                    'inactive' => 'danger',
                    'suspended' => 'warning',
                    default => 'gray'
                });
        }

        return $columns;
    }

    // Multi-filter management methods
    public function addFilter(string $type, string $value, ?string $label = null): void
    {
        $filterId = 'filter_' . $this->filterCounter++;

        $this->activeFilters[$filterId] = [
            'id' => $filterId,
            'type' => $type,
            'value' => $value,
            'label' => $label ?? $value,
            'display' => $this->getFilterDisplay($type, $value, $label),
        ];

        // Reset selection when filters change
        $this->selectedTableRecords = [];

        // Refresh table data
        $this->resetTable();
    }

    public function removeFilter(string $filterId): void
    {
        unset($this->activeFilters[$filterId]);

        // Reset selection when filters change
        $this->selectedTableRecords = [];

        $this->resetTable();

        Notification::make()
            ->title('Filter Removed')
            ->success()
            ->send();
    }

    public function clearAllFilters(): void
    {
        $this->activeFilters = [];

        // Reset selection when filters change
        $this->selectedTableRecords = [];

        $this->resetTable();

        Notification::make()
            ->title('All Filters Cleared')
            ->success()
            ->send();
    }

    protected function getFilterDisplay(string $type, string $value, ?string $label = null): string
    {
        return match($type) {
            'location' => "Location: " . ($label ?? $value),
            'status' => "Status: " . ucfirst($label ?? $value),
            'balance_range' => "Balance: " . ($label ?? $value),
            default => ucfirst($type) . ": " . ($label ?? $value)
        };
    }

    // Filament Actions
    public function addFiltersAction(): Action
    {
        return Action::make('addFilters')
            ->label('Add Filters')
            ->icon('heroicon-o-funnel')
            ->color('primary')
            ->modal()
            ->modalHeading('Add Filters')
            ->modalDescription('Select filters to narrow down your customer list')
            ->modalWidth('2xl')
            ->modalSubmitActionLabel('Add')
            ->modalCancelActionLabel('Cancel')
            ->form($this->getFilterFormSchema())
            ->action(function (array $data) {
                $this->processFilterSelections($data);
            });
    }

    protected function getFilterFormSchema(): array
    {
        $schema = [];

        if (in_array('location', $this->availableFilters)) {
            $schema[] = Section::make('Location Filters')
                ->description('Filter customers by their location')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    CheckboxList::make('locations')
                        ->label('')
                        ->options($this->getLocationOptions())
                        ->columns(3)
                        ->gridDirection('row'),
                ]);
        }

        if (in_array('status', $this->availableFilters)) {
            $schema[] = Section::make('Status Filters')
                ->description('Filter customers by their account status')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    CheckboxList::make('statuses')
                        ->label('')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'suspended' => 'Suspended',
                        ])
                        ->columns(3)
                        ->gridDirection('row'),
                ]);
        }

        if (in_array('balance', $this->availableFilters)) {
            $schema[] = Section::make('Balance Range Filters')
                ->description('Filter customers by their account balance')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    CheckboxList::make('balance_ranges')
                        ->label('')
                        ->options([
                            '>1' => 'All Balances',
                            '0-1000' => 'KES 0 - 1,000 (Low)',
                            '1000-5000' => 'KES 1,000 - 5,000 (Medium)',
                            '5000-10000' => 'KES 5,000 - 10,000 (High)',
                            '10000-999999' => 'KES 10,000+ (Very High)',
                        ])
                        ->columns(2)
                        ->gridDirection('row'),
                ]);
        }

        return $schema;
    }

    protected function getLocationOptions(): array
    {
        return User::where('role', User::ROLE_CUSTOMER)
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->pluck('location', 'location')
            ->toArray();
    }

    protected function processFilterSelections(array $data): void
    {
        // Process location filters
        if (!empty($data['locations'])) {
            foreach ($data['locations'] as $location) {
                $this->addFilter('location', $location);
            }
        }

        // Process status filters
        if (!empty($data['statuses'])) {
            foreach ($data['statuses'] as $status) {
                $this->addFilter('status', $status, ucfirst($status));
            }
        }

        // Process balance range filters
        if (!empty($data['balance_ranges'])) {
            foreach ($data['balance_ranges'] as $range) {
                $label = match($range) {
                    '>1' => 'All Balances',
                    '0-1000' => 'KES 0 - 1,000',
                    '1000-5000' => 'KES 1,000 - 5,000',
                    '5000-10000' => 'KES 5,000 - 10,000',
                    '10000-999999' => 'KES 10,000+',
                    default => $range
                };
                $this->addFilter('balance_range', $range, $label);
            }
        }

        Notification::make()
            ->title('Filters Applied')
            ->body('Selected filters have been applied to the customer list')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.customer-selection-table');
    }
}
