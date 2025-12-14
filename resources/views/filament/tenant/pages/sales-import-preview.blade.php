<x-filament-panels::page>

    <h2 class="text-xl font-bold mb-4">
        Preview Sales Import
    </h2>

    @if (empty($rows))
        <div class="text-sm text-gray-500 dark:text-gray-400">
            No valid sales rows found.
        </div>
    @else
        <div class="overflow-x-auto rounded-lg shadow-sm
                    bg-white dark:bg-gray-900
                    ring-1 ring-gray-200 dark:ring-gray-800">

            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">

                {{-- Header --}}
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Counter
                        </th>
                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Product
                        </th>
                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Qty
                        </th>
                        <th class="px-4 py-2 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">
                            Notes
                        </th>
                    </tr>
                </thead>

                {{-- Body --}}
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rows as $row)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">

                            {{-- Counter --}}
                            <td class="px-4 py-2 text-sm">
                                <span class="inline-flex items-center rounded-md
                                             bg-gray-100 dark:bg-gray-700
                                             px-2 py-1 text-gray-800 dark:text-gray-200">
                                    {{ $row['counter'] ?? '-' }}
                                </span>
                            </td>

                            {{-- Product --}}
                            <td class="px-4 py-2 text-sm">
                                <span class="inline-flex items-center rounded-md
                                             bg-gray-100 dark:bg-gray-700
                                             px-2 py-1 text-gray-800 dark:text-gray-200">
                                    {{ $row['product'] ?? '-' }}
                                </span>
                            </td>

                            {{-- Quantity --}}
                            <td class="px-4 py-2 text-sm">
                                <span class="inline-flex items-center rounded-md
                                             bg-green-100 dark:bg-green-700/60
                                             px-2 py-1 font-semibold
                                             text-green-800 dark:text-green-100">
                                    {{ $row['quantity'] ?? '-' }}
                                </span>
                            </td>

                            {{-- Notes --}}
                            <td class="px-4 py-2 text-sm">
                                <span class="inline-flex items-center rounded-md
                                             bg-gray-100 dark:bg-gray-700
                                             px-2 py-1 text-gray-700 dark:text-gray-300">
                                    {{ $row['notes'] ?? '-' }}
                                </span>
                            </td>

                        </tr>
                    @endforeach
                </tbody>

            </table>
        </div>

        <x-filament::button
            class="mt-4"
            wire:click="import"
            color="success">
            Confirm Import
        </x-filament::button>
    @endif

</x-filament-panels::page>
