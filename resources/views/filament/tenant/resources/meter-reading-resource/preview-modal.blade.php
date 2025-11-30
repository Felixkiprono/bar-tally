<div class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800 dark:text-green-200">
                        Valid Rows
                    </p>
                    <p class="text-2xl font-bold text-green-900 dark:text-green-100">
                        {{ count($validRows) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800 dark:text-red-200">
                        Invalid Rows
                    </p>
                    <p class="text-2xl font-bold text-red-900 dark:text-red-100">
                        {{ count($invalidRows) }}
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
            <div class="flex items-center">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-blue-800 dark:text-blue-200">
                        Total Rows
                    </p>
                    <p class="text-2xl font-bold text-blue-900 dark:text-blue-100">
                        {{ count($validRows) + count($invalidRows) }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    @if(count($invalidRows) > 0)
        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
            <h4 class="text-md font-medium text-red-800 dark:text-red-200 mb-2">
                Invalid Rows ({{ count($invalidRows) }})
            </h4>
            <div class="space-y-2 max-h-40 overflow-y-auto">
                @foreach($invalidRows as $row)
                    <div class="text-sm">
                        <span class="font-medium">Row {{ $row['row'] }}:</span>
                        <span class="text-red-600 dark:text-red-400">{{ implode(', ', $row['errors']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(count($validRows) > 0)
        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
            <h4 class="text-md font-medium text-green-800 dark:text-green-200 mb-2">
                Valid Rows ({{ count($validRows) }})
            </h4>
            <div class="space-y-2 max-h-40 overflow-y-auto">
                @foreach($validRows as $row)
                    <div class="text-sm">
                        <span class="font-medium">Row {{ $row['row'] }}:</span>
                        <span class="text-green-600 dark:text-green-400">
                            Meter {{ $row['data']['meter_number'] }} -
                            Reading {{ $row['data']['reading_value'] }} on {{ $row['data']['reading_date'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="text-sm text-gray-600 dark:text-gray-400">
        <p><strong>Note:</strong> Only valid rows will be imported. Invalid rows will be skipped.</p>
        @if(count($invalidRows) > 0)
            <p class="text-red-600 dark:text-red-400 mt-2">
                Please fix the invalid rows in your file and try importing again.
            </p>
        @endif
    </div>
</div>
