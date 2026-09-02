@extends('layouts.metronic.app')

@section('title', 'Target Sales')
@section('page_title', 'Target Sales')
@section('content')
    <x-metronic.page-title title="Target Sales" description="Atur target bulanan dan persentase bonus sederhana." />
    <div class="row g-5">
        <div class="col-lg-5"><x-metronic.card title="Atur Target"><form method="POST" action="{{ route('sales.admin.targets.store') }}">@csrf
            <x-metronic.form-group name="sales_user_id" label="Sales" required><select name="sales_user_id" class="form-select"><option value="">Pilih Sales</option>@foreach($salesUsers as $sales)<option value="{{ $sales->id }}" @selected((string) old('sales_user_id') === (string) $sales->id)>{{ $sales->name }}</option>@endforeach</select></x-metronic.form-group>
            <div class="row"><div class="col-6"><x-metronic.form-group name="month" label="Bulan" required><select name="month" class="form-select">@for($value = 1; $value <= 12; $value++)<option value="{{ $value }}" @selected((int) old('month', $month) === $value)>{{ DateTime::createFromFormat('!m', $value)->format('F') }}</option>@endfor</select></x-metronic.form-group></div><div class="col-6"><x-metronic.form-group name="year" label="Tahun" required><input type="number" name="year" min="2020" max="2100" value="{{ old('year', $year) }}" class="form-control"></x-metronic.form-group></div></div>
            <x-metronic.form-group name="target_amount" label="Target Penjualan" required><input type="number" name="target_amount" min="0" step="0.01" value="{{ old('target_amount') }}" class="form-control"></x-metronic.form-group>
            <x-metronic.form-group name="bonus_percentage" label="Persentase Bonus" required><div class="input-group"><input type="number" name="bonus_percentage" min="0" max="100" step="0.0001" value="{{ old('bonus_percentage', 0) }}" class="form-control"><span class="input-group-text">%</span></div></x-metronic.form-group>
            <button class="btn btn-primary w-100">Simpan Target</button>
        </form></x-metronic.card></div>
        <div class="col-lg-7"><form method="GET" class="card card-body mb-5"><div class="row g-3"><div class="col-5"><select name="month" class="form-select">@for($value = 1; $value <= 12; $value++)<option value="{{ $value }}" @selected($month === $value)>{{ DateTime::createFromFormat('!m', $value)->format('F') }}</option>@endfor</select></div><div class="col-4"><input type="number" name="year" value="{{ $year }}" class="form-control"></div><div class="col-3"><button class="btn btn-light-primary w-100">Filter</button></div></div></form>
            <x-metronic.card title="Target Periode"><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Sales</th><th>Target</th><th>Bonus</th><th>Histori</th></tr></thead><tbody>@forelse($targets as $target)<tr><td>{{ $target->sales?->name }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($target->target_amount) }}</td><td>{{ number_format((float) $target->bonus_percentage, 2, ',', '.') }}%</td><td>{{ $target->bonus?->finalized_at ? 'Terkunci' : 'Berjalan' }}</td></tr>@empty<tr><td colspan="4"><x-metronic.empty-state title="Belum ada target" description="Isi form untuk menambahkan target periode ini." /></td></tr>@endforelse</tbody></table></div></x-metronic.card>
        </div>
    </div>
@endsection
