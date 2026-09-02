@extends('layouts.metronic.app')

@section('title', 'Customer Saya')
@section('page_title', 'Customer Saya')
@section('page_guide')
    <x-metronic.page-guide id="sales-customers" title="Panduan Customer Saya">
        <x-slot:function><p>Menampilkan customer yang secara aktif ditugaskan kepada akun Sales Anda.</p></x-slot:function>
        <x-slot:workflow><p>Admin melakukan assignment, lalu Sales dapat melihat customer dan membuat order B2B.</p></x-slot:workflow>
        <x-slot:parts><p>Total penjualan hanya berasal dari order Sales ini yang berstatus Selesai.</p></x-slot:parts>
        <x-slot:impacts><p>Perubahan assignment langsung mengubah akses customer di backend.</p></x-slot:impacts>
        <x-slot:operation><p>Cari customer, buka detail, atau klik Buat Order.</p></x-slot:operation>
        <x-slot:warnings><p>Customer Sales lain tidak dapat dibuka dengan mengganti ID pada URL.</p></x-slot:warnings>
        <x-slot:example><p>Cari berdasarkan nama usaha, kode, atau nomor WhatsApp.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Customer Saya" description="Customer yang menjadi tanggung jawab Anda." />
    <form method="GET" class="card card-body mb-5">
        <div class="row g-3"><div class="col-md-9"><input name="q" value="{{ $term }}" class="form-control form-control-solid" placeholder="Cari nama, kode, atau nomor HP"></div><div class="col-md-3"><button class="btn btn-primary w-100">Cari</button></div></div>
    </form>
    <x-metronic.card>
        <div class="table-responsive"><table class="table align-middle">
            <thead><tr><th>Customer</th><th>Nomor HP</th><th>Alamat</th><th>Total Order</th><th>Total Penjualan</th><th>Order Terakhir</th><th></th></tr></thead>
            <tbody>
                @forelse($customers as $customer)
                    <tr>
                        <td><a href="{{ route('sales.customers.show', $customer) }}" class="fw-bold">{{ $customer->business_name }}</a><div class="text-muted">{{ $customer->code }}</div></td>
                        <td>{{ $customer->whatsapp_number ?: '-' }}</td>
                        <td>{{ $customer->business_address ?: '-' }}<div class="text-muted">{{ $customer->city }}</div></td>
                        <td>{{ $customer->total_orders }}</td>
                        <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($customer->total_sales ?? 0) }}</td>
                        <td>{{ $customer->last_order_at ? Illuminate\Support\Carbon::parse($customer->last_order_at)->format('d/m/Y') : '-' }}</td>
                        <td class="text-end"><a href="{{ route('sales.orders.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-light-primary">Buat Order</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada customer" description="Hubungi admin untuk assignment customer." /></td></tr>
                @endforelse
            </tbody>
        </table></div>
        <div class="mt-4">{{ $customers->links() }}</div>
    </x-metronic.card>
@endsection
