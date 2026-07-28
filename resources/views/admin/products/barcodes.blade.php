@extends('layouts.metronic.app')
@section('title', 'Cetak Barcode/QR')
@section('page_title', 'Cetak Barcode/QR')
@section('page_guide')
    <x-metronic.page-guide id="admin-products-barcodes" title="Panduan Halaman Cetak Barcode/QR">
        <x-slot:function>
            <p class="mb-2"><strong>Fungsi utama halaman ini:</strong> Halaman untuk mencetak label barcode/QR produk secara massal dalam format PDF siap-cetak. Admin bisa memilih produk, mengatur jumlah label, ukuran kertas, dan mengunduh file PDF untuk dicetak di printer label.</p>
            
            <p class="mb-2"><strong>Siapa yang menggunakan halaman ini:</strong></p>
            <ul class="mb-0">
                <li><strong>Staff Gudang:</strong> Mencetak label barcode untuk ditempel pada produk fisik.</li>
                <li><strong>Admin:</strong> Cetak ulang label untuk produk baru atau barcode yang rusak.</li>
            </ul>
        </x-slot:function>
        <x-slot:workflow>
            <p class="mb-3"><strong>Halaman ini terhubung erat dengan modul-modul berikut:</strong></p>
            <ul class="mb-0">
                <li><strong>Detail Produk:</strong> Barcode yang terdaftar di sini berasal dari halaman detail produk (Tab Barcode).</li>
                <li><strong>Kasir / POS:</strong> Barcode yang dicetak akan digunakan untuk scan produk di halaman kasir.</li>
                <li><strong>Printer Label:</strong> Output PDF bisa langsung dikirim ke printer thermal atau laser.</li>
            </ul>
        </x-slot:workflow>
        <x-slot:parts>
            <p class="mb-3"><strong>Cara kerja halaman ini:</strong></p>
            <ol class="mb-0">
                <li>Admin memilih produk (atau biarkan "Semua Produk Aktif").</li>
                <li>Mengatur jumlah label per produk (1-100).</li>
                <li>Memilih ukuran kertas (A4 untuk printer laser, Thermal untuk printer label thermal).</li>
                <li>Klik "Preview" untuk melihat hasil.</li>
                <li>Klik "Cetak PDF" untuk mengunduh file PDF siap-cetak.</li>
            </ol>
        </x-slot:parts>
        <x-slot:impacts>
            <p class="mb-3"><strong>Dampak dari tindakan di halaman ini:</strong></p>
            <ul class="mb-0">
                <li>Cetak barcode tidak mengubah data di database. Hanya menghasilkan file PDF siap-cetak.</li>
                <li>Setiap download dicatat di activity log untuk audit.</li>
            </ul>
        </x-slot:impacts>
        <x-slot:operation>
            <p class="mb-3"><strong>Alur Pengoperasian Lengkap:</strong></p>
            
            <p class="fw-bold mt-3">Mencetak Barcode Satu Produk</p>
            <ol>
                <li>Pilih produk spesifik dari dropdown "Produk".</li>
                <li>Atur jumlah label (misal 50).</li>
                <li>Pilih ukuran kertas (A4 untuk printer laser).</li>
                <li>Klik "Preview" untuk melihat tampilan label.</li>
                <li>Klik "Cetak PDF" untuk mendownload.</li>
                <li>Buka file PDF dan cetak di printer label.</li>
            </ol>
            
            <p class="fw-bold mt-3">Mencetak Semua Produk Aktif</p>
            <ol>
                <li>Kosongkan dropdown "Produk" (biarkan "Semua Produk Aktif").</li>
                <li>Atur jumlah label (default 1).</li>
                <li>Klik "Preview" — akan muncul barcode dari semua produk aktif.</li>
                <li>Klik "Cetak PDF" untuk mendownload PDF berisi barcode semua produk.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <p class="mb-3"><strong>Peringatan Penting:</strong></p>
            <div class="alert alert-warning mb-0">
                <ul class="mb-0">
                    <li><strong>Tanpa Barcode:</strong> Produk yang tidak punya barcode akan otomatis menggunakan SKU sebagai barcode. Pastikan SKU adalah kode valid untuk discan.</li>
                    <li><strong>Ukuran Kertas Thermal:</strong> Pilih ukuran "thermal" hanya jika Anda punya printer thermal label. Format thermal lebih kecil dari A4.</li>
                    <li><strong>Limit 200 Produk:</strong> Dropdown hanya menampilkan 200 produk pertama. Untuk produk lebih banyak, gunakan filter di daftar produk.</li>
                    <li><strong>Jumlah Label:</strong> Maksimal 100 label per produk per download. Untuk lebih banyak, download berulang kali.</li>
                </ul>
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p class="mb-3"><strong>Contoh Penggunaan Nyata:</strong></p>
            <p><strong>Skenario — Mencetak Label 50 Produk Baru:</strong></p>
            <ol>
                <li>Staff gudang membuka halaman Cetak Barcode.</li>
                <li>Pilih produk tertentu dari dropdown.</li>
                <li>Set jumlah label = 50.</li>
                <li>Pilih ukuran kertas A4.</li>
                <li>Klik "Preview" &rarr; tampil 50 label identik di layar.</li>
                <li>Klik "Cetak PDF".</li>
                <li>File PDF di-download, lalu dicetak di printer.</li>
                <li>Label dipotong dan ditempel ke produk fisik.</li>
            </ol>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content')
<x-metronic.card title="Pengaturan Cetak">
    <form method="GET" class="row g-3 align-items-end">
        <div class="col-md-5"><label class="form-label">Produk</label><select name="product_id" class="form-select"><option value="">Semua Produk Aktif</option>@foreach(App\Models\Product::query()->orderBy('name')->limit(200)->get() as $option)<option value="{{ $option->id }}" @selected((int) $selectedProductId === $option->id)>{{ $option->sku }} Â· {{ $option->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Jumlah Label</label><input type="number" min="1" max="100" name="label_count" value="{{ $labelCount }}" class="form-control"></div>
        <div class="col-md-3"><label class="form-label">Ukuran Kertas</label><select name="paper_size" class="form-select"><option value="A4" @selected($paperSize === 'A4')>A4</option><option value="thermal" @selected($paperSize === 'thermal')>Thermal</option></select></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Preview</button></div>
    </form>
</x-metronic.card>
<x-metronic.card title="Preview Label" class="mt-6">
    <div class="row g-4">
        @forelse($products as $product)
            @php($barcode = $product->barcodes->first())
            <div class="col-md-3"><div class="border rounded p-4 text-center"><div class="fw-bold">{{ $product->name }}</div><div class="text-muted small">{{ $product->sku }}</div><div class="my-3 d-flex justify-content-center">@if($barcode?->type === 'qr'){!! (new Milon\Barcode\DNS2D())->getBarcodeHTML($barcode->code, 'QRCODE', 3, 3) !!}@else{!! (new Milon\Barcode\DNS1D())->getBarcodeHTML($barcode?->code ?: $product->sku, 'C128', 1.5, 45) !!}@endif</div><div class="font-monospace">{{ $barcode?->code ?: $product->sku }}</div><div class="text-muted small">{{ $product->baseUnit?->symbol }}</div></div></div>
        @empty
            <x-metronic.empty-state title="Tidak ada produk" description="Pilih produk yang memiliki barcode atau tambahkan barcode di detail produk." />
        @endforelse
    </div>
    <a href="{{ route('admin.products.barcodes.pdf', ['product_id' => $selectedProductId, 'label_count' => $labelCount, 'paper_size' => $paperSize]) }}" class="btn btn-light-primary mt-4">Cetak PDF</a>
</x-metronic.card>
@endsection

