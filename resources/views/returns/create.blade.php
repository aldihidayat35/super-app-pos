@section('title', 'Pengajuan Retur - ' . config('app.name'))
@section('page_title', 'Pengajuan Retur')
@extends('layouts.metronic.app')

@section('content')
    <x-metronic.page-title title="Form Pengajuan Retur" description="Ajukan retur dengan referensi dokumen asal, item, alasan, kondisi, foto, dan solusi." />
    <form method="POST" action="{{ route('returns.store') }}" enctype="multipart/form-data">
        @csrf
        <x-metronic.card title="Header Retur" class="mb-6">
            <div class="row g-4">
                <div class="col-md-3"><x-metronic.form-group name="work_location_id" label="Lokasi Kerja" required><select name="work_location_id" class="form-select form-select-solid" required><option value="">Pilih</option>@foreach($workLocations as $location)<option value="{{ $location->id }}">{{ $location->name }}</option>@endforeach</select></x-metronic.form-group></div>
                <div class="col-md-3"><x-metronic.form-group name="source_type" label="Sumber" required><select name="source_type" class="form-select form-select-solid" required>@foreach(['branch'=>'Cabang','supplier'=>'Supplier','pos'=>'POS','b2b'=>'B2B','transfer'=>'Transfer','manual'=>'Manual'] as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></x-metronic.form-group></div>
                <div class="col-md-3"><x-metronic.form-group name="source_name" label="Nama Sumber"><input name="source_name" class="form-control form-control-solid"></x-metronic.form-group></div>
                <div class="col-md-3"><x-metronic.form-group name="return_date" label="Tanggal" required><input type="date" name="return_date" value="{{ now()->toDateString() }}" class="form-control form-control-solid" required></x-metronic.form-group></div>
                <div class="col-md-3"><x-metronic.form-group name="reference_no" label="No Dokumen Asal"><input name="reference_no" class="form-control form-control-solid"></x-metronic.form-group></div>
                <div class="col-md-3"><x-metronic.form-group name="reason" label="Alasan" required><select name="reason" class="form-select form-select-solid"><option value="broken">Pecah/Rusak</option><option value="wrong_item">Salah Kirim</option><option value="defect">Cacat</option><option value="shortage">Kurang</option><option value="other">Lainnya</option></select></x-metronic.form-group></div>
                <div class="col-md-3"><x-metronic.form-group name="requested_resolution" label="Solusi Diminta" required><select name="requested_resolution" class="form-select form-select-solid">@foreach($resolutions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></x-metronic.form-group></div>
                <div class="col-md-3"><x-metronic.form-group name="evidence" label="Foto/Bukti"><input type="file" name="evidence" class="form-control form-control-solid"></x-metronic.form-group></div>
            </div>
        </x-metronic.card>
        <x-metronic.card title="Item Retur">
            <div class="table-responsive"><table class="table"><thead><tr><th>Produk</th><th>Lokasi Masuk</th><th>Qty</th><th>Qty Asal</th><th>HPP</th><th>Kondisi</th><th>Catatan</th></tr></thead><tbody>
                @for($i=0;$i<5;$i++)
                    <tr>
                        <td><select name="items[{{ $i }}][product_id]" class="form-select form-select-solid"><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></td>
                        <td><select name="items[{{ $i }}][warehouse_location_id]" class="form-select form-select-solid"><option value="">Tanpa bin</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}">{{ $location->full_code }}</option>@endforeach</select></td>
                        <td><input type="number" step="1" min="0" name="items[{{ $i }}][quantity_requested]" class="form-control form-control-solid"></td>
                        <td><input type="number" step="1" min="0" name="items[{{ $i }}][source_quantity]" class="form-control form-control-solid"></td>
                        <td><input type="number" step="0.01" min="0" name="items[{{ $i }}][unit_cost_snapshot]" class="form-control form-control-solid"></td>
                        <td><select name="items[{{ $i }}][condition]" class="form-select form-select-solid">@foreach($conditions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></td>
                        <td><input name="items[{{ $i }}][notes]" class="form-control form-control-solid"></td>
                    </tr>
                @endfor
            </tbody></table></div>
            <x-slot:footer><button name="action" value="submit" class="btn btn-primary">Submit Retur</button><button name="action" value="draft" class="btn btn-light">Simpan Draft</button></x-slot:footer>
        </x-metronic.card>
    </form>
@endsection
