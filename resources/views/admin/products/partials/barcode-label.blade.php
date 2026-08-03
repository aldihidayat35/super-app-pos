<div class="barcode-label-shell" data-barcode-label>
    <div class="barcode-product-name">{{ $label['product_name'] }}</div>
    <table class="barcode-meta">
        <tr>
            <td>SKU: {{ $label['sku'] }} &nbsp;|&nbsp; Satuan: {{ $label['unit'] }}</td>
        </tr>
    </table>
    <div class="barcode-graphic {{ $label['is_qr'] ? 'barcode-graphic--qr' : 'barcode-graphic--linear' }}">
        <img src="data:image/png;base64,{{ $label['image'] }}" alt="Barcode {{ $label['code'] }}">
    </div>
    <div class="barcode-code">{{ $label['code'] }}</div>
</div>
