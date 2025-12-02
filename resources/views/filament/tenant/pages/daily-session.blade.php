<x-filament-panels::page>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- STATUS CARD --}}
        <x-filament::section heading="Today's Status">
            <div class="space-y-3">

                <p class="text-lg">
                    Status:
                    @if ($this->session && $this->session->is_open)
                        <span class="px-3 py-1 text-green-600 bg-green-100 rounded-full">OPEN</span>
                    @else
                        <span class="px-3 py-1 text-red-600 bg-red-100 rounded-full">CLOSED</span>
                    @endif
                </p>

                @if ($this->session)
                    <p><strong>Opened By:</strong> {{ $this->session->opener()?->name ?? '-' }}</p>
                    <p><strong>Opened At:</strong> {{ $this->session->opening_time?->format('d M Y, H:i') ?? '-' }}</p>
                    <p><strong>Closed By:</strong> {{ $this->session->closer()?->name ?? '-' }}</p>
                    <p><strong>Closed At:</strong> {{ $this->session->closing_time?->format('d M Y, H:i') ?? '-' }}</p>
                @else
                    <p>No session has been started today.</p>
                @endif

            </div>
        </x-filament::section>

        {{-- INFO CARD --}}
        <x-filament::section heading="Instructions">
            <p class="text-gray-200">
                <strong>Open Day:</strong> Starts stock tracking for today.<br>
                <strong>Close Day:</strong> Collects closing counts and locks the day.<br>
            </p>
        </x-filament::section>

    </div>

</x-filament-panels::page>

