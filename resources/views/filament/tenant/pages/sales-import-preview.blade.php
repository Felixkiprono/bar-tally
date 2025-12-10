<x-filament-panels::page>

    <h2 class="text-xl font-bold mb-4">Preview Sales Import</h2>

    <table class="w-full border bg-white">
        <thead class="bg-gray-100 font-bold">
            <tr>
                <th class="p-2">Counter</th>
                <th class="p-2">Product</th>
                <th class="p-2">Qty</th>
                <th class="p-2">Notes</th>
            </tr>
        </thead>

      <table class="min-w-full divide-y divide-gray-300 dark:divide-gray-700 rounded-lg overflow-hidden shadow-sm">

    <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
        <tr>
            <th class="px-4 py-2 text-left text-sm font-medium">Counter</th>
            <th class="px-4 py-2 text-left text-sm font-medium">Product</th>
            <th class="px-4 py-2 text-left text-sm font-medium">Qty</th>
            <th class="px-4 py-2 text-left text-sm font-medium">Notes</th>
        </tr>
    </thead>

    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
        @foreach($rows as $row)
            <tr>
                {{-- Counter --}}
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <span class="px-2 py-1 rounded bg-gray-200 dark:bg-gray-700">
                        {{ $row['counter'] ?? '-' }}
                    </span>
                </td>

                {{-- Product --}}
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <span class="px-2 py-1 rounded bg-gray-200 dark:bg-gray-700">
                        {{ $row['product'] ?? '-' }}
                    </span>
                </td>

                {{-- Quantity --}}
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <span class="px-2 py-1 rounded bg-green-200 dark:bg-green-700">
                        {{ $row['quantity'] ?? '-' }}
                    </span>
                </td>

                {{-- Notes --}}
                <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                    <span class="px-2 py-1 rounded bg-gray-200 dark:bg-gray-700">
                        {{ $row['notes'] ?? '-' }}
                    </span>
                </td>
            </tr>
        @endforeach
    </tbody>

    </table>

    <x-filament::button
        class="mt-4"
        wire:click="import"
        color="success">
        Confirm Import
    </x-filament::button>

</x-filament-panels::page>
