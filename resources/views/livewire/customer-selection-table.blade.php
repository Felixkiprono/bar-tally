<div class="space-y-6">
    

    {{-- Always Visible Filters Section with Filament Components --}}
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center space-x-2">
                <x-heroicon-o-funnel class="w-5 h-5 text-primary-500" />
                <span>Customer Filters</span>
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
                Use the "Add Filters" button to narrow down the customer list. Select customers using table checkboxes.
            @else
                Showing customers matching {{ count($activeFilters) > 1 ? 'all' : 'the' }} selected filter{{ count($activeFilters) > 1 ? 's' : '' }}.
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
                                default => 'gray'
                            }"
                            size="md"
                        >
                            <div class="flex items-center space-x-1">
                                @if($filter['type'] === 'location')
                                    <x-heroicon-o-map-pin class="w-3 h-3" />
                                @elseif($filter['type'] === 'status')
                                    <x-heroicon-o-shield-check class="w-3 h-3" />
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
                        No filters applied. All customers are shown in the table below.
                    </x-filament::section.description>
                </div>
            </div>
        @endif
    </x-filament::section>

    {{-- Enhanced Customer Table --}}
    <div class="bg-white dark:bg-gray-900 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-1">
            {{ $this->table }}
        </div>
    </div>



    {{-- Filament Action Modals --}}
    <x-filament-actions::modals />
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('CustomerSelectionTable script loaded');
    
    // Function to get current selections and emit update
    function emitCurrentSelections() {
        // Get all checked checkboxes in the table (more specific selector)
        const tableContainer = document.querySelector('[wire\\:id="{{ $this->getId() }}"]');
        if (!tableContainer) {
            console.log('Table container not found');
            return;
        }
        
        const checkedBoxes = tableContainer.querySelectorAll('input[type="checkbox"]:checked');
        const selectedIds = [];
        
        checkedBoxes.forEach(function(checkbox) {
            const value = checkbox.value;
            console.log('Found checked checkbox with value:', value);
            
            // Skip the "select all" checkbox and only get actual record IDs
            if (value && value !== '' && value !== 'on' && !isNaN(value)) {
                selectedIds.push(parseInt(value));
            }
        });
        
        console.log('Emitting selection update:', selectedIds);
        
        // Emit the selection update
        @this.dispatch('customerSelectionUpdated', selectedIds);
    }
    
    // Use event delegation to catch checkbox changes
    document.addEventListener('change', function(e) {
        if (e.target.type === 'checkbox' && e.target.closest('[wire\\:id="{{ $this->getId() }}"]')) {
            console.log('Checkbox change detected:', e.target.value, e.target.checked);
            setTimeout(emitCurrentSelections, 50);
        }
    });
    
    // Also listen for click events as a backup
    document.addEventListener('click', function(e) {
        if (e.target.type === 'checkbox' && e.target.closest('[wire\\:id="{{ $this->getId() }}"]')) {
            console.log('Checkbox click detected:', e.target.value, e.target.checked);
            setTimeout(emitCurrentSelections, 50);
        }
    });
    
    // Initial check
    setTimeout(function() {
        console.log('Performing initial selection check');
        emitCurrentSelections();
    }, 1000);
});
</script>
