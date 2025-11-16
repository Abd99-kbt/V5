<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('invoices.invoice') }} {{ $invoice->invoice_number }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @if(app()->getLocale() === 'ar')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600&display=swap" rel="stylesheet">
    @endif

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: {{ app()->getLocale() === 'ar' ? "'Noto Sans Arabic', sans-serif" : "'Figtree', sans-serif" }};
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        .invoice-header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            margin: 0;
        }
        .invoice-number {
            font-size: 18px;
            margin: 10px 0;
        }
        .invoice-details {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .detail-section {
            flex: 1;
        }
        .detail-section h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }
        .detail-row {
            display: flex;
            margin-bottom: 5px;
        }
        .detail-label {
            font-weight: bold;
            width: 120px;
        }
        .detail-value {
            flex: 1;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .invoice-table th,
        .invoice-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .invoice-table th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .totals {
            text-align: right;
            margin-top: 20px;
        }
        .total-row {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 5px;
        }
        .total-label {
            font-weight: bold;
            width: 150px;
        }
        .total-value {
            width: 100px;
            text-align: right;
        }
        .grand-total {
            border-top: 2px solid #333;
            padding-top: 10px;
            font-size: 18px;
            font-weight: bold;
        }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-header">
        <h1 class="invoice-title">{{ __('invoices.invoice') }}</h1>
        <div class="invoice-number">{{ __('invoices.invoice_number') }}: {{ $invoice->invoice_number }}</div>
    </div>

    <div class="invoice-details">
        <div class="detail-section">
            <h3>{{ __('invoices.customer') }}</h3>
            <div class="detail-row">
                <div class="detail-label">{{ __('customers.name') }}:</div>
                <div class="detail-value">{{ $invoice->customer->name ?? '' }}</div>
            </div>
        </div>

        <div class="detail-section">
            <h3>{{ __('invoices.invoice_details') }}</h3>
            <div class="detail-row">
                <div class="detail-label">{{ __('invoices.invoice_date') }}:</div>
                <div class="detail-value">{{ $invoice->invoice_date?->format('Y-m-d') }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">{{ __('invoices.due_date') }}:</div>
                <div class="detail-value">{{ $invoice->due_date?->format('Y-m-d') }}</div>
            </div>
            <div class="detail-row">
                <div class="detail-label">{{ __('invoices.status') }}:</div>
                <div class="detail-value">{{ $invoice->is_paid ? __('invoices.paid') : __('invoices.unpaid') }}</div>
            </div>
        </div>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>{{ __('invoices.description') }}</th>
                <th>{{ __('invoices.quantity') }}</th>
                <th>{{ __('invoices.unit_price') }}</th>
                <th>{{ __('invoices.total') }}</th>
            </tr>
        </thead>
        <tbody>
            <!-- Invoice items would go here if you have them -->
            <tr>
                <td>{{ __('invoices.invoice_total') }}</td>
                <td>1</td>
                <td>${{ number_format($invoice->total_amount, 2) }}</td>
                <td>${{ number_format($invoice->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <div class="total-row">
            <div class="total-label">{{ __('invoices.subtotal') }}:</div>
            <div class="total-value">${{ number_format($invoice->subtotal, 2) }}</div>
        </div>
        <div class="total-row">
            <div class="total-label">{{ __('invoices.tax_amount') }}:</div>
            <div class="total-value">${{ number_format($invoice->tax_amount, 2) }}</div>
        </div>
        <div class="total-row">
            <div class="total-label">{{ __('invoices.discount_amount') }}:</div>
            <div class="total-value">-${{ number_format($invoice->discount_amount, 2) }}</div>
        </div>
        <div class="total-row grand-total">
            <div class="total-label">{{ __('invoices.total_amount') }}:</div>
            <div class="total-value">${{ number_format($invoice->total_amount, 2) }}</div>
        </div>
    </div>
</body>
</html>