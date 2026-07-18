<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $purchaseOrder->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 8px; }
        th { background: #f3f4f6; text-align: left; }
        .header { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .title { font-size: 24px; font-weight: bold; }
        .right { text-align: right; }
        .total { font-size: 16px; font-weight: bold; }
        .signature { margin-top: 60px; display: flex; justify-content: space-between; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="title">Purchase Order</div>
            <div>{{ config('app.name') }}</div>
            <div>Gudang: {{ $purchaseOrder->warehouse?->name }}</div>
        </div>
        <div class="right">
            <div><strong>{{ $purchaseOrder->number }}</strong></div>
            <div>Tanggal: {{ $purchaseOrder->order_date?->format('d/m/Y') }}</div>
            <div>ETA: {{ $purchaseOrder->expected_at?->format('d/m/Y') ?: '-' }}</div>
            <div>Status: {{ $purchaseOrder->status->label() }}</div>
        </div>
    </div>
    <table style="margin-bottom: 16px">
        <tr><th>Supplier</th><td>{{ $purchaseOrder->supplier?->name }}</td><th>Termin</th><td>{{ $purchaseOrder->payment_term_days }} hari</td></tr>
        <tr><th>Alamat Supplier</th><td>{{ $purchaseOrder->supplier?->address ?: '-' }}</td><th>Kontak</th><td>{{ $purchaseOrder->supplier?->whatsapp_number ?: $purchaseOrder->supplier?->phone_number ?: '-' }}</td></tr>
    </table>
    <table>
        <thead><tr><th>Produk</th><th>Unit</th><th>Qty</th><th>Harga</th><th>Diskon</th><th>Pajak</th><th>Subtotal</th></tr></thead>
        <tbody>
        @foreach($purchaseOrder->items as $item)
            <tr>
                <td>{{ $item->product_sku_snapshot }} - {{ $item->product_name_snapshot }}</td>
                <td>{{ $item->unit_name_snapshot }} / x {{ qty($item->conversion_factor_snapshot) }}</td>
                <td class="right">{{ qty($item->quantity_ordered) }}</td>
                <td class="right">Rp {{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format((float) $item->discount_amount, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format((float) $item->tax_amount, 0, ',', '.') }}</td>
                <td class="right">Rp {{ number_format((float) $item->subtotal, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <table style="margin-top: 16px; width: 45%; margin-left: auto;">
        <tr><th>Subtotal</th><td class="right">Rp {{ number_format((float) $purchaseOrder->items_subtotal, 0, ',', '.') }}</td></tr>
        <tr><th>Diskon Header</th><td class="right">Rp {{ number_format((float) $purchaseOrder->header_discount, 0, ',', '.') }}</td></tr>
        <tr><th>Ongkir</th><td class="right">Rp {{ number_format((float) $purchaseOrder->freight_cost, 0, ',', '.') }}</td></tr>
        <tr><th>Biaya Tambahan</th><td class="right">Rp {{ number_format((float) $purchaseOrder->additional_cost, 0, ',', '.') }}</td></tr>
        <tr><th class="total">Total Akhir</th><td class="right total">Rp {{ number_format((float) $purchaseOrder->grand_total, 0, ',', '.') }}</td></tr>
    </table>
    <p><strong>Catatan:</strong> {{ $purchaseOrder->notes ?: '-' }}</p>
    <div class="signature">
        <div>Dibuat oleh,<br><br><br>{{ $purchaseOrder->creator?->name ?: '-' }}</div>
        <div>Disetujui oleh,<br><br><br>{{ $purchaseOrder->approver?->name ?: '-' }}</div>
        <div>Supplier,<br><br><br>________________</div>
    </div>
</body>
</html>
