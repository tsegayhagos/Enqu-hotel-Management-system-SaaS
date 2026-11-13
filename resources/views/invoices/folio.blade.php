<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $folio->folio_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
            padding: 24px;
        }
        .header {
            border-bottom: 2px solid #004758;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .brand {
            font-size: 24px;
            font-weight: bold;
            color: #004758;
        }
        .hotel-info {
            font-size: 11px;
            color: #6b7280;
        }
        .invoice-title {
            text-align: right;
            float: right;
        }
        .invoice-title h1 {
            margin: 0;
            font-size: 22px;
            color: #004758;
        }
        .meta-grid {
            width: 100%;
            margin-bottom: 24px;
        }
        .meta-grid td {
            vertical-align: top;
            width: 50%;
        }
        .meta-box {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 12px;
        }
        .meta-box h3 {
            margin: 0 0 8px 0;
            font-size: 12px;
            text-transform: uppercase;
            color: #004758;
            letter-spacing: 0.5px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        table.items th {
            background-color: #004758;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            font-size: 11px;
            text-transform: uppercase;
        }
        table.items td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px 10px;
        }
        .text-right {
            text-align: right;
        }
        .totals-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .totals-table td {
            padding: 6px 10px;
        }
        .totals-table .total-row {
            font-size: 15px;
            font-weight: bold;
            border-top: 2px solid #004758;
            color: #004758;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #e5e7eb;
            padding-top: 12px;
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .badge-settled { background: #d1fae5; color: #065f46; }
        .badge-open { background: #dbeafe; color: #1e40af; }
    </style>
</head>
<body>
    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <div class="brand">{{ $hotel->name }}</div>
                    <div class="hotel-info">
                        {{ $hotel->address ?? 'Luxury Hospitality & Suites' }}<br>
                        Email: {{ $hotel->email ?? 'info@enku.test' }} | Phone: {{ $hotel->phone ?? '+251-11-000-0000' }}
                    </div>
                </td>
                <td style="text-align: right;">
                    <div class="invoice-title">
                        <h1>GUEST FOLIO INVOICE</h1>
                        <p style="margin: 4px 0; color: #4b5563;">Folio #: <strong>{{ $folio->folio_number }}</strong></p>
                        <p style="margin: 0; color: #4b5563;">Date: {{ now()->format('M d, Y') }}</p>
                        <span class="badge {{ $folio->isSettled() ? 'badge-settled' : 'badge-open' }}">
                            {{ $folio->status->label() }}
                        </span>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-grid">
        <tr>
            <td style="padding-right: 12px;">
                <div class="meta-box">
                    <h3>Guest Information</h3>
                    <p style="margin: 2px 0;"><strong>Guest Name:</strong> {{ $folio->guest_name ?? 'Guest' }}</p>
                    <p style="margin: 2px 0;"><strong>Currency:</strong> {{ $folio->currency }}</p>
                </div>
            </td>
            <td style="padding-left: 12px;">
                <div class="meta-box">
                    <h3>Stay Details</h3>
                    <p style="margin: 2px 0;"><strong>Room:</strong> {{ $folio->room?->room_number ?? 'N/A' }} ({{ $folio->room?->roomType?->name ?? 'Standard' }})</p>
                    <p style="margin: 2px 0;"><strong>Folio Opened:</strong> {{ $folio->created_at->format('M d, Y H:i') }}</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 15%;">Date & Time</th>
                <th style="width: 18%;">Type</th>
                <th style="width: 37%;">Description</th>
                <th style="width: 15%; text-align: right;">Charge</th>
                <th style="width: 15%; text-align: right;">Credit / Paid</th>
            </tr>
        </thead>
        <tbody>
            @foreach($folio->transactions as $tx)
            <tr>
                <td>{{ $tx->posted_at->format('M d, H:i') }}</td>
                <td>{{ $tx->transaction_type->label() }}</td>
                <td>
                    {{ $tx->description }}
                    @if($tx->reference_id)
                        <span style="color: #6b7280; font-size: 10px;">(Ref: {{ $tx->reference_id }})</span>
                    @endif
                </td>
                <td class="text-right">
                    @if($tx->transaction_type->isCharge())
                        {{ $folio->currency }} {{ number_format((float)$tx->amount, 2) }}
                    @else
                        -
                    @endif
                </td>
                <td class="text-right" style="color: #059669;">
                    @if(!$tx->transaction_type->isCharge())
                        {{ $folio->currency }} {{ number_format(abs((float)$tx->amount), 2) }}
                    @else
                        -
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Total Charges:</td>
            <td class="text-right">{{ $folio->currency }} {{ number_format((float)$folio->total_charges, 2) }}</td>
        </tr>
        <tr>
            <td>Total Payments:</td>
            <td class="text-right" style="color: #059669;">- {{ $folio->currency }} {{ number_format((float)$folio->total_payments, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td>Balance Due:</td>
            <td class="text-right">{{ $folio->currency }} {{ number_format((float)$folio->balance, 2) }}</td>
        </tr>
    </table>

    <div class="footer">
        <p>Thank you for choosing {{ $hotel->name }}. We look forward to welcoming you back!</p>
        <p style="font-size: 10px;">Generated automatically by Enku Hotel Management SaaS.</p>
    </div>
</body>
</html>
