<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Laporan Shift {{ $shift->number }}</title><style>body{font-family:Arial,sans-serif;margin:24px}.row{display:flex;justify-content:space-between;border-bottom:1px solid #ddd;padding:6px 0}.title{text-align:center;margin-bottom:20px}</style></head>
<body onload="window.print()">
    <div class="title"><h2>Laporan Closing Shift</h2><div>{{ $shift->number }} — {{ $shift->branch?->name }} — {{ $shift->cashier?->name }}</div></div>
    @foreach(['opening_cash'=>'Modal Awal','cash_sales'=>'Penjualan Tunai','non_cash_sales'=>'Non Tunai','receivable_sales'=>'Piutang','refunds'=>'Refund','expenses'=>'Expense','expected_cash'=>'Expected Cash','actual_cash'=>'Actual Cash','difference'=>'Selisih'] as $key => $label)
        <div class="row"><strong>{{ $label }}</strong><span>Rp {{ number_format((float) ($summary[$key] ?? 0), 0, ',', '.') }}</span></div>
    @endforeach
    <p><strong>Catatan:</strong> {{ $shift->handover_notes ?: '-' }}</p>
    <p><strong>Approval:</strong> {{ $shift->approval_notes ?: '-' }}</p>
</body>
</html>
