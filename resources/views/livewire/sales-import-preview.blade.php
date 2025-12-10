<div class="p-4">

    <input type="file" wire:model="file" class="mb-4">

    @if($readyToPreview)
        <h3 class="text-lg font-bold mb-3">Preview Import</h3>

        <table class="w-full text-left border">
            <thead class="bg-gray-800 text-white">
                <tr>
                    <th class="p-2">Counter</th>
                    <th class="p-2">Product</th>
                    <th class="p-2">Quantity</th>
                    <th class="p-2">Notes</th>
                </tr>
            </thead>

            <tbody>
                @foreach($rows as $r)
                    <tr class="border">
                        <td class="p-2">{{ $r['counter'] }}</td>
                        <td class="p-2">{{ $r['product'] }}</td>
                        <td class="p-2">{{ $r['quantity'] }}</td>
                        <td class="p-2">{{ $r['notes'] ?? '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 flex gap-2">
            <button wire:click="importNow" class="px-4 py-2 bg-green-600 text-white rounded">
                Import Now
            </button>
        </div>
    @endif
</div>
