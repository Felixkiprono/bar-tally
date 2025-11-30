<?php
/**
 * Warning modal content for generating invoices
 */
?>
<div class="space-y-4">
    <div class="p-4 bg-warning-50 border-2 border-warning-500 rounded-lg">
        <p class="font-bold text-warning-700 text-lg">⚠️ WARNING: This action cannot be undone!</p>
        <p class="mt-2 text-warning-600 font-medium">This will:</p>
        <ul class="list-disc ml-4 mt-2 space-y-1 text-warning-700">
            <li>Convert ALL pending bills into invoices</li>
            <li>DEBIT the customer accounts for the full amount</li>
            <li>Create journal entries for the transactions</li>
            <li>Mark all processed bills as "invoiced"</li>
        </ul>
    </div>
    <p class="text-gray-600">Please ensure you have reviewed all pending bills before proceeding.</p>
</div>