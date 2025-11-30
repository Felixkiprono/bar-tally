<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .report-title {
            font-size: 18px;
            margin-bottom: 5px;
        }
        .report-date {
            font-size: 14px;
            color: #666;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .total-row {
            font-weight: bold;
            background-color: #f9f9f9;
        }
        .positive {
            color: #28a745;
        }
        .negative {
            color: #dc3545;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ config('app.name') }}</div>
        <div class="report-title">{{ $title ?? 'Financial Report' }}</div>
        <div class="report-date">{{ $date ?? now()->format('d M Y') }}</div>
    </div>

    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Print Report</button>
    </div>

    @if(isset($profitAndLoss))
    <div class="section">
        <div class="section-title">Profit and Loss Statement</div>
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2"><strong>Revenue</strong></td>
                </tr>
                @foreach($profitAndLoss['revenue'] as $item)
                <tr>
                    <td>{{ $item->account->name }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Revenue</td>
                    <td class="text-right">{{ number_format($profitAndLoss['total_revenue'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Expenses</strong></td>
                </tr>
                @foreach($profitAndLoss['expenses'] as $item)
                <tr>
                    <td>{{ $item->account->name }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Expenses</td>
                    <td class="text-right">{{ number_format($profitAndLoss['total_expenses'], 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Net Profit/Loss</td>
                    <td class="text-right {{ $profitAndLoss['net_profit'] >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($profitAndLoss['net_profit'], 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    @if(isset($balanceSheet))
    <div class="section">
        <div class="section-title">Balance Sheet</div>
        <table>
            <thead>
                <tr>
                    <th>Account</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="2"><strong>Assets</strong></td>
                </tr>
                @foreach($balanceSheet['assets'] as $item)
                <tr>
                    <td>{{ $item->account->name }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Assets</td>
                    <td class="text-right">{{ number_format($balanceSheet['total_assets'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Liabilities</strong></td>
                </tr>
                @foreach($balanceSheet['liabilities'] as $item)
                <tr>
                    <td>{{ $item->account->name }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Liabilities</td>
                    <td class="text-right">{{ number_format($balanceSheet['total_liabilities'], 2) }}</td>
                </tr>
                <tr>
                    <td colspan="2"><strong>Equity</strong></td>
                </tr>
                @foreach($balanceSheet['equity'] as $item)
                <tr>
                    <td>{{ $item->account->name }}</td>
                    <td class="text-right">{{ number_format($item->total, 2) }}</td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Equity</td>
                    <td class="text-right">{{ number_format($balanceSheet['total_equity'], 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Liabilities and Equity</td>
                    <td class="text-right">{{ number_format($balanceSheet['total_liabilities'] + $balanceSheet['total_equity'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    @if(isset($cashFlow))
    <div class="section">
        <div class="section-title">Cash Flow Statement</div>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th class="text-right">Net Flow</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cashFlow['daily_cash_flow'] as $item)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($item->flow_date)->format('d M Y') }}</td>
                    <td class="text-right {{ $item->net_flow >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($item->net_flow, 2) }}
                    </td>
                </tr>
                @endforeach
                <tr class="total-row">
                    <td>Total Inflow</td>
                    <td class="text-right positive">{{ number_format($cashFlow['total_inflow'], 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Total Outflow</td>
                    <td class="text-right negative">{{ number_format($cashFlow['total_outflow'], 2) }}</td>
                </tr>
                <tr class="total-row">
                    <td>Net Cash Flow</td>
                    <td class="text-right {{ $cashFlow['net_cash_flow'] >= 0 ? 'positive' : 'negative' }}">
                        {{ number_format($cashFlow['net_cash_flow'], 2) }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>Generated on {{ now()->format('d M Y H:i:s') }}</p>
        <p>This report is for internal use only.</p>
    </div>
</body>
</html>