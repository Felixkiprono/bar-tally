<x-filament::page>

    <h2 class="text-xl font-bold mb-4">Preview Physical Count Import</h2>

    <table class="w-full text-sm">
        <thead class="bg-gray-700 text-white">
            <tr>
                <th class="px-2 py-1">Counter</th>
                <th class="px-2 py-1">Product</th>
                <th class="px-2 py-1">Closing Count</th>
                <th class="px-2 py-1">Notes</th>
            </tr>
        </thead>

        <tbody>
            @foreach($rows as $row)
                <tr class="border-b border-gray-600">
                    <td class="px-2 py-1">{{ $row['counter'] ?? '-' }}</td>
                    <td class="px-2 py-1">{{ $row['product'] ?? 'Unknown' }}</td>
                    <td class="px-2 py-1">{{ $row['quantity'] ?? '0' }}</td>
                    <td class="px-2 py-1">{{ $row['notes'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <x-filament::button class="mt-4" wire:click="import">
        Import Physical Counts
    </x-filament::button>

</x-filament::page>
