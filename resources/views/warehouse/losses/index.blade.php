@extends('layouts.metronic.app')
@section('title', 'Barang Rusak & Loss - ' . config('app.name'))
@section('page_title', 'Barang Rusak, Hilang, dan Kerugian')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-losses" title="Panduan Halaman Barang Rusak, Hilang, dan Kerugian">
        <x-slot:function>
            <p>Halaman ini digunakan untuk mencatat loss inventory termasuk barang pecah, hilang, expired, selisih opname, rusak, atau lainnya. Setiap loss akan menghasilkan mutasi damaged atau issue sesuai disposition yang dipilih.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih lokasi kerja dan bin tempat loss terjadi.</li><li>Pilih produk dan jenis loss.</li><li>Pilih disposition: damage (masuk rusak) atau issue (keluar stok).</li><li>Isi qty, HPP snapshot, dan bukti foto.</li><li>Simpan untuk mencatat loss dan membuat mutasi.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Lokasi:</strong> gudang/cabang tempat loss (wajib).</li><li><strong>Bin:</strong> lokasi fisik spesifik.</li><li><strong>Produk:</strong> barang yang lost (wajib).</li><li><strong>Jenis:</strong> Pecah, Hilang, Expired, Selisih Opname, Rusak, Lainnya.</li><li><strong>Mutasi:</strong> Damage (masuk damaged) atau Issue (keluar stok).</li><li><strong>Qty:</strong> jumlah loss (wajib).</li><li><strong>HPP Snapshot:</strong> harga per unit saat loss.</li><li><strong>Foto:</strong> upload bukti foto.</li><li><strong>Approval:</strong> flag untuk loss nilai besar.</li><li><strong>Catatan:</strong> keterangan loss.</li><li><strong>Proses Loss:</strong> simpan dan catat mutasi.</li><li><strong>Histori Loss:</strong> daftar loss sebelumnya.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Loss yang tercatat mengurangi on hand atau menambah damaged. Loss nilai besar memerlukan approval. Mutasi append-only dibuat otomatis.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih lokasi dan bin tempat barang loss.</li><li>Pilih produk yang lost.</li><li>Pilih jenis loss dan disposition.</li><li>Isi qty dan HPP snapshot.</li><li>Unggah foto dan tulis catatan.</li><li>Klik <strong>Proses Loss</strong>.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Qty wajib lebih dari nol.</li><li>Loss nilai besar mungkin perlu approval.</li><li>Foto bukti penting untuk audit.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Kopi Robusta di Bin A-02 pecah 3 unit. Jenis: Pecah, Disposition: Masuk damaged. HPP Rp 25.000. Foto diunggah. Simpan.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content')
    <x-metronic.page-title title="Barang Rusak, Hilang, dan Kerugian" description="Catat loss inventory dengan mutasi damaged/issue dan approval nilai besar." />
    <div class="row g-6"><div class="col-lg-4"><x-metronic.card title="Catat Loss"><form method="POST" action="{{ route('warehouse.losses.store') }}" enctype="multipart/form-data">@csrf<x-metronic.form-group name="work_location_id" label="Lokasi" required><select name="work_location_id" class="form-select">@foreach($workLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></x-metronic.form-group><x-metronic.form-group name="warehouse_location_id" label="Bin"><select name="warehouse_location_id" class="form-select"><option value="">Tanpa bin</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></x-metronic.form-group><x-metronic.form-group name="product_id" label="Produk" required><select name="product_id" class="form-select">@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></x-metronic.form-group><x-metronic.form-group name="loss_type" label="Jenis"><select name="loss_type" class="form-select"><option value="broken">Pecah</option><option value="lost">Hilang</option><option value="expired">Expired</option><option value="opname_variance">Selisih Opname</option><option value="damage">Rusak</option><option value="other">Lainnya</option></select></x-metronic.form-group><x-metronic.form-group name="disposition" label="Mutasi"><select name="disposition" class="form-select"><option value="damage">Masuk damaged</option><option value="issue">Keluar stok/loss</option></select></x-metronic.form-group><x-metronic.form-group name="quantity" label="Qty" required><input type="number" step="1" min="0" name="quantity" class="form-control" required></x-metronic.form-group><x-metronic.form-group name="unit_cost_snapshot" label="HPP Snapshot"><input type="number" step="0.01" min="0" name="unit_cost_snapshot" class="form-control"></x-metronic.form-group><x-metronic.form-group name="reason" label="Penyebab"><textarea name="reason" class="form-control"></textarea></x-metronic.form-group><button class="btn btn-primary">Simpan Loss</button></form></x-metronic.card></div><div class="col-lg-8"><x-metronic.card title="Daftar Loss"><table class="table"><thead><tr><th>No</th><th>Produk/Lokasi</th><th>Jenis</th><th>Qty</th><th>Nilai</th><th>Status</th><th></th></tr></thead><tbody>@forelse($losses as $loss)<tr><td class="fw-bold">{{ $loss->number }}</td><td>{{ $loss->product?->name }}<div class="text-muted">{{ $loss->workLocation?->name }} / {{ $loss->warehouseLocation?->full_code }}</div></td><td>{{ $loss->loss_type }} / {{ $loss->disposition }}</td><td>{{ qty($loss->quantity) }}</td><td>{{ \App\Support\CurrencyFormatter::rupiah($loss->loss_value) }}</td><td><x-metronic.status-badge :status="$loss->status" /></td><td>@can('approve', $loss)<form method="POST" action="{{ route('warehouse.losses.approve', $loss) }}">@csrf<button class="btn btn-sm btn-success">Approve</button></form>@endcan</td></tr>@empty<tr><td colspan="7"><x-metronic.empty-state title="Belum ada loss" description="Loss akan tampil setelah dicatat." /></td></tr>@endforelse</tbody></table>{{ $losses->links() }}</x-metronic.card></div></div>
@endsection
