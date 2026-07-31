@extends('layouts.metronic.app')

@section('title', 'Counting Stok Opname - ' . config('app.name'))
@section('page_title', 'Counting Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-count" title="Panduan Halaman Counting Stok Opname">
        <x-slot:function>
            <p>Halaman ini digunakan untuk melakukan counting fisik stok opname. Counter memasukkan qty fisik, alasan selisih, catatan, dan bukti foto. Proses ini bisa dilakukan manual per item atau via import CSV.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Halaman menampilkan daftar item dengan qty sistem (kecuali blind count).</li><li>Counter menghitung fisik dan memasukkan qty pada form.</li><li>Pilih alasan selisih, isi catatan, dan unggah bukti.</li><li>Simpan per item.</li><li>Setelah semua item tercounting, submit ke approval.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Import Count CSV:</strong> upload file CSV hasil counting.</li><li><strong>SKU:</strong> produk yang di-counting.</li><li><strong>Lokasi:</strong> bin/zak penyimpanan.</li><li><strong>Sistem:</strong> qty tercatat (atau Blind jika blind count aktif).</li><li><strong>Fisik:</strong> qty hasil counting fisik (wajib).</li><li><strong>Alasan:</strong> kategori selisih.</li><li><strong>Catatan Counter:</strong> keterangan tambahan.</li><li><strong>Bukti:</strong> foto bukti counting.</li><li><strong>Counter:</strong> user dan waktu counting.</li><li><strong>Ada Transaksi Setelah Snapshot:</strong> peringatan item berubah setelah snapshot.</li><li><strong>Simpan:</strong> menyimpan hasil counting per item.</li><li><strong>Submit ke Approval:</strong> mengirim hasil ke approval.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Counting disimpan untuk setiap item. Setelah submit, opname masuk ke pipeline approval. Selisih dihitung antara sistem dan fisik.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Untuk item yang sama, periksa qty sistem (jika tidak blind count).</li><li>Masukkan qty fisik hasil penghitungan.</li><li>Pilih alasan jika ada selisih.</li><li>Isi catatan dan unggah bukti foto.</li><li>Klik <strong>Simpan</strong> per item.</li><li>Setelah semua item, klik <strong>Submit ke Approval</strong>.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Qty fisik wajib diisi untuk setiap item.</li><li>Perhatikan item dengan badge "Ada transaksi setelah snapshot".</li><li>Alasan harus diisi jika ada selisih signifikan.</li><li>Bukti foto memudahkan verifikasi.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Kopi Robusta sistem 100, fisik dihitung 97. Selisih -3. Alasan: "Penjualan belum diposting". Catatan: "Stok di lantai 2 belum terhitung". Foto diunggah. Simpan.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="'Counting ' . $opname->number" :description="'Progress ' . $opname->countedProgress() . ' — ' . ($opname->blind_count ? 'Blind count aktif' : 'Qty sistem ditampilkan')">
        <x-slot:actions>
            <a href="{{ route('warehouse.stock-opnames.show', $opname) }}" class="btn btn-light">Detail</a>
            <a href="{{ route('warehouse.stock-opnames.variance', $opname) }}" class="btn btn-light-info">Variance</a>
        </x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card title="Import Count CSV" class="mb-6">
        <form method="POST" action="{{ route('warehouse.stock-opnames.import', $opname) }}" enctype="multipart/form-data" class="row g-3 align-items-end">
            @csrf
            <div class="col-md-8"><x-metronic.form-group name="import_file" label="File CSV"><input type="file" name="import_file" class="form-control form-control-solid" accept=".csv,text/csv"></x-metronic.form-group><div class="text-muted fs-8">Header wajib: sku,counted_qty,reason,note. Reason: {{ implode(', ', array_keys($reasons)) }}.</div></div>
            <div class="col-md-4"><button class="btn btn-light-primary w-100">Import Count</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Daftar Counting">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th class="text-end">Sistem</th><th class="text-end">Fisik</th><th>Alasan & Bukti</th><th>Counter</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @foreach($opname->items as $item)
                    <tr>
                        <form method="POST" action="{{ route('warehouse.stock-opnames.count-item', [$opname, $item]) }}" enctype="multipart/form-data">
                            @csrf
                            <td class="fw-bold">{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div>@if($item->has_transaction_after_snapshot)<span class="badge badge-light-warning mt-1">Ada transaksi setelah snapshot</span>@endif</td>
                            <td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td>
                            <td class="text-end">{{ $opname->blind_count ? 'Blind' : qty($item->system_qty_snapshot) }}</td>
                            <td class="text-end"><input type="number" step="1" min="0" name="counted_qty" value="{{ old('counted_qty', qty_input($item->counted_qty)) }}" class="form-control form-control-solid text-end" required></td>
                            <td>
                                <select name="reason" class="form-select form-select-solid mb-2">
                                    <option value="">Tidak ada selisih</option>
                                    @foreach($reasons as $value => $label)
                                        <option value="{{ $value }}" @selected(old('reason', $item->reason?->value) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <input name="note" value="{{ old('note', $item->note) }}" class="form-control form-control-solid mb-2" placeholder="Catatan counter">
                                <input type="file" name="evidence" class="form-control form-control-solid" accept=".jpg,.jpeg,.png,.pdf">
                            </td>
                            <td>{{ $item->counter?->name ?: '-' }}<div class="text-muted fs-8">{{ $item->counted_at?->format('d/m/Y H:i') ?: '-' }}</div></td>
                            <td class="text-end"><button class="btn btn-sm btn-primary">Simpan</button></td>
                        </form>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <x-slot:footer>
            <form method="POST" action="{{ route('warehouse.stock-opnames.submit', $opname) }}" class="text-end">@csrf<button class="btn btn-success">Submit ke Approval</button></form>
        </x-slot:footer>
    </x-metronic.card>
@endsection
