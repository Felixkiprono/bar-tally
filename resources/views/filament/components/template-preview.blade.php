<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Preview with Sample Data:
        </h3>
        <p class="text-gray-900 dark:text-gray-100 whitespace-pre-wrap">
            {{ $preview }}
        </p>
    </div>

    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg p-4">
        <h3 class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-2">
            Template Details:
        </h3>
        <dl class="space-y-2">
            <div class="flex justify-between">
                <dt class="text-sm text-gray-600 dark:text-gray-400">Context:</dt>
                <dd class="text-sm font-medium">{{ ucfirst($template->context) }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-600 dark:text-gray-400">Category:</dt>
                <dd class="text-sm font-medium">{{ $template->category }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-600 dark:text-gray-400">Character Count:</dt>
                <dd class="text-sm font-medium">{{ strlen($preview) }} characters</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-sm text-gray-600 dark:text-gray-400">SMS Count:</dt>
                <dd class="text-sm font-medium">{{ ceil(strlen($preview) / 160) }} SMS</dd>
            </div>
        </dl>
    </div>

    @if($template->available_tags)
    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
        <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
            Available Tags:
        </h3>
        <div class="flex flex-wrap gap-2">
            @foreach($template->available_tags as $tag)
                <span class="px-2 py-1 bg-gray-200 dark:bg-gray-700 rounded text-xs">
                    {{ $tag }}
                </span>
            @endforeach
        </div>
    </div>
    @endif
</div>

