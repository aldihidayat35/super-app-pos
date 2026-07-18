<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Berita Penerimaan {{ $receipt->number }}</title>
    <style>body{font-family:Arial,sans-serif;color:#222}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{border:1px solid #ddd;padding:8px;text-align:left}th{background:#f5f5f5}.right{text-align:right}.muted{color:#666}</style>
</head>
<body onload="window.print()">
    <h2>Berita Penerimaan Barang</h2>
    <p class="muted">{{ config('app.name') }}</p>
    <table>
        <tr><th>No Receipt</th><td>{{ $receipt->number }}</td><th>PO</th><td>{{ $receipt->purchaseOrder?->number }}</td></tr>
        <tr><th>Supplier</th><td>{{ $receipt->supplier?->name }}</td><th>Gudang</th><td>{{ $receipt->warehouse?->name }}</td></tr>
        <tr><th>Tanggal</th><td>{{ $receipt->received_at?->format('d/m/Y') }}</td><th>Penerima</th><td>{{ $receipt->receiver?->name }}</td></tr>
    </table>
    <table>
        <thead><tr><th>Produk</th><th>Unit</th><th>Datang</th><th>Accepted</th><th>Rejected</th><th>Damaged</th><th>Lokasi</th><th>Batch</th></tr></thead>
        <tbody>@foreach($receipt->items as $item)<tr><td>{{ $item->product_sku_snapshot }} - {{ $item->product_name_snapshot }}</td><td>{{ $item->unit_name_snapshot }}</td><td>{{ qty($item->quantity_received) }}</td><td>{{ qty($item->quantity_accepted) }}</td><td>{{ qty($item->quantity_rejected) }}</td><td>{{ qty($item->quantity_damaged) }}</td><td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td><td>{{ $item->batch_no ?: '-' }}</td></tr>@endforeach</tbody>
    </table>
    <br><br>
    <table><tr><td style="height:90px;vertical-align:bottom;text-align:center">Penerima<br>{{ $receipt->receiver?->name }}</td><td style="height:90px;vertical-align:bottom;text-align:center">Kepala Gudang</td></tr></table>
</body>
</html>
