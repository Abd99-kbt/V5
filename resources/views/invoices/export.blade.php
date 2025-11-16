<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('invoices.invoices_export') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    @if(app()->getLocale() === 'ar')
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;500;600&display=swap" rel="stylesheet">
    @endif

    <style>
        body {
            font-family: {{ app()->getLocale() === 'ar' ? "'Noto Sans Arabic', sans-serif" : "'Figtree', sans-serif" }};
            margin: 0;
            padding: 20px;
            background: white;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .export-date {
            font-size: 14px;
            color: #666;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .summary {
            text-align: right;
            margin-top: 20px;
            font-weight: bold;
        }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">{{ __('invoices.invoices_export') }}</h1>
        <div class="export-date">{{ __('invoices.export_date') }}: {{ now()->format('Y-m-d H:i:s') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>{{ __('invoices.invoice_number') }}</th>
                <th>{{ __('invoices.customer') }}</th>
                <th>{{ __('invoices.invoice_date') }}</th>
                <th>{{ __('invoices.due_date') }}</th>
                <th>{{ __('invoices.subtotal') }}</th>
                <th>{{ __('invoices.tax_amount') }}</th>
                <th>{{ __('invoices.discount_amount') }}</th>
                <th>{{ __('invoices.total_amount') }}</th>
                <th>{{ __('invoices.is_paid') }}</th>
                <th>{{ __('invoices.paid_at') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
            <tr>
                <td>{{ $invoice->invoice_number }}</td>
                <td>{{ $invoice->customer->name ?? '' }}</td>
                <td>{{ $invoice->invoice_date?->format('Y-m-d') }}</td>
                <td>{{ $invoice->due_date?->format('Y-m-d') }}</td>
                <td>${{ number_format($invoice->subtotal, 2) }}</td>
                <td>${{ number_format($invoice->tax_amount, 2) }}</td>
                <td>${{ number_format($invoice->discount_amount, 2) }}</td>
                <td>${{ number_format($invoice->total_amount, 2) }}</td>
                <td>{{ $invoice->is_paid ? __('invoices.paid') : __('invoices.unpaid') }}</td>
                <td>{{ $invoice->paid_at?->format('Y-m-d H:i:s') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        {{ __('invoices.total_invoices') }}: {{ $invoices->count() }}
    </div>
</body>
</html>