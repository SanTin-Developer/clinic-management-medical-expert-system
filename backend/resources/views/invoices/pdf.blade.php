<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 28px 32px 42px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #172033; margin: 0; }
        h1, h2, p { margin: 0; }
        .header { border-bottom: 2px solid #1769aa; padding-bottom: 14px; margin-bottom: 18px; }
        .brand { color: #125b96; font-size: 19px; font-weight: bold; }
        .document-title { color: #334155; font-size: 13px; font-weight: bold; letter-spacing: .5px; text-align: right; }
        .muted { color: #64748b; }
        .meta { margin-top: 5px; line-height: 1.5; }
        .grid { width: 100%; border-collapse: collapse; margin: 14px 0 18px; }
        .grid td { vertical-align: top; width: 50%; }
        .section-label { color: #64748b; font-size: 8px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; }
        .value { font-size: 10px; font-weight: bold; margin-top: 3px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .items thead { display: table-header-group; }
        .items th { background: #eaf4fb; color: #164e7a; font-size: 8px; letter-spacing: .5px; padding: 8px 6px; text-align: left; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #dce4ec; padding: 8px 6px; vertical-align: top; }
        .items .numeric { text-align: right; white-space: nowrap; }
        .item-detail { color: #64748b; font-size: 8px; margin-top: 2px; }
        .summary { margin-left: auto; margin-top: 18px; width: 245px; border-collapse: collapse; }
        .summary td { padding: 5px 0; }
        .summary .numeric { text-align: right; }
        .summary .total td { border-top: 1px solid #94a3b8; color: #0f4f80; font-size: 12px; font-weight: bold; padding-top: 9px; }
        .summary .balance td { color: {{ (float) $invoice->balance > 0 ? '#b91c1c' : '#047857' }}; font-weight: bold; }
        .footer { border-top: 1px solid #dce4ec; bottom: -25px; color: #64748b; font-size: 8px; left: 0; position: fixed; right: 0; padding-top: 7px; }
        .page-number:after { content: counter(page) ' / ' counter(pages); }
    </style>
</head>
<body>
    <table class="header" width="100%"><tr>
        <td>
            <div class="brand">{{ $clinicName }}</div>
            <div class="meta muted">Pharmacy sales invoice</div>
        </td>
        <td>
            <div class="document-title">PHARMACY SALES INVOICE</div>
            <div class="meta muted" style="text-align:right">{{ $invoice->invoice_number }}<br>{{ optional($invoice->issued_at)->format('M j, Y') ?? '—' }}</div>
        </td>
    </tr></table>

    <table class="grid"><tr>
        <td>
            <div class="section-label">Customer</div>
            @if ($invoice->patient)
                <div class="value">{{ trim($invoice->patient->first_name.' '.$invoice->patient->last_name) }}</div>
                @if ($invoice->patient->patient_number)<div class="muted">Patient ID: {{ $invoice->patient->patient_number }}</div>@endif
            @else
                <div class="value">Walk-in customer</div>
            @endif
        </td>
        <td>
            <div class="section-label">Transaction</div>
            <div class="value">{{ $sale?->sale_number ?? $invoice->invoice_number }}</div>
            <div class="muted">Cashier: {{ $invoice->cashier?->name ?? '—' }}<br>Payment status: {{ str_replace('_', ' ', ucfirst($invoice->status)) }}</div>
        </td>
    </tr></table>

    <table class="items">
        <thead><tr><th>#</th><th>Medicine</th><th class="numeric">Qty</th><th class="numeric">Unit price</th><th class="numeric">Line subtotal</th></tr></thead>
        <tbody>
        @forelse ($invoice->items as $index => $item)
            @php($saleItem = $sale?->items->firstWhere('medicine.name', $item->description))
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->description }}</strong>
                    @if ($saleItem?->medicine)
                        <div class="item-detail">{{ collect([$saleItem->medicine->code, $saleItem->medicine->strength, $saleItem->medicine->form])->filter()->implode(' · ') }}</div>
                    @endif
                </td>
                <td class="numeric">{{ $item->quantity }}</td>
                <td class="numeric">${{ number_format((float) $item->unit_price, 2) }}</td>
                <td class="numeric">${{ number_format((float) $item->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="muted">No line items recorded.</td></tr>
        @endforelse
        </tbody>
    </table>

    <table class="summary">
        <tr><td class="muted">Subtotal</td><td class="numeric">${{ number_format((float) $invoice->subtotal, 2) }}</td></tr>
        @if ((float) $invoice->discount > 0)<tr><td class="muted">Discount</td><td class="numeric">−${{ number_format((float) $invoice->discount, 2) }}</td></tr>@endif
        @if ((float) $invoice->tax > 0)<tr><td class="muted">Tax</td><td class="numeric">${{ number_format((float) $invoice->tax, 2) }}</td></tr>@endif
        <tr class="total"><td>Final total</td><td class="numeric">${{ number_format((float) $invoice->total, 2) }}</td></tr>
        <tr><td class="muted">Amount paid</td><td class="numeric">${{ number_format((float) $invoice->amount_paid, 2) }}</td></tr>
        <tr class="balance"><td>Outstanding balance</td><td class="numeric">${{ number_format((float) $invoice->balance, 2) }}</td></tr>
    </table>

    <div class="footer">Thank you for choosing {{ $clinicName }}. This document is a pharmacy sales invoice. · Reference: {{ $invoice->invoice_number }} <span style="float:right">Page <span class="page-number"></span></span></div>
</body>
</html>
