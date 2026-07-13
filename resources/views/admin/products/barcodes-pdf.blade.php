<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        .label { width: 180px; height: 110px; border: 1px solid #111; display: inline-block; margin: 8px; padding: 8px; text-align: center; vertical-align: top; }
        .name { font-weight: bold; min-height: 28px; }
        .code { font-family: monospace; margin-top: 6px; }
        .bars { margin: 8px auto; }
    </style>
</head>
<body>
@foreach($products as $product)
    @for($i = 0; $i < $labelCount; $i++)
        @php($barcode = $product->barcodes->first())
        <div class="label">
            <div class="name">{{ $product->name }}</div>
            <div>{{ $product->sku }}</div>
            <div class="bars">
                @if($barcode?->type === 'qr')
                    {!! (new Milon\Barcode\DNS2D())->getBarcodeHTML($barcode->code, 'QRCODE', 2, 2) !!}
                @else
                    {!! (new Milon\Barcode\DNS1D())->getBarcodeHTML($barcode?->code ?: $product->sku, 'C128', 1.2, 35) !!}
                @endif
            </div>
            <div class="code">{{ $barcode?->code ?: $product->sku }}</div>
            <div>{{ $product->baseUnit?->symbol }}</div>
        </div>
    @endfor
@endforeach
</body>
</html>
