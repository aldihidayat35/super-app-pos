<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Barcode Produk</title>
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
    </style>
    @include('admin.products.partials.barcode-label-styles', ['paperSize' => $paperSize])
</head>
<body class="barcode-pdf-body">
    @include('admin.products.partials.barcode-sheet', [
        'labelPages' => $labelPages,
        'paperSize' => $paperSize,
    ])
</body>
</html>
