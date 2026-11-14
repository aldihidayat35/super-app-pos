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
        <tr><th>Supplier</th><td>{{ $receipt->supplier?->name }}</td><th>Lokasi penerima</th><td>{{ $receipt->destinationName() }}</td></tr>
        <tr><th>Tanggal</th><td>{{ $receipt->received_at?->format('d/m/Y') }}</td><th>Penerima</th><td>{{ $receipt->receiver?->name }}</td></tr>
    </table>
    <table>
        <thead><tr><th>Produk</th><th>Unit</th><th>Datang</th><th>Accepted</th><th>Rejected</th><th>Damaged</th><th>Lokasi</th><th>Batch</th></tr></thead>
        <tbody>@foreach($receipt->items as $item)<tr><td>{{ $item->product_sku_snapshot }} - {{ $item->product_name_snapshot }}</td><td>{{ $item->unit_name_snapshot }}</td><td>{{ qty($item->quantity_received) }}</td><td>{{ qty($item->quantity_accepted) }}</td><td>{{ qty($item->quantity_rejected) }}</td><td>{{ qty($item->quantity_damaged) }}</td><td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td><td>{{ $item->batch_no ?: '-' }}</td></tr>@endforeach</tbody>
    </table>
    <div style="display:flex;justify-content:space-around;margin-top:40px;text-align:center;">
        <div style="width:40%;">
            <div>Petugas Penerima,</div>
            <div style="height:50px;"></div>
            <div style="font-weight:bold;white-space:nowrap;">( {{ $receipt->receiver?->name ?: '....................' }} )</div>
        </div>
        <div style="width:40%;">
            <div>Kepala Gudang / Verifikator,</div>
            <div style="height:50px;"></div>
            <div style="font-weight:bold;white-space:nowrap;">( .................... )</div>
        </div>
    </div>
</body>
</html>
