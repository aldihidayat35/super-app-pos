@extends('layouts.metronic.app')

@section('title', 'Penghitungan Stok Opname - ' . config('app.name'))
@section('page_title', 'Penghitungan Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-count" title="Panduan Penghitungan Fisik">
        <x-slot:function><p>Petugas memasukkan jumlah barang yang benar-benar ditemukan di lokasi. Sistem menyimpan pelaku, waktu, alasan selisih, catatan, dan bukti pendukung.</p></x-slot:function>
        <x-slot:workflow><ol><li>Hitung barang pada lokasi yang tercantum.</li><li>Masukkan Jumlah Fisik.</li><li>Jika berbeda, pilih alasan dan tambahkan bukti.</li><li>Simpan tiap baris.</li><li>Setelah seluruh item dihitung, ajukan persetujuan.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Stok Sistem:</strong> angka saat acuan stok disimpan; disembunyikan pada mode objektif.</li><li><strong>Jumlah Fisik:</strong> hasil hitung lapangan.</li><li><strong>Selisih:</strong> jumlah fisik dikurangi stok sistem.</li><li><strong>Petugas Penghitung:</strong> pengguna yang menyimpan hasil fisik.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Menyimpan hasil hitung belum mengubah stok. Perubahan saldo baru dibuat setelah hasil disetujui dan opname diselesaikan.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Masukkan hasil fisik.</li><li>Lengkapi alasan bila berselisih.</li><li>Klik Simpan.</li><li>Ajukan persetujuan setelah kemajuan mencapai 100%.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0">Item yang mempunyai transaksi setelah acuan stok harus diperiksa khusus sebelum persetujuan.</div></x-slot:warnings>
        <x-slot:example><p>Stok sistem 100 dan jumlah fisik 97 menghasilkan status “Kurang 3”. Pilih alasan, tambahkan catatan, lalu simpan.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="'Penghitungan ' . $opname->number" :description="'Kemajuan ' . $opname->countedProgress() . ' — ' . ($opname->blind_count ? 'Mode penghitungan objektif aktif' : 'Stok sistem ditampilkan')">
        <x-slot:actions>
            <a href="{{ route('warehouse.stock-opnames.show', $opname) }}" class="btn btn-light">Detail</a>
            <a href="{{ route('warehouse.stock-opnames.variance', $opname) }}" class="btn btn-light-info">Selisih Stok</a>
        </x-slot:actions>
    </x-metronic.page-title>

    <div class="alert alert-light-primary border border-primary border-dashed">Hitung barang secara fisik pada lokasi yang tercantum, lalu masukkan hasilnya pada kolom <strong>Jumlah Fisik</strong>. Jika terdapat selisih, pilih alasan dan unggah bukti jika diperlukan.</div>
    @if($opname->blind_count)<div class="alert alert-info">Stok sistem disembunyikan karena mode penghitungan objektif aktif.</div>@endif

    <x-metronic.card title="Import Hasil Penghitungan CSV" class="mb-6">
        <form method="POST" action="{{ route('warehouse.stock-opnames.import', $opname) }}" enctype="multipart/form-data" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-8"><x-metronic.form-group name="import_file" label="File CSV"><input type="file" name="import_file" class="form-control form-control-solid" accept=".csv,text/csv"></x-metronic.form-group><div class="text-muted fs-8">Kolom wajib: sku,counted_qty,reason,note. Pilihan reason: {{ implode(', ', array_keys($reasons)) }}.</div></div>
            <div class="col-md-4"><button class="btn btn-light-primary w-100">Import Hasil</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Penghitungan">
        @foreach($opname->items as $item)
            <form id="count-item-{{ $item->id }}" method="POST" action="{{ route('warehouse.stock-opnames.count-item', [$opname, $item]) }}" enctype="multipart/form-data" class="d-none">@csrf</form>
        @endforeach
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th class="text-end">Stok Sistem</th><th class="text-end">Jumlah Fisik</th><th class="text-end">Selisih</th><th>Alasan & Bukti Pendukung</th><th>Petugas Penghitung <i class="ki-outline ki-information-5 fs-7" data-bs-toggle="tooltip" title="Pengguna yang memasukkan hasil penghitungan fisik."></i></th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @foreach($opname->items as $item)
                    <tr>
                            <td class="fw-bold">{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div>@if($item->has_transaction_after_snapshot)<span class="badge badge-light-warning mt-1">Ada transaksi setelah acuan stok</span>@endif</td>
                            <td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td>
                            <td class="text-end">{{ $opname->blind_count ? 'Disembunyikan' : qty($item->system_qty_snapshot) }}</td>
                            <td class="text-end"><input form="count-item-{{ $item->id }}" type="number" step="1" min="0" name="counted_qty" value="{{ old('counted_qty', qty_input($item->counted_qty)) }}" class="form-control form-control-solid text-end counted-input" @unless($opname->blind_count)data-system-qty="{{ qty_input($item->system_qty_snapshot) }}"@endunless required></td>
                            <td class="text-end"><span class="badge difference-badge badge-light">{{ $opname->blind_count ? 'Dihitung setelah disimpan' : 'Belum dihitung' }}</span></td>
                            <td>
                                <select form="count-item-{{ $item->id }}" name="reason" class="form-select form-select-solid mb-2">
                                    <option value="">Tidak ada selisih</option>
                                    @foreach($reasons as $value => $label)<option value="{{ $value }}" @selected(old('reason', $item->reason?->value) === $value)>{{ $label }}</option>@endforeach
                                </select>
                                <input form="count-item-{{ $item->id }}" name="note" value="{{ old('note', $item->note) }}" class="form-control form-control-solid mb-2" placeholder="Catatan petugas penghitung">
                                <input form="count-item-{{ $item->id }}" type="file" name="evidence" class="form-control form-control-solid" accept=".jpg,.jpeg,.png,.pdf">
                                @if($item->evidence_path)<a href="{{ Storage::disk('public')->url($item->evidence_path) }}" target="_blank" class="btn btn-sm btn-light mt-2">Lihat Bukti Tersimpan</a>@endif
                            </td>
                            <td>{{ $item->counter?->name ?: '-' }}<div class="text-muted fs-8">{{ $item->counted_at?->format('d/m/Y H:i') ?: '-' }}</div></td>
                            <td class="text-end"><button form="count-item-{{ $item->id }}" class="btn btn-sm btn-primary">Simpan</button></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <x-slot:footer><form method="POST" action="{{ route('warehouse.stock-opnames.submit', $opname) }}" class="text-end">@csrf<button class="btn btn-success" @disabled($opname->items->contains(fn($item) => $item->counted_qty === null))>Ajukan Persetujuan</button>@if($opname->items->contains(fn($item) => $item->counted_qty === null))<div class="text-muted fs-8 mt-2">Semua item harus dihitung sebelum diajukan.</div>@endif</form></x-slot:footer>
    </x-metronic.card>
@endsection

@unless($opname->blind_count)
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.counted-input[data-system-qty]').forEach((input) => {
        const badge = input.closest('tr').querySelector('.difference-badge');
        const refresh = () => {
            if (input.value === '') { badge.className = 'badge badge-light difference-badge'; badge.textContent = 'Belum dihitung'; return; }
            const difference = Number(input.value) - Number(input.dataset.systemQty);
            badge.className = `badge difference-badge ${difference > 0 ? 'badge-light-success' : (difference < 0 ? 'badge-light-danger' : 'badge-light-primary')}`;
            badge.textContent = difference > 0 ? `Lebih ${difference}` : (difference < 0 ? `Kurang ${Math.abs(difference)}` : 'Sesuai');
        };
        input.addEventListener('input', refresh); refresh();
    });
});
</script>
@endpush
@endunless
