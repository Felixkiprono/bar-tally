<x-filament-panels::page>
    <div class="space-y-6">
        <div class="p-4 bg-white rounded-lg shadow">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-2xl font-bold">Customer Statement</h2>
                <div class="flex space-x-2">
                    <button type="button" class="filament-button inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700" onclick="window.print()">
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0110.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0l.229 2.523a1.125 1.125 0 01-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0021 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 00-1.913-.247M6.34 18H5.25A2.25 2.25 0 013 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 011.913-.247m10.5 0a48.536 48.536 0 00-10.5 0m10.5 0V3.375c0-.621-.504-1.125-1.125-1.125h-8.25c-.621 0-1.125.504-1.125 1.125v3.659M18 10.5h.008v.008H18V10.5zm-3 0h.008v.008H15V10.5z" />
                        </svg>
                        Print Statement
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-6">
                <div>
                    <h3 class="text-lg font-semibold">Customer Details</h3>
                    <p><strong>Name:</strong> {{ $this->record->name }}</p>
                    <p><strong>Email:</strong> {{ $this->record->email }}</p>
                    <p><strong>Phone:</strong> {{ $this->record->telephone }}</p>
                    <p><strong>Address:</strong> {{ $this->record->location }}</p>
                </div>
                <div>
                    <h3 class="text-lg font-semibold">Statement Details</h3>
                    <p><strong>Statement Period:</strong> {{ $this->data['start_date'] ? \Carbon\Carbon::parse($this->data['start_date'])->format('d M Y') : 'Not set' }} to {{ $this->data['end_date'] ? \Carbon\Carbon::parse($this->data['end_date'])->format('d M Y') : 'Not set' }}</p>
                    <p><strong>Statement Date:</strong> {{ now()->format('d M Y') }}</p>
                </div>
            </div>

            <form wire:submit="getStatementData">
                {{ $this->form }}
                <div class="mt-4">
                    <button type="submit" class="filament-button inline-flex items-center justify-center py-1 gap-1 font-medium rounded-lg border transition-colors outline-none focus:ring-offset-2 focus:ring-2 focus:ring-inset min-h-[2.25rem] px-4 text-sm text-white shadow focus:ring-white border-transparent bg-primary-600 hover:bg-primary-500 focus:bg-primary-700 focus:ring-offset-primary-700">
                        Generate Statement
                    </button>
                </div>
            </form>

            @if($this->data['start_date'] && $this->data['end_date'])
                @php
                    $statementData = $this->getStatementData();
                @endphp

                <div class="mt-8">
                    <div class="grid grid-cols-3 gap-4 mb-6">
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500">Opening Balance</h4>
                            <p class="text-xl font-bold">{{ number_format($statementData['opening_balance'], 2) }} KES</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500">Total Debits</h4>
                            <p class="text-xl font-bold">{{ number_format($statementData['transactions']->sum('debit'), 2) }} KES</p>
                        </div>
                        <div class="p-4 bg-gray-50 rounded-lg">
                            <h4 class="text-sm font-medium text-gray-500">Total Credits</h4>
                            <p class="text-xl font-bold">{{ number_format($statementData['transactions']->sum('credit'), 2) }} KES</p>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reference</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Debit</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Credit</th>
                                    <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($statementData['transactions'] as $transaction)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ \Carbon\Carbon::parse($transaction['date'])->format('d M Y') }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $transaction['reference'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $transaction['description'] }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $transaction['debit'] > 0 ? 'text-red-600 font-medium' : 'text-gray-500' }}">
                                            {{ $transaction['debit'] > 0 ? number_format($transaction['debit'], 2) : '' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right {{ $transaction['credit'] > 0 ? 'text-green-600 font-medium' : 'text-gray-500' }}">
                                            {{ $transaction['credit'] > 0 ? number_format($transaction['credit'], 2) : '' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium {{ $transaction['balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                                            {{ number_format($transaction['balance'], 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="px-6 py-4 text-right font-medium">Closing Balance</td>
                                    <td colspan="3" class="px-6 py-4 text-right font-bold {{ $statementData['closing_balance'] >= 0 ? 'text-gray-900' : 'text-red-600' }}">
                                        {{ number_format($statementData['closing_balance'], 2) }} KES
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>