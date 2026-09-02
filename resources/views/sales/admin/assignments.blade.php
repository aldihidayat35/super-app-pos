@extends('layouts.metronic.app')

@section('title', 'Assignment Customer')
@section('page_title', 'Assignment Customer')
@section('content')
    <x-metronic.page-title title="Assignment Customer" description="Tentukan satu Sales penanggung jawab untuk setiap customer." />
    <form method="GET" class="card card-body mb-5"><div class="row g-3"><div class="col-md-9"><input name="q" value="{{ $term }}" class="form-control" placeholder="Cari nama atau kode customer"></div><div class="col-md-3"><button class="btn btn-primary w-100">Cari</button></div></div></form>
    <x-metronic.card><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Customer</th><th>Kota</th><th>Sales Saat Ini</th><th>Ubah Assignment</th></tr></thead><tbody>
        @forelse($customers as $customer)<tr><td><div class="fw-bold">{{ $customer->business_name }}</div><div class="text-muted">{{ $customer->code }}</div></td><td>{{ $customer->city ?: '-' }}</td><td>{{ $customer->sales?->name ?: 'Belum ditentukan' }}</td><td><form method="POST" action="{{ route('sales.assignments.update', $customer) }}" class="d-flex gap-2">@csrf @method('PUT')<select name="sales_user_id" class="form-select form-select-sm"><option value="">Tanpa Sales</option>@foreach($salesUsers as $sales)<option value="{{ $sales->id }}" @selected($customer->sales_user_id === $sales->id)>{{ $sales->name }}</option>@endforeach</select><button class="btn btn-sm btn-light-primary">Simpan</button></form></td></tr>
        @empty<tr><td colspan="4"><x-metronic.empty-state title="Customer tidak ditemukan" description="Ubah kata pencarian dan coba kembali." /></td></tr>@endforelse
    </tbody></table></div><div class="mt-4">{{ $customers->links() }}</div></x-metronic.card>
@endsection
