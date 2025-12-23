<x-filament-panels::page>

    <h2 class="text-xl font-bold mb-2">
        Preview Sales Import
    </h2>

    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Review sales quantities per counter before confirming import.
    </p>

    @php
        $grouped = collect($rows)->groupBy(fn ($r) =>
            ($r['product'] ?? '') . '|' . ($r['sku'] ?? '')
        );

        $counters = collect($rows)
            ->pluck('counter')
            ->unique()
            ->values();
    @endphp

    @if ($grouped->isEmpty())
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No valid sales rows found.
        </div>
    @else
        {{-- FULL-WIDTH CONTAINER --}}
        <div class="w-full overflow-x-auto rounded-xl
                    bg-white dark:bg-gray-900
                    ring-1 ring-gray-200 dark:ring-gray-800">

            <table class="w-full min-w-full divide-y divide-gray-200 dark:divide-gray-800">

                {{-- HEADER --}}
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-sm font-semibold">
                            Product
                        </th>
                        <th class="px-6 py-3 text-left text-sm font-semibold">
                            SKU
                        </th>

                        @foreach($counters as $counter)
                            <th class="px-6 py-3 text-center text-sm font-semibold">
                                {{ ucfirst($counter) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>

                {{-- BODY --}}
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach($grouped as $group)
                        @php
                            $first = $group->first();
                            $byCounter = $group->keyBy('counter');
                        @endphp

                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="px-6 py-4 font-semibold">
                                {{ $first['product'] }}
                            </td>

                            <td class="px-6 py-4 text-gray-500">
                                {{ $first['sku'] ?? '-' }}
                            </td>

                            @foreach($counters as $counter)
                                @php
                                    $qty = $byCounter[$counter]['quantity'] ?? 0;
                                @endphp

                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex min-w-[2.5rem] justify-center
                                        rounded-md px-3 py-1 font-semibold
                                        {{ $qty > 0
                                            ? 'bg-green-100 dark:bg-green-700/60 text-green-800 dark:text-green-100'
                                            : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-300'
                                        }}">
                                        {{ $qty }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>

        {{-- FULL-WIDTH ACTION BUTTON --}}
        <div class="mt-6">
            <x-filament::button
                class="w-full"
                wire:click="import"
                color="success"
                size="lg">
                Confirm Import
            </x-filament::button>
        </div>
    @endif

</x-filament-panels::page>
