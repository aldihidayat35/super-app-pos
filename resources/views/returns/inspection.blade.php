@section('title', 'Pemeriksaan Retur - ' . config('app.name'))
@section('page_title', 'Pemeriksaan Retur')
@extends('layouts.metronic.app')

@section('content')
    <x-metronic.page-title :title="'QC ' . $return->number" description="Tentukan qty diterima baik/rusak/ditolak dan lokasi masuk." />
    <form method="POST" action="{{ route('returns.inspect', $return) }}">@csrf
        <x-metronic.card title="QC Item Retur"><table class="table"><thead><tr><th>Produk</th><th>Qty Retur</th><th>Lokasi</th><th>Good</th><th>Rusak</th><th>Reject</th><th>Kondisi</th><th>Catatan</th></tr></thead><tbody>@foreach($return->items as $item)<tr><td>{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td><td>{{ $item->quantity_requested }}</td><td><select name="items[{{ $item->id }}][warehouse_location_id]" class="form-select"><option value="">Tanpa bin</option>@foreach($warehouseLocations as $location)<option value="{{ $location->id }}" @selected($item->warehouse_location_id == $location->id)>{{ $location->full_code }}</option>@endforeach</select></td><td><input type="number" step="0.0001" min="0" name="items[{{ $item->id }}][quantity_good]" value="{{ $item->quantity_requested }}" class="form-control"></td><td><input type="number" step="0.0001" min="0" name="items[{{ $item->id }}][quantity_damaged]" value="0" class="form-control"></td><td><input type="number" step="0.0001" min="0" name="items[{{ $item->id }}][quantity_rejected]" value="0" class="form-control"></td><td><select name="items[{{ $item->id }}][condition]" class="form-select">@foreach($conditions as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></td><td><input name="items[{{ $item->id }}][notes]" class="form-control"></td></tr>@endforeach</tbody></table><x-slot:footer><button class="btn btn-primary">Simpan QC</button></x-slot:footer></x-metronic.card>
    </form>
@endsection
