<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Financial Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .section {
            margin-bottom: 30px;
        }
        .section-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f8f9fa;
        }
        .text-right {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8f9fa;
        }
        .date-range {
            text-align: center;
            margin-bottom: 20px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Financial Report</h1>
        <div class="date-range">
            {{ $startDate->format('M d, Y') }} - {{ $endDate->format('M d, Y') }}
        </div>
    </div>

    <div class="section">
        <div class="section-title">Profit and Loss Statement</div>
        <table>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
            <tr>
                <td>Revenue</td>
                <td class="text-right">{{ number_format($profitAndLoss['revenue'], 2) }}</td>
            </tr>
            <tr>
                <td>Expenses</td>
                <td class="text-right">{{ number_format($profitAndLoss['expenses'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Net Income</td>
                <td class="text-right">{{ number_format($profitAndLoss['net_income'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Balance Sheet</div>
        <table>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
            <tr>
                <td>Assets</td>
                <td class="text-right">{{ number_format($balanceSheet['assets'], 2) }}</td>
            </tr>
            <tr>
                <td>Liabilities</td>
                <td class="text-right">{{ number_format($balanceSheet['liabilities'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Equity</td>
                <td class="text-right">{{ number_format($balanceSheet['equity'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Cash Flow Statement</div>
        <table>
            <tr>
                <th>Description</th>
                <th class="text-right">Amount</th>
            </tr>
            <tr>
                <td>Operating Activities</td>
                <td class="text-right">{{ number_format($cashFlow['operating'], 2) }}</td>
            </tr>
            <tr>
                <td>Investing Activities</td>
                <td class="text-right">{{ number_format($cashFlow['investing'], 2) }}</td>
            </tr>
            <tr>
                <td>Financing Activities</td>
                <td class="text-right">{{ number_format($cashFlow['financing'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Net Cash Flow</td>
                <td class="text-right">{{ number_format($cashFlow['net_cash_flow'], 2) }}</td>
            </tr>
        </table>
    </div>
</body>
</html>