@php
    $preview = $getState();
    $preview = str_replace('{customer_name}', 'John Doe', $preview);
    $preview = str_replace('{contact_name}', 'Jane Smith', $preview);
    $preview = str_replace('{balance}', '1,000.00', $preview);
@endphp

<div class="p-4 rounded-lg">
    <div class="prose max-w-none">
        {{ $preview }}
    </div>
    <div class="mt-2 text-sm text-white">
        <p>Available placeholders:</p>
        <ul class="list-disc list-inside">
            <li>{customer_name} - Customer's name</li>
            <li>{contact_name} - Contact's name (for contacts)</li>
            <li>{balance} - Customer's balance</li>
        </ul>
    </div>
</div>