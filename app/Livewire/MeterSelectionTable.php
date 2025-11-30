<?php

namespace App\Livewire;

use App\Models\MeterAssignment;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Action;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class MeterSelectionTable extends Component implements HasForms, HasTable, HasActions
{
    use InteractsWithForms, InteractsWithTable, InteractsWithActions;

    // Multi-filter state
    public array $activeFilters = [];
    public int $filterCounter = 0;

    // Track selected records - this is used by Filament Tables
    public array $selectedTableRecords = [];

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getMetersQuery())
            ->columns($this->getTableColumns())
            ->filters([])
            ->actions([])
            ->bulkActions([
                // Confirm selected meters
                BulkAction::make('confirmSelection')
                    ->label('Confirm Selected Meters')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->action(function (Collection $records) {
                        // Get meter IDs from the assignments
                        $selectedMeterIds = $records->pluck('meter_id')->unique()->toArray();

                        \Log::info('Confirming meter selection', [
                            'count' => count($selectedMeterIds),
                            'meter_ids' => $selectedMeterIds,
                        ]);

                        $this->dispatch('meterSelectionUpdated', meterIds: $selectedMeterIds);

                        Notification::make()
                            ->title('Selection Confirmed')
                            ->body("Successfully confirmed " . count($selectedMeterIds) . " meter(s) for bulk SMS.")
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
                        if (empty($this->selectedTableRecords)) {
                            Notification::make()
                                ->title('No Selection')
                                ->body('Please select at least one meter first.')
                                ->warning()
                                ->send();
                            return;
                        }

                        // Get meter IDs from selected assignment IDs
                        $selectedMeterIds = MeterAssignment::whereIn('id', $this->selectedTableRecords)
                            ->pluck('meter_id')
                            ->unique()
                            ->toArray();

                        \Log::info('Confirming selection from header', [
                            'count' => count($selectedMeterIds),
                            'meter_ids' => $selectedMeterIds,
                        ]);

                        $this->dispatch('meterSelectionUpdated', meterIds: $selectedMeterIds);

                        Notification::make()
                            ->title('Selection Confirmed')
                            ->body("Successfully confirmed " . count($selectedMeterIds) . " meter(s) for bulk SMS.")
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
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->searchable();
    }

    protected function getMetersQuery(): Builder
    {
        $query = MeterAssignment::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->where('is_active', true)
            ->with(['customer', 'meter']);

        // Apply active filters
        $this->applyActiveFilters($query);

        return $query->orderBy('meter_id', 'asc');
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
                $query->whereHas('meter', function ($q) use ($values) {
                    $q->whereIn('location', $values);
                });
            } elseif ($type === 'status') {
                $values = array_column($filters, 'value');
                $query->whereHas('meter', function ($q) use ($values) {
                    $q->whereIn('status', $values);
                });
            } elseif ($type === 'balance_range') {
                // For balance ranges
                $query->whereHas('meter', function ($meterQuery) use ($filters) {
                    $meterQuery->where(function ($subQuery) use ($filters) {
                        foreach ($filters as $filter) {
                            $parts = explode('-', $filter['value']);
                            if (count($parts) === 2) {
                                $from = (float) $parts[0];
                                $to = (float) $parts[1];
                                $subQuery->orWhereBetween('balance', [$from, $to]);
                            }
                        }
                    });
                });
            } elseif ($type === 'overpayment_range') {
                // For overpayment ranges
                $query->whereHas('meter', function ($meterQuery) use ($filters) {
                    $meterQuery->where(function ($subQuery) use ($filters) {
                        foreach ($filters as $filter) {
                            $parts = explode('-', $filter['value']);
                            if (count($parts) === 2) {
                                $from = (float) $parts[0];
                                $to = (float) $parts[1];
                                $subQuery->orWhereBetween('overpayment', [$from, $to]);
                            }
                        }
                    });
                });
            }
        }
    }

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
        if ($label) {
            return $label;
        }

        return match($type) {
            'location' => "Location: {$value}",
            'status' => "Status: {$value}",
            'balance_range' => "Balance: KES {$value}",
            'overpayment_range' => "Overpayment: KES {$value}",
            default => $value,
        };
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('customer.name')
                ->label('Customer')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            TextColumn::make('meter.meter_number')
                ->label('Meter Number')
                ->searchable()
                ->sortable()
                ->weight('medium'),

            TextColumn::make('meter.location')
                ->label('Location')
                ->searchable()
                ->default('N/A'),

            TextColumn::make('meter.balance')
                ->label('Balance')
                ->money('KES')
                ->sortable()
                ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),

            TextColumn::make('meter.overpayment')
                ->label('Credit')
                ->money('KES')
                ->sortable()
                ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

            TextColumn::make('meter.status')
                ->label('Status')
                ->badge()
                ->color(fn (string $state): string => match ($state) {
                    'active' => 'success',
                    'inactive' => 'danger',
                    default => 'gray',
                }),
        ];
    }

    public function addFiltersAction(): Action
    {
        return Action::make('addFilters')
            ->label('Add Filters')
            ->icon('heroicon-o-funnel')
            ->color('primary')
            ->modal()
            ->modalHeading('Add Filters')
            ->modalDescription('Select filters to narrow down your meter list')
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
        return [
            Section::make('Location Filters')
                ->description('Filter meters by location')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    CheckboxList::make('locations')
                        ->label('')
                        ->options($this->getLocationOptions())
                        ->columns(3)
                        ->gridDirection('row'),
                ]),

            Section::make('Status Filters')
                ->description('Filter meters by status')
                ->icon('heroicon-o-shield-check')
                ->schema([
                    CheckboxList::make('statuses')
                        ->label('')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                        ])
                        ->columns(2)
                        ->gridDirection('row'),
                ]),

            Section::make('Balance Range Filter')
                ->description('Filter meters by balance range')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('balance_from')
                                ->label('Balance From')
                                ->numeric()
                                ->prefix('KES')
                                ->placeholder('0')
                                ->default(0),
                            
                            TextInput::make('balance_to')
                                ->label('Balance To')
                                ->numeric()
                                ->prefix('KES')
                                ->placeholder('Any')
                                ->helperText('Leave empty for no upper limit'),
                        ]),
                ]),
            
            Section::make('Overpayment Range Filter')
                ->description('Filter meters by overpayment/credit range')
                ->icon('heroicon-o-currency-dollar')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            TextInput::make('overpayment_from')
                                ->label('Overpayment From')
                                ->numeric()
                                ->prefix('KES')
                                ->placeholder('0')
                                ->default(0),
                            
                            TextInput::make('overpayment_to')
                                ->label('Overpayment To')
                                ->numeric()
                                ->prefix('KES')
                                ->placeholder('Any')
                                ->helperText('Leave empty for no upper limit'),
                        ]),
                ]),
        ];
    }

    protected function getLocationOptions(): array
    {
        return \App\Models\Meter::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->pluck('location', 'location')
            ->toArray();
    }

    protected function processFilterSelections(array $data): void
    {
        // Add location filters
        if (!empty($data['locations'])) {
            foreach ($data['locations'] as $location) {
                $this->addFilter('location', $location);
            }
        }

        // Add status filters
        if (!empty($data['statuses'])) {
            foreach ($data['statuses'] as $status) {
                $this->addFilter('status', $status, ucfirst($status));
            }
        }

        // Add balance range filter
        if (isset($data['balance_from']) || isset($data['balance_to'])) {
            $from = $data['balance_from'] ?? 0;
            $to = $data['balance_to'] ?? null;
            
            if ($to !== null) {
                $label = "Balance: KES " . number_format($from, 0) . " - " . number_format($to, 0);
                $value = "{$from}-{$to}";
            } else {
                $label = "Balance: KES " . number_format($from, 0) . "+";
                $value = "{$from}-999999999";
            }
            
            $this->addFilter('balance_range', $value, $label);
        }

        // Add overpayment range filter
        if (isset($data['overpayment_from']) || isset($data['overpayment_to'])) {
            $from = $data['overpayment_from'] ?? 0;
            $to = $data['overpayment_to'] ?? null;
            
            if ($to !== null) {
                $label = "Overpayment: KES " . number_format($from, 0) . " - " . number_format($to, 0);
                $value = "{$from}-{$to}";
            } else {
                $label = "Overpayment: KES " . number_format($from, 0) . "+";
                $value = "{$from}-999999999";
            }
            
            $this->addFilter('overpayment_range', $value, $label);
        }

        Notification::make()
            ->title('Filters Applied')
            ->body('Your filter selections have been applied to the meter list.')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.meter-selection-table');
    }
}

