@extends('layouts.metronic.app')

@section('title', 'Ajukan Produk Baru')

@section('content')
    <x-metronic.page-title title="Ajukan Produk Baru" description="Master data akan memeriksa duplikasi nama, SKU, dan barcode sebelum produk dapat dijual." />
    <x-metronic.card title="Data Produk dari Toko">
        <form method="POST" enctype="multipart/form-data" action="{{ route('retail.product-requests.store') }}" class="row g-5">
            @csrf
            <div class="col-md-6"><x-metronic.form-group name="branch_id" label="Toko Pengaju" required><select name="branch_id" class="form-select" required><option value="">Pilih toko</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->code }} - {{ $branch->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-6"><x-metronic.form-group name="name" label="Nama Produk" required help="Gunakan nama lengkap agar pemeriksaan duplikasi lebih mudah."><input name="name" value="{{ old('name') }}" class="form-control" required></x-metronic.form-group></div>
            <div class="col-md-6"><x-metronic.form-group name="proposed_sku" label="Usulan SKU" help="Boleh dikosongkan agar sistem membuat SKU."><input name="proposed_sku" value="{{ old('proposed_sku') }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-6"><x-metronic.form-group name="barcode" label="Barcode"><input name="barcode" value="{{ old('barcode') }}" class="form-control"></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="category_id" label="Kategori" required><select name="category_id" class="form-select" required><option value="">Pilih kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="brand_id" label="Merek"><select name="brand_id" class="form-select"><option value="">Tanpa merek</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(old('brand_id') == $brand->id)>{{ $brand->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="base_unit_id" label="Satuan Dasar" required><select name="base_unit_id" class="form-select" required><option value="">Pilih satuan</option>@foreach($units as $unit)<option value="{{ $unit->id }}" @selected(old('base_unit_id') == $unit->id)>{{ $unit->name }} ({{ $unit->symbol }})</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="supplier_id" label="Supplier"><select name="supplier_id" class="form-select"><option value="">Belum ditentukan</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="purchase_cost" label="Harga Beli" required><input type="number" min="0" step="0.01" name="purchase_cost" value="{{ old('purchase_cost', 0) }}" class="form-control" required></x-metronic.form-group></div>
            <div class="col-md-4"><x-metronic.form-group name="proposed_selling_price" label="Usulan Harga Jual" required help="Tidak boleh lebih kecil dari harga beli."><input type="number" min="0" step="0.01" name="proposed_selling_price" value="{{ old('proposed_selling_price', 0) }}" class="form-control" required></x-metronic.form-group></div>
            <div class="col-md-6"><x-metronic.form-group name="photo" label="Foto Produk"><input type="file" name="photo" accept="image/*" class="form-control"></x-metronic.form-group></div>
            <div class="col-12"><x-metronic.form-group name="description" label="Keterangan"><textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea></x-metronic.form-group></div>
            <div class="col-12 d-flex gap-3"><button class="btn btn-primary">Kirim untuk Diperiksa</button><a href="{{ route('retail.product-requests.index') }}" class="btn btn-light">Batal</a></div>
        </form>
    </x-metronic.card>
@endsection
