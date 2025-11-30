@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Financial Report</h5>
                    <div class="d-flex align-items-center">
                        <form action="{{ route('reports.financial') }}" method="GET" class="d-flex align-items-center me-3">
                            <div class="input-group">
                                <input type="date" name="start_date" class="form-control" value="{{ $startDate->format('Y-m-d') }}">
                                <span class="input-group-text">to</span>
                                <input type="date" name="end_date" class="form-control" value="{{ $endDate->format('Y-m-d') }}">
                                <button type="submit" class="btn btn-primary">Apply</button>
                            </div>
                        </form>
                        <a href="{{ route('reports.financial.download', ['start_date' => $startDate->format('Y-m-d'), 'end_date' => $endDate->format('Y-m-d')]) }}"
                           class="btn btn-success">
                            <i class="fas fa-download"></i> Download PDF
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <!-- Profit and Loss Statement -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0">Profit and Loss Statement</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <tr>
                                                <td>Revenue</td>
                                                <td class="text-end">{{ number_format($profitAndLoss['revenue'], 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td>Expenses</td>
                                                <td class="text-end">{{ number_format($profitAndLoss['expenses'], 2) }}</td>
                                            </tr>
                                            <tr class="table-primary fw-bold">
                                                <td>Net Income</td>
                                                <td class="text-end">{{ number_format($profitAndLoss['net_income'], 2) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="mt-4">
                                        <canvas id="profitLossChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Balance Sheet -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0">Balance Sheet</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <tr>
                                                <td>Assets</td>
                                                <td class="text-end">{{ number_format($balanceSheet['assets'], 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td>Liabilities</td>
                                                <td class="text-end">{{ number_format($balanceSheet['liabilities'], 2) }}</td>
                                            </tr>
                                            <tr class="table-info fw-bold">
                                                <td>Equity</td>
                                                <td class="text-end">{{ number_format($balanceSheet['equity'], 2) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cash Flow Statement -->
                        <div class="col-md-4">
                            <div class="card h-100">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">Cash Flow Statement</h6>
                                </div>
                                <div class="card-body">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <tr>
                                                <td>Operating Activities</td>
                                                <td class="text-end">{{ number_format($cashFlow['operating'], 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td>Investing Activities</td>
                                                <td class="text-end">{{ number_format($cashFlow['investing'], 2) }}</td>
                                            </tr>
                                            <tr>
                                                <td>Financing Activities</td>
                                                <td class="text-end">{{ number_format($cashFlow['financing'], 2) }}</td>
                                            </tr>
                                            <tr class="table-success fw-bold">
                                                <td>Net Cash Flow</td>
                                                <td class="text-end">{{ number_format($cashFlow['net_cash_flow'], 2) }}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('profitLossChart').getContext('2d');
    const revenue = {{ $profitAndLoss['revenue'] }};
    const expenses = {{ $profitAndLoss['expenses'] }};
    const netIncome = {{ $profitAndLoss['net_income'] }};

    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: ['Revenue', 'Expenses', 'Net Income'],
            datasets: [{
                data: [revenue, expenses, netIncome],
                backgroundColor: [
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)'
                ],
                borderColor: [
                    'rgba(75, 192, 192, 1)',
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Profit and Loss Distribution'
                }
            }
        }
    });
});
</script>
@endsection

@push('styles')
<style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        margin-bottom: 1rem;
    }
    .table-responsive {
        margin-bottom: 0;
    }
    .input-group {
        flex-wrap: nowrap;
    }
</style>
@endpush