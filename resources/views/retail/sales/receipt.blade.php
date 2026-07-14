<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Struk {{ $sale->number }}</title>
    <style>body{font-family:monospace;max-width:320px;margin:0 auto}.center{text-align:center}.line{border-top:1px dashed #000;margin:8px 0}.row{display:flex;justify-content:space-between}</style>
</head>
<body onload="window.print()">
    <div class="center"><strong>{{ config('app.name') }}</strong><br>{{ $sale->branch?->name }}<br>{{ $sale->number }}</div>
    <div class="line"></div>
    <div>{{ $sale->completed_at?->format('d/m/Y H:i') }} / {{ $sale->cashier?->name }}</div>
    <div class="line"></div>
    @foreach($sale->items as $item)
        <div>{{ $item->product_name_snapshot }}</div>
        <div class="row"><span>{{ $item->quantity }} x {{ number_format((float) $item->selected_price, 0, ',', '.') }}</span><span>{{ number_format((float) $item->line_total, 0, ',', '.') }}</span></div>
    @endforeach
    <div class="line"></div>
    <div class="row"><strong>Total</strong><strong>{{ number_format((float) $sale->grand_total_amount, 0, ',', '.') }}</strong></div>
    <div class="row"><span>Bayar</span><span>{{ number_format((float) $sale->paid_amount, 0, ',', '.') }}</span></div>
    <div class="row"><span>Kembali</span><span>{{ number_format((float) $sale->change_amount, 0, ',', '.') }}</span></div>
    <div class="line"></div>
    <div class="center">Terima kasih</div>
</body>
</html>
