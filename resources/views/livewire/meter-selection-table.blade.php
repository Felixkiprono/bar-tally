<div class="space-y-6">
    {{-- Always Visible Filters Section with Filament Components --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-funnel class="w-5 h-5 text-primary-500" />
                <span>Meter Filters</span>
                @if(!empty($activeFilters))
                    <x-filament::badge color="primary">
                        {{ count($activeFilters) }}
                    </x-filament::badge>
                @endif
            </div>
        </x-slot>

        <x-slot name="headerActions">
            <div class="flex items-center gap-3">
                @if(!empty($activeFilters))
                    <x-filament::button
                        wire:click="clearAllFilters"
                        color="danger"
                        size="sm"
                        outlined
                        icon="heroicon-o-trash"
                    >
                        Clear All
                    </x-filament::button>
                @endif
                
                {{ $this->addFiltersAction }}
            </div>
        </x-slot>

        <x-slot name="description">
            @if(empty($activeFilters))
                Use the "Add Filters" button to narrow down the meter list. Select meters using table checkboxes.
            @else
                Showing meters matching {{ count($activeFilters) > 1 ? 'all' : 'the' }} selected filter{{ count($activeFilters) > 1 ? 's' : '' }}.
            @endif
        </x-slot>

        @if(!empty($activeFilters))
            {{-- Active Filter Chips with Filament Styling --}}
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="flex flex-wrap gap-3">
                    @foreach($activeFilters as $filter)
                        <x-filament::badge 
                            :color="match($filter['type']) {
                                'location' => 'info',
                                'status' => 'success', 
                                'balance_range' => 'warning',
                                'overpayment_range' => 'primary',
                                default => 'gray'
                            }"
                            size="md"
                        >
                            <div class="flex items-center space-x-1">
                                @if($filter['type'] === 'location')
                                    <x-heroicon-o-map-pin class="w-3 h-3" />
                                @elseif($filter['type'] === 'status')
                                    <x-heroicon-o-shield-check class="w-3 h-3" />
                                @elseif($filter['type'] === 'overpayment_range')
                                    <x-heroicon-o-currency-dollar class="w-3 h-3" />
                                @else
                                    <x-heroicon-o-banknotes class="w-3 h-3" />
                                @endif
                                <span>{{ $filter['display'] }}</span>
                                <button 
                                    wire:click="removeFilter('{{ $filter['id'] }}')" 
                                    type="button" 
                                    class="ml-1 hover:bg-white/20 rounded-full p-0.5 transition-colors"
                                >
                                    <x-heroicon-o-x-mark class="w-3 h-3" />
                                </button>
                            </div>
                        </x-filament::badge>
                    @endforeach
                </div>
            </div>
        @else
            {{-- Empty State --}}
            <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                <div class="text-center py-8">
                    <x-heroicon-o-funnel class="w-8 h-8 text-gray-400 mx-auto mb-3" />
                    <x-filament::section.description>
                        No filters applied. All active meters are shown in the table below.
                    </x-filament::section.description>
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- Enhanced Meter Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-1">
            {{ $this->table }}
        </div>
    </div>

    {{-- Filament Action Modals --}}
    <x-filament-actions::modals />
</div>
