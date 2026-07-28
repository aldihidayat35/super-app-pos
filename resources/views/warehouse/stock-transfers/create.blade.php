@extends('layouts.metronic.app')

@section('title', 'Buat Transfer Stok - ' . config('app.name'))
@section('page_title', 'Form dan Approval Transfer')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-transfer-create" title="Panduan Form Buat Transfer Stok">
        <x-slot:function>
            <p>Form ini digunakan untuk membuat transfer stok baru antar lokasi. Pengguna mengisi sumber, tujuan, dan item yang akan dipindahkan. Transfer dapat disimpan sebagai draft atau langsung submitted untuk proses approval.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Pilih sumber, tujuan, dan lokasi default bin.</li><li>Tentukan tanggal dan catatan opsional.</li><li>Tambahkan item produk dan qty diminta/approved.</li><li>Simpan sebagai Draft untuk review atau Submit untuk approval.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Sumber:</strong> lokasi kerja asal (wajib).</li><li><strong>Tujuan:</strong> lokasi kerja tujuan (wajib).</li><li><strong>Lokasi Ambil Default:</strong> bin asal default jika item tidak spesifik.</li><li><strong>Lokasi Tujuan Default:</strong> bin tujuan default untuk semua item.</li><li><strong>Tanggal:</strong> tanggal pelaksanaan transfer (wajib).</li><li><strong>Catatan:</strong> informasi tambahan transfer.</li><li><strong>Produk:</strong> memilih produk untuk tiap baris item.</li><li><strong>Qty Diminta:</strong> jumlah yang diminta pengaju.</li><li><strong>Qty Approved:</strong> jumlah yang disetujui approver.</li><li><strong>Lokasi Ambil/Tujuan:</strong> bin spesifik per item.</li><li><strong>Catatan Item:</strong> informasi khusus item.</li><li><strong>Simpan Draft:</strong> menyimpan tanpa submit approval.</li><li><strong>Submit Approval:</strong> mengirim ke approver.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Transfer draft belum menambah stok masuk/keluar. Setelah di-approve, sistem melakukan reserve stok sumber sesuai qty approved. Pengiriman akan mengeluarkan stok sumber.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Pilih lokasi sumber dan tujuan.</li><li>Tentukan tanggal dan tambahkan catatan jika perlu.</li><li>Untuk setiap produk yang akan dipindahkan, isi qty diminta dan qty approved.</li><li>Pilih bin spesifik jika berbeda dari default.</li><li>Klik <strong>Simpan Draft</strong> untuk menyimpan atau <strong>Submit Approval</strong> untuk melanjutkan proses.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Minimal harus ada satu item dengan qty lebih dari nol.</li><li>Sumber dan tujuan harus berbeda.</li><li>Lokasi harus sesuai scope akun Anda.</li><li>Qty diminta dan qty approved mempengaruhi stok di proses berikutnya.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Transfer 20 unit Kopi dari Gudang Pusat ke Cabang Bogor pada 18/07/2025. Simpan Draft dulu untuk review, lalu Submit Approval agar Kepala Gudang dapat menyetujui qty.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.card title="Transfer Baru">
        <form method="POST" action="{{ route('warehouse.stock-transfers.store') }}">
            @csrf
            <div class="row g-4">
                <div class="col-md-3"><label class="form-label required">Sumber</label><select name="source_work_location_id" class="form-select form-select-solid" required>@foreach($workLocations as $location)<option value="{{ $location->id }}">{{ $location->typeLabel() }} — {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label required">Tujuan</label><select name="destination_work_location_id" class="form-select form-select-solid" required>@foreach($allWorkLocations as $location)<option value="{{ $location->id }}">{{ $location->typeLabel() }} — {{ $location->name }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Lokasi Ambil Default</label><select name="source_warehouse_location_id" class="form-select form-select-solid"><option value="">Default sumber</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label">Lokasi Tujuan Default</label><select name="destination_warehouse_location_id" class="form-select form-select-solid"><option value="">Default tujuan</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></div>
                <div class="col-md-3"><label class="form-label required">Tanggal</label><input type="date" name="transfer_date" value="{{ now()->toDateString() }}" class="form-control form-control-solid" required></div>
                <div class="col-md-9"><label class="form-label">Catatan</label><input name="notes" class="form-control form-control-solid"></div>
            </div>

            <div class="separator my-6"></div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Qty Diminta</th><th>Qty Approved</th><th>Lokasi Ambil</th><th>Lokasi Tujuan</th><th>Catatan</th></tr></thead>
                    <tbody>
                    @for($i = 0; $i < 5; $i++)
                        <tr>
                            <td><select name="items[{{ $i }}][product_id]" class="form-select form-select-sm"><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></td>
                            <td><input name="items[{{ $i }}][quantity_requested]" type="number" step="1" min="0" value="{{ $i === 0 ? '1' : '' }}" class="form-control form-control-sm"></td>
                            <td><input name="items[{{ $i }}][quantity_approved]" type="number" step="1" min="0" value="{{ $i === 0 ? '1' : '' }}" class="form-control form-control-sm"></td>
                            <td><select name="items[{{ $i }}][source_warehouse_location_id]" class="form-select form-select-sm"><option value="">Default</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></td>
                            <td><select name="items[{{ $i }}][destination_warehouse_location_id]" class="form-select form-select-sm"><option value="">Default</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></td>
                            <td><input name="items[{{ $i }}][notes]" class="form-control form-control-sm"></td>
                        </tr>
                    @endfor
                    </tbody>
                </table>
            </div>
            @if($errors->any())<div class="alert alert-danger">Periksa kembali form. Minimal satu item, qty wajib lebih dari nol, dan lokasi harus sesuai scope.</div>@endif
            <div class="d-flex justify-content-end gap-3"><button name="action" value="draft" class="btn btn-light">Simpan Draft</button><button name="action" value="submit" class="btn btn-primary">Submit Approval</button></div>
        </form>
    </x-metronic.card>
@endsection
