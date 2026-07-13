<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Surat Jalan {{ $transfer->number }}</title><style>body{font-family:Arial,sans-serif}table{width:100%;border-collapse:collapse;margin-top:18px}th,td{border:1px solid #ddd;padding:8px}th{background:#f7f7f7}.muted{color:#666}</style></head>
<body onload="window.print()">
    <h2>Surat Jalan Transfer Stok</h2>
    <p class="muted">{{ config('app.name') }}</p>
    <table><tr><th>No Transfer</th><td>{{ $transfer->number }}</td><th>Status</th><td>{{ $transfer->status->label() }}</td></tr><tr><th>Sumber</th><td>{{ $transfer->sourceWorkLocation?->name }}</td><th>Tujuan</th><td>{{ $transfer->destinationWorkLocation?->name }}</td></tr><tr><th>Kurir</th><td>{{ $transfer->carrier ?: '-' }}</td><th>Resi/Kendaraan</th><td>{{ $transfer->tracking_number ?: $transfer->vehicle_number ?: '-' }}</td></tr></table>
    <table><thead><tr><th>Produk</th><th>Approved</th><th>Picked</th><th>Shipped</th><th>Catatan</th></tr></thead><tbody>@foreach($transfer->items as $item)<tr><td>{{ $item->product_sku_snapshot }} - {{ $item->product_name_snapshot }}</td><td>{{ $item->quantity_approved }}</td><td>{{ $item->quantity_picked }}</td><td>{{ $item->quantity_shipped }}</td><td>{{ $item->notes ?: '-' }}</td></tr>@endforeach</tbody></table>
    <br><br><table><tr><td style="height:90px;vertical-align:bottom;text-align:center">Pengirim<br>{{ $transfer->shipper?->name ?: '-' }}</td><td style="height:90px;vertical-align:bottom;text-align:center">Penerima</td></tr></table>
</body>
</html>
