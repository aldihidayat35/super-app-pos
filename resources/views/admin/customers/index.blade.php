@extends('layouts.metronic.app')
@section('title', 'Pelanggan dan Langganan')
@section('page_title', 'Pelanggan dan Langganan')
@section('toolbar_actions')
    <x-metronic.permission-button permission="customers.import" :href="route('admin.parties.import.index', 'customers')" variant="light" icon="ki-outline ki-file-up">Import</x-metronic.permission-button>
    <x-metronic.permission-button permission="customers.export" :href="route('admin.customers.export')" variant="light" icon="ki-outline ki-file-down">Export</x-metronic.permission-button>
    <x-metronic.permission-button permission="customers.create" :href="route('admin.customers.create')" icon="ki-outline ki-plus">Tambah Pelanggan</x-metronic.permission-button>
@endsection
@section('content')
<x-metronic.card>
    <form method="GET" class="d-flex flex-wrap justify-content-between gap-3 mb-5"><div class="d-flex flex-wrap gap-3"><input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid w-225px" placeholder="Cari kode/nama/PIC"><select name="type" class="form-select form-select-solid w-175px"><option value="">Semua Tipe</option>@foreach($types as $value => $label)<option value="{{ $value }}" @selected($filters['type'] === $value)>{{ $label }}</option>@endforeach</select><input name="price_category" value="{{ $filters['price_category'] }}" class="form-control form-control-solid w-175px" placeholder="Ring harga"><select name="status" class="form-select form-select-solid w-200px"><option value="">Semua Status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select><select name="over_limit" class="form-select form-select-solid w-175px"><option value="">Semua Limit</option><option value="yes" @selected($filters['over_limit'] === 'yes')>Over Limit</option></select></div><button class="btn btn-light-primary">Filter</button></form>
    <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Kode</th><th>Nama/Usaha</th><th>Tipe</th><th>Ring</th><th>Limit</th><th>Piutang</th><th>Verifikasi</th><th>Akun</th><th class="text-end">Aksi</th></tr></thead><tbody>
    @forelse($customers as $customer)
        <tr><td class="fw-bold">{{ $customer->code }}</td><td><a href="{{ route('admin.customers.show', $customer) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $customer->business_name }}</a><div class="text-muted">{{ $customer->pic_name ?: $customer->owner_name }}</div></td><td>{{ $customer->type->label() }}</td><td>{{ $customer->price_category }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($customer->credit_limit) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($customer->receivable_balance) }}</td><td><x-metronic.status-badge :status="$customer->verification_status->value" :label="$customer->verification_status->label()" /></td><td><x-metronic.status-badge :status="$customer->account_status->value" :label="$customer->account_status->label()" /></td><td class="text-end"><a href="{{ route('admin.customers.show', $customer) }}" class="btn btn-sm btn-light">Detail</a> @can('update', $customer)<a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-sm btn-light-primary">Edit</a>@endcan</td></tr>
    @empty
        <tr><td colspan="9"><x-metronic.empty-state title="Belum ada pelanggan" description="Pelanggan akan tampil setelah dibuat atau diimport." /></td></tr>
    @endforelse
    </tbody></table></div>{{ $customers->links() }}
</x-metronic.card>
@endsection
