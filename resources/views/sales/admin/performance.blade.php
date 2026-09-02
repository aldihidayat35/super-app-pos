@extends('layouts.metronic.app')

@section('title', 'Performance Sales')
@section('page_title', 'Performance Sales')
@section('content')
    <x-metronic.page-title title="Performance Sales" description="Ringkasan seluruh Sales untuk periode terpilih." />
    <form method="GET" class="card card-body mb-5"><div class="row g-3"><div class="col-md-4"><select name="month" class="form-select">@for($value = 1; $value <= 12; $value++)<option value="{{ $value }}" @selected($month === $value)>{{ DateTime::createFromFormat('!m', $value)->format('F') }}</option>@endfor</select></div><div class="col-md-4"><input type="number" name="year" min="2020" max="2100" value="{{ $year }}" class="form-control"></div><div class="col-md-4"><button class="btn btn-primary w-100">Tampilkan</button></div></div></form>
    <x-metronic.card><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Sales</th><th>Target</th><th>Penjualan</th><th>Pencapaian</th><th>Bonus</th><th>Customer</th><th>Order Selesai</th></tr></thead><tbody>
        @forelse($rows as $row)<tr><td class="fw-bold">{{ $row['sales']->name }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($row['metrics']['target_amount']) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($row['metrics']['sales_amount']) }}</td><td>{{ number_format((float) $row['metrics']['achievement_percentage'], 2, ',', '.') }}%</td><td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($row['metrics']['bonus_amount']) }}</td><td>{{ $row['metrics']['customer_count'] }}</td><td>{{ $row['metrics']['order_count'] }}</td></tr>
        @empty<tr><td colspan="7"><x-metronic.empty-state title="Belum ada user Sales" description="Tambahkan role Sales pada user terlebih dahulu." /></td></tr>@endforelse
    </tbody></table></div></x-metronic.card>
@endsection
