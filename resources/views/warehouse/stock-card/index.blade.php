@extends('layouts.metronic.app')

@section('title', 'Kartu Stok - ' . config('app.name'))
@section('page_title', 'Kartu Stok Produk')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-card" title="Panduan Halaman Kartu Stok Produk">
        <x-slot:function>
            <p>Halaman baca-saja ini menampilkan ledger mutasi stok secara kronologis. Gudang dan Owner menggunakannya untuk menelusuri mengapa saldo produk berubah, dokumen asalnya, lokasi, serta pengguna yang menjalankan proses.</p>
            <p>Mutasi bersifat append-only: histori tidak diedit dari halaman ini. Koreksi harus dilakukan melalui dokumen koreksi, reversal, retur, atau opname yang sesuai.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih produk dan lokasi yang diperiksa.</li><li>Batasi jenis mutasi, periode, nomor referensi, atau pengguna.</li><li>Sistem mengambil mutasi sesuai scope lokasi akun.</li><li>Data diurutkan berdasarkan waktu kejadian lalu ID sehingga saldo dapat ditelusuri berurutan.</li><li>Buka <strong>Detail</strong> untuk melihat alasan, metadata, dan identitas mutasi lengkap.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Produk/Lokasi/Zona-Rak-Bin:</strong> menentukan ruang lingkup kartu stok.</li><li><strong>Jenis Mutasi:</strong> menyaring receive, issue, transfer, reservation, damage, retur, atau adjustment.</li><li><strong>Tanggal Dari/Sampai:</strong> membatasi waktu berdasarkan occurred_at.</li><li><strong>No referensi:</strong> mencari nomor dokumen asal.</li><li><strong>Semua user:</strong> mencari actor yang memproses perubahan.</li><li><strong>Masuk/Keluar:</strong> arah perubahan on hand.</li><li><strong>Before/After:</strong> saldo sebelum dan sesudah mutasi.</li><li><strong>Detail:</strong> membuka rekaman mutasi read-only.</li><li><strong>Export CSV:</strong> mengunduh ledger sesuai filter.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Membuka, memfilter, melihat detail, dan mengekspor kartu stok tidak mengubah saldo. Setiap baris merupakan bukti perubahan dari modul sumber. Karena histori append-only, koreksi menghasilkan mutasi baru sehingga jejak before dan after tetap dapat diaudit.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih satu produk agar penelusuran lebih jelas.</li><li>Pilih lokasi kerja dan bin bila produk tersimpan di beberapa tempat.</li><li>Tentukan rentang tanggal yang relevan.</li><li>Tambahkan jenis, referensi, atau user bila diperlukan.</li><li>Klik <strong>Filter</strong> dan cocokkan After satu baris dengan Before baris berikutnya pada scope yang sama.</li><li>Buka Detail untuk memeriksa dokumen dan alasan.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Jangan mencampur lokasi berbeda saat merekonsiliasi saldo berjalan.</li><li>Masuk/Keluar pada tabel menunjukkan perubahan on hand; reservation dan damage juga memiliki rincian pada detail mutasi.</li><li>Periksa tanggal dan zona waktu sebelum menyimpulkan transaksi hilang.</li><li>Jangan menghapus histori untuk memperbaiki saldo.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Saldo awal Beras adalah 20 unit. Receipt menambah 10 sehingga After menjadi 30. Transfer keluar 4 unit berikutnya memiliki Before 30 dan After 26. Nomor referensi menghubungkan kedua mutasi ke dokumen sumber masing-masing.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('toolbar_actions')
    <a href="{{ route('warehouse.stock-card.export', request()->query()) }}" class="btn btn-light-primary"><i class="ki-outline ki-file-down"></i> Export CSV</a>
@endsection

@section('content')
    <x-metronic.card>
        <form method="GET" class="row g-3 mb-5">
            <div class="col-md-3"><select name="product_id" class="form-select form-select-solid"><option value="">Semua produk</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="work_location_id" class="form-select form-select-solid"><option value="">Semua lokasi</option>@foreach ($workLocations as $location)<option value="{{ $location->id }}" @selected(($filters['work_location_id'] ?? '') == $location->id)>{{ $location->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><select name="mutation_type" class="form-select form-select-solid"><option value="">Semua jenis</option>@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(($filters['mutation_type'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-2"><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-solid"></div>
            <div class="col-md-1"><button class="btn btn-light-primary w-100">Filter</button></div>
            <div class="col-md-3"><select name="warehouse_location_id" class="form-select form-select-solid"><option value="">Semua zona/rak/bin</option>@foreach ($warehouseLocations as $location)<option value="{{ $location->id }}" @selected(($filters['warehouse_location_id'] ?? '') == $location->id)>{{ $location->full_code }}</option>@endforeach</select></div>
            <div class="col-md-3"><input name="reference_no" value="{{ $filters['reference_no'] ?? '' }}" class="form-control form-control-solid" placeholder="No referensi"></div>
            <div class="col-md-3"><select name="user_id" class="form-select form-select-solid"><option value="">Semua user</option>@foreach ($users as $user)<option value="{{ $user->id }}" @selected(($filters['user_id'] ?? '') == $user->id)>{{ $user->name }}</option>@endforeach</select></div>
        </form>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Waktu</th><th>Produk</th><th>Lokasi</th><th>Jenis</th><th>Masuk</th><th>Keluar</th><th>Before</th><th>After</th><th>Referensi</th><th>User</th><th></th></tr></thead>
                <tbody>
                @forelse ($mutations as $mutation)
                    <tr>
                        <td>{{ $mutation->occurred_at?->format('d/m/Y H:i:s') }}</td>
                        <td>{{ $mutation->product?->sku }}<div class="text-muted">{{ $mutation->product?->name }}</div></td>
                        <td>{{ $mutation->warehouseLocation?->full_code ?: $mutation->workLocation?->name }}</td>
                        <td>{{ $mutation->mutation_type->label() }}</td>
                        <td class="text-success">{{ (float) $mutation->quantity_on_hand_change > 0 ? qty($mutation->quantity_on_hand_change) : '-' }}</td>
                        <td class="text-danger">{{ (float) $mutation->quantity_on_hand_change < 0 ? qty(abs((float) $mutation->quantity_on_hand_change)) : '-' }}</td>
                        <td>{{ qty($mutation->quantity_on_hand_before) }}</td>
                        <td class="fw-bold">{{ qty($mutation->quantity_on_hand_after) }}</td>
                        <td>{{ $mutation->reference_no ?: '-' }}</td>
                        <td>{{ $mutation->actor?->name ?: '-' }}</td>
                        <td class="text-end"><a href="{{ route('warehouse.stock-mutations.show', $mutation) }}" class="btn btn-sm btn-light">Detail</a></td>
                    </tr>
                @empty
                    <tr><td colspan="11"><x-metronic.empty-state title="Belum ada mutasi" description="Kartu stok akan tampil setelah ada transaksi inventory." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $mutations->links() }}
    </x-metronic.card>
@endsection
