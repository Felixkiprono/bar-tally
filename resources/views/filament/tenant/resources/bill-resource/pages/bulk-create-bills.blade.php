<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}
        
        <x-filament::actions :actions="$this->getFormActions()" />
    </div>
</x-filament-panels::page>
