<div class="p-4  rounded-lg">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h3 class="text-sm font-medium text-gray-500">Name</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $customer->name }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Telephone</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $customer->telephone }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Location</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $customer->location }}</p>
        </div>

    </div>
</div>
