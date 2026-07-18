@extends('layouts.metronic.app')

@section('title', 'Buat Transfer Stok - ' . config('app.name'))
@section('page_title', 'Form dan Approval Transfer')

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
