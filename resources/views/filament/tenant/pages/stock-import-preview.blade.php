<x-filament-panels::page>

    <h2 class="text-xl font-bold mb-4">
        {{ $title ?? 'Preview Import' }}
    </h2>

    @php
        // gets first key safely (works even if keys are 5, 20, 99)
        $firstRow = $rows[array_key_first($rows)] ?? [];
    @endphp

    <div class="overflow-hidden rounded-lg shadow bg-white dark:bg-gray-900">
        <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700">

            {{-- Dynamic Header --}}
            <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                <tr>
                    @foreach(array_keys($firstRow) as $col)
                        <th class="px-4 py-2 text-left text-sm font-medium">
                            {{ ucfirst(str_replace('_', ' ', $col)) }}
                        </th>
                    @endforeach
                </tr>
            </thead>

            {{-- Body --}}
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($rows as $row)
                    <tr>
                        @foreach($row as $key => $value)

                            {{-- Quantity should be green badge --}}
                            @if($key === 'quantity')
                                <td class="px-4 py-2 text-sm">
                                    <span class="px-2 py-1 bg-green-200 dark:bg-green-700 rounded">
                                        {{ $value }}
                                    </span>
                                </td>

                            {{-- Normal fields --}}
                            @else
                                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                    <span class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded">
                                        {{ $value ?: '-' }}
                                    </span>
                                </td>
                            @endif

                        @endforeach
                    </tr>
                @endforeach
            </tbody>

        </table>
    </div>

    <x-filament::button
        class="mt-4"
        wire:click="import"
        wire:loading.attr="disabled"
        color="success">
        Confirm Import
    </x-filament::button>




</x-filament-panels::page>
