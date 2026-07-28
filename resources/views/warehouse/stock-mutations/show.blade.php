@extends('layouts.metronic.app')

@section('title', 'Detail Mutasi Stok - ' . config('app.name'))
@section('page_title', 'Detail Mutasi Stok')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-mutation-show" title="Panduan Halaman Detail Mutasi Stok">
        <x-slot:function>
            <p>Halaman ini menampilkan rincian satu mutasi stok spesifik secara read-only. Mutasi adalah jejak perubahan stok yang tercipta dari proses penerimaan, pengeluaran, transfer, reservasi, kerusakan, retur, atau penyesuaian.</p>
            <p>Halaman ini berguna untuk audit dan menelusuri alasan di balik perubahan saldo produk pada waktu tertentu.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Halaman dibuka dari link Detail pada Kartu Stok atau Transfer.</li><li>Sistem menampilkan semua informasi mutasi: waktu, produk, lokasi, perubahan on hand/reserved/damaged, referensi dokumen, dan metadata audit.</li><li>Pengguna memeriksa rincian untuk memahami perubahan stok.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Waktu:</strong> saat mutasi terjadi.</li><li><strong>Produk:</strong> SKU dan nama produk yang berubah.</li><li><strong>Satuan Dasar:</strong> satuan pengukuran produk.</li><li><strong>Lokasi Kerja:</strong> gudang atau cabang tempat mutasi.</li><li><strong>Zona/Rak/Bin:</strong> lokasi fisik spesifik.</li><li><strong>Actor:</strong> pengguna yang menjalankan proses.</li><li><strong>On Hand Before/Change/After:</strong> saldo fisik sebelum, perubahan, dan sesudah.</li><li><strong>Reserved Before/Change/After:</strong> saldo reserved sebelum dan sesudah.</li><li><strong>Damaged Before/Change/After:</strong> saldo rusak sebelum dan sesudah.</li><li><strong>Referensi:</strong> jenis dan nomor dokumen asal mutasi.</li><li><strong>Idempotency Key:</strong> kunci unik mencegah duplikasi.</li><li><strong>Catatan:</strong> alasan atau keterangan perubahan.</li><li><strong>Metadata Audit:</strong> JSON lengkap data tambahan mutasi.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Halaman ini hanya menampilkan informasi dan tidak mengubah data apapun. Mutasi bersifat append-only; koreksi dilakukan melalui dokumen baru bukan peng editan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Buka halaman dari detail mutasi yang ingin diperiksa.</li><li>Periksa Waktu, Produk, Lokasi, dan Jenis mutasi.</li><li>Bandingseluruhi nilai Before, Change, dan After untuk memastikan perubahan benar.</li><li>Lihat Referensi untuk mengetahui dokumen sumber.</li><li>Periksa Metadata Audit jika diperlukan investigasi mendalam.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Halaman ini read-only; Anda tidak dapat mengedit atau menghapus mutasi dari sini.</li><li>Jika ada ketidaksesuaian, buat dokumen koreksi resmi bukan mengubah data manual.</li><li>Metadata audit bersifat teknis; hubungi administrator jika diperlukan interpretasi.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Mutasi transfer keluar menunjukkan On Hand Before 100, Change -10, After 90. Referensi ST-2024-001 mengaitkan mutasi ini dengan Surat Transfer nomor tersebut.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.card title="{{ $mutation->mutation_type->label() }}">
        <div class="row g-5">
            <div class="col-md-4"><div class="text-muted">Waktu</div><div class="fw-bold">{{ $mutation->occurred_at?->format('d/m/Y H:i:s') }}</div></div>
            <div class="col-md-4"><div class="text-muted">Produk</div><div class="fw-bold">{{ $mutation->product?->sku }} — {{ $mutation->product?->name }}</div></div>
            <div class="col-md-4"><div class="text-muted">Satuan Dasar</div><div class="fw-bold">{{ $mutation->product?->baseUnit?->name ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Lokasi Kerja</div><div class="fw-bold">{{ $mutation->workLocation?->name }}</div></div>
            <div class="col-md-4"><div class="text-muted">Zona/Rak/Bin</div><div class="fw-bold">{{ $mutation->warehouseLocation?->full_code ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Actor</div><div class="fw-bold">{{ $mutation->actor?->name ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">On Hand Before / Change / After</div><div class="fw-bold">{{ qty($mutation->quantity_on_hand_before) }} / {{ qty($mutation->quantity_on_hand_change) }} / {{ qty($mutation->quantity_on_hand_after) }}</div></div>
            <div class="col-md-4"><div class="text-muted">Reserved Before / Change / After</div><div class="fw-bold">{{ qty($mutation->quantity_reserved_before) }} / {{ qty($mutation->quantity_reserved_change) }} / {{ qty($mutation->quantity_reserved_after) }}</div></div>
            <div class="col-md-4"><div class="text-muted">Damaged Before / Change / After</div><div class="fw-bold">{{ qty($mutation->quantity_damaged_before) }} / {{ qty($mutation->quantity_damaged_change) }} / {{ qty($mutation->quantity_damaged_after) }}</div></div>
            <div class="col-md-4"><div class="text-muted">Referensi</div><div class="fw-bold">{{ $mutation->reference_type ?: '-' }} / {{ $mutation->reference_no ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Idempotency Key</div><div class="fw-bold">{{ $mutation->idempotency_key ?: '-' }}</div></div>
            <div class="col-md-4"><div class="text-muted">Catatan</div><div class="fw-bold">{{ $mutation->reason ?: '-' }}</div></div>
        </div>
        <div class="separator my-6"></div>
        <h4>Metadata Audit</h4>
        <pre class="bg-light p-4 rounded">{{ json_encode($mutation->metadata ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        <a href="{{ route('warehouse.stock-card.index', ['product_id' => $mutation->product_id]) }}" class="btn btn-light">Kembali ke Kartu Stok</a>
    </x-metronic.card>
@endsection
