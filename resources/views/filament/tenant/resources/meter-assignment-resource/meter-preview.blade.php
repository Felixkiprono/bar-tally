<div class="p-4  rounded-lg">
    <div class="grid grid-cols-2 gap-4">
        <div>
            <h3 class="text-sm font-medium text-gray-500">Meter Number</h3>
            <p class="mt-1 text-lg font-bold text-gray-900">{{ $meter->meter_number }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Serial Number</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $meter->serial_number }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Type</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $meter->meter_type }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Installation Date</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $meter->installation_date?->format('Y-m-d') ?? 'Not set' }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Current Assignment</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $meter->currentCustomer()?->name ?? 'Not assigned to any customer' }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Meter Balance</h3>
            <p class="mt-1 text-sm font-semibold text-gray-900">KES {{ number_format((float) ($meter->balance ?? 0), 2) }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Meter Credit / Overpayment</h3>
            <p class="mt-1 text-sm font-semibold text-gray-900">KES {{ number_format((float) ($meter->overpayment ?? 0), 2) }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Last Reading Date</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $meter->last_reading_date }}</p>
        </div>
        <div>
            <h3 class="text-sm font-medium text-gray-500">Last Reading Reading</h3>
            <p class="mt-1 text-sm text-gray-900">{{ $meter->last_reading }}</p>
        </div>


    </div>
</div>
