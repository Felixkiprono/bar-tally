<div class="space-y-6 p-4 rounded-md
    bg-white dark:bg-gray-900
    text-gray-800 dark:text-gray-200">


    <!-- EXPORT BUTTON -->
    <div class="flex justify-end">
        <button
            wire:click="exportCsv"
            class="px-4 py-2 bg-primary-600 hover:bg-primary-700
                     text-white rounded-lg shadow text-sm">
            Export CSV
        </button>
    </div>

    <!-- FILTERS -->
    <div class="flex gap-4">

        <!-- Date Filter -->
        <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Date</label>
            <input type="date"
                wire:model.live="date"
                class="px-3 py-2 rounded-lg border
                       bg-white text-gray-800
                       border-gray-300
                       dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700
                       focus:border-primary-500 focus:ring-primary-500" />
        </div>

        <!-- Item Filter -->
        <div class="flex flex-col">
            <label class="text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Item</label>
            <select wire:model.live="item"
                class="px-3 py-2 rounded-lg border
                       bg-white text-gray-800
                       border-gray-300
                       dark:bg-gray-800 dark:text-gray-200 dark:border-gray-700
                       focus:border-primary-500 focus:ring-primary-500">
                <option value="">All Items</option>
                @foreach($items as $it)
                <option value="{{ $it->id }}">{{ $it->name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <!-- TABLE -->
    <div class="overflow-hidden rounded-xl shadow
        border border-gray-300 dark:border-gray-700
        bg-white dark:bg-gray-900">

        <table class="w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-200">
                <tr>
                    <th class="p-3 text-left font-medium">Item</th>
                    <th class="p-3 text-center font-medium">Opening</th>
                    <th class="p-3 text-center font-medium">Restock</th>
                    <th class="p-3 text-center font-medium">Sold</th>
                    <th class="p-3 text-center font-medium">Closing</th>
                    <th class="p-3 text-center font-medium">Expected</th>
                    <th class="p-3 text-center font-medium">Variance</th>
                    {{-- NEW: Counter columns --}}
                  @foreach($counters as $counterId => $counterName)
                        <th class="p-3 text-center font-medium">
                            Counter {{ $counterName }}
                        </th>
                    @endforeach
                    <th class="p-3 text-center font-medium">Cost</th>
                    <th class="p-3 text-center font-medium">Selling</th>
                    <th class="p-3 text-center font-medium">Expected Profit</th>
                </tr>
            </thead>

            @php
            $grouped = $rows->groupBy('item_id');

            // Totals accumulator
            $totals = [
            'opening' => 0,
            'restock' => 0,
            'sold' => 0,
            'closing' => 0,
            'expected' => 0,
            'variance' => 0,
            'profit' => 0,
            'cost' => 0,
            'selling' => 0,
            ];
            @endphp


            <tbody>
                @forelse($grouped as $itemId => $movements)

                @php
                $item = $movements->first();
                $opening = $movements->where('movement_type','opening_stock')->sum('quantity');
                $restock = $movements->where('movement_type','restock')->sum('quantity');
                $sold = $movements->where('movement_type','sale')->sum('quantity');
                $closing = $movements->where('movement_type','closing_stock')->sum('quantity');

                $expected = $opening + $restock - $sold;
                $variance = $closing  - $expected;

                $cost = floatval($item->cost_price ?? 0);
                $selling = floatval($item->selling_price ?? 0);
                $finalprofit = ($cost* $sold) - ($selling * $sold);
                $profit = $sold * ($selling - $cost);

                // Accumulate totals
                $totals['opening'] += $opening;
                $totals['restock'] += $restock;
                $totals['sold'] += $sold;
                $totals['closing'] += $closing;
                $totals['expected'] += $expected;
                $totals['variance'] += $variance;
                $totals['profit'] += $profit;
                $totals['cost'] += $cost;
                $totals['selling'] += $selling;

                $counterVariances = $this->calculateCounterVariances($movements);

                @endphp


                <tr class="transition hover:bg-gray-200/50 dark:hover:bg-gray-700/50">

                    <td class="p-3 font-semibold">{{ $item->item_name }}</td>

                    <td class="p-3 text-center">{{ $opening }}</td>

                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                            {{ $restock }}
                        </span>
                    </td>

                    <td class="p-3 text-center">
                        <span class="px-2 py-1 rounded-full bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">
                            {{ $sold }}
                        </span>
                    </td>

                    <td class="p-3 text-center">{{ $closing }}</td>
                    <td class="p-3 text-center">{{ $expected }}</td>
                    <td class="p-3 text-center font-semibold">
                        @if ($variance > 0)
                            <span class="text-green-400">
                                +{{ $variance }}
                            </span>
                        @elseif ($variance < 0)
                            <span class="text-red-400">
                                {{ $variance }}
                            </span>
                        @else
                            <span class="text-gray-500">
                                0
                            </span>
                        @endif
                    </td>


                {{-- ✅ Counter columns (INSIDE THE ROW) --}}
                @foreach($counters as $counterId => $counterName)
                    @php $cv = $counterVariances[$counterId] ?? 0; @endphp
                   <td class="p-3 text-center font-semibold">
                    @if ($cv > 0)
                        <span class="text-green-400">
                            +{{ $cv }}
                        </span>
                    @elseif ($cv < 0)
                        <span class="text-red-400">
                            {{ $cv }}
                        </span>
                    @else
                        <span class="text-gray-500">
                            0
                        </span>
                    @endif
                </td>

                @endforeach

                    <td class="p-3 text-center">{{ number_format($cost, 2) }}</td>
                    <td class="p-3 text-center">{{ number_format($selling, 2) }}</td>

                    <td class="p-3 text-center font-bold">
                        <span class="px-2 py-1 rounded-full
                            {{ $profit >= 0
                                ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300'
                            }}">
                            KES {{ number_format($profit, 2) }}
                        </span>
                    </td>

                </tr>

                @empty
                <tr>
                    <td colspan="10" class="p-4 text-center text-gray-500 dark:text-gray-400">
                        No data found.
                    </td>
                </tr>
                @endforelse
            </tbody>

            <!-- FOOTER TOTALS ROW -->
            <tfoot class="bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-200">
                <tr class="font-semibold">
                    <td class="p-3 text-left">Totals</td>
                    <td class="p-3 text-center">{{ $totals['opening'] }}</td>
                    <td class="p-3 text-center text-green-500">{{ $totals['restock'] }}</td>
                    <td class="p-3 text-center text-red-500">{{ $totals['sold'] }}</td>
                    <td class="p-3 text-center">{{ $totals['closing'] }}</td>
                    <td class="p-3 text-center">{{ $totals['expected'] }}</td>

                    <td class="p-3 text-center">
                       @if ($totals['variance'] < 0)
                        <span class="text-red-400">
                            {{ $totals['variance'] }}
                        </span>
                    @elseif ($totals['variance'] > 0)
                        <span class="text-green-400">
                            +{{ $totals['variance'] }}
                        </span>
                    @else
                        <span class="text-gray-500">
                            balanced
                        </span>
                    @endif

                    </td>
                    @foreach($counters as $counterId => $counterName)
                    @php $cv = $this->counterVarianceTotals[$counterId] ?? 0; @endphp
                      <td class="p-3 text-center font-semibold">
                        @if ($cv > 0)
                            <span class="text-green-400">+{{ $cv }}</span>
                        @elseif ($cv < 0)
                            <span class="text-red-400">{{ $cv }} shortage</span>
                        @else
                            <span class="text-gray-500">0</span>
                        @endif
                    </td>

                    @endforeach

                    <td class="p-3 text-center font-bold">
                        <span class="px-2 py-1 rounded bg-blue-200 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                            KES {{ number_format($totals['cost'], 2) }}
                        </span>
                    </td>
                    <td class="p-3 text-center font-bold">
                        <span class="px-2 py-1 rounded bg-blue-200 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                            KES {{ number_format($totals['selling'], 2) }}
                        </span>
                    </td>

                    <td class="p-3 text-center font-bold">
                        <span class="px-2 py-1 rounded bg-blue-200 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                            KES {{ number_format($totals['profit'], 2) }}
                        </span>
                    </td>
                </tr>
            </tfoot>

        </table>


    </div>
    <div class="mt-6 flex justify-center">
        {{ $rows->links() }}
    </div>
</div>
