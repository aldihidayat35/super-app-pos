@extends('layouts.metronic.app')

@section('title', 'Antrian Order B2B')
@section('page_title', 'Antrian Order B2B')
@section('page_guide')
    <x-metronic.page-guide id="warehouse-b2b-orders" title="Panduan Halaman Antrian Order B2B">
        <x-slot:function>
            <p>Halaman ini menampilkan daftar order B2B dari pelanggan yang membutuhkan validasi gudang. Order masuk setelah customer melakukan pemesanan dan perlu dicek stok, limit kredit, payment preference, dan prioritas fulfillment sebelum di-reserve.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Order masuk dari portal langganan dengan status Pending Confirmation.</li><li>Gudang melakukan review dan validasi pada halaman Review.</li><li>Stok di-reserve dan order lanjut ke proses packing lalu shipping.</li><li>Invoice diterbitkan setelah approval.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Cari nomor/customer:</strong> mencari order berdasarkan nomor atau nama pelanggan.</li><li><strong>Filter Status:</strong> menyaring berdasarkan status order.</li><li><strong>Order:</strong> nomor dan tanggal submit order.</li><li><strong>Pelanggan:</strong> nama bisnis dan requester.</li><li><strong>Ring/Limit:</strong> kategori harga dan limit kredit.</li><li><strong>Total:</strong> grand total order.</li><li><strong>Payment:</strong> preferensi pembayaran dan pengiriman.</li><li><strong>Status:</strong> status proses order.</li><li><strong>Umur:</strong> berapa lama order dibuat.</li><li><strong>Review:</strong> membuka halaman review detail order.</li><li><strong>Reservations:</strong> link ke monitor reserved stock.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Order yang di-reserve akan mengalokasikan stok. Peningkatan jumlah item menyebabkan reserved stock bertambah. Invoice diterbitkan setelah reserved atau status invoice-ready.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Gunakan pencarian atau filter status.</li><li>Klik <strong>Filter</strong> untuk menerapkan filter.</li><li>Klik <strong>Review</strong> pada order yang ingin diproses.</li><li>Pada halaman review, verifikasi dan reserve stok.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Periksa limit kredit customer sebelum approve.</li><li>Perhatikan umur order untuk prioritas fulfillment.</li><li>Order dengan status berbeda memerlukan aksi berbeda.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Order ORD-B2B-001 dari PT Maju Jaya Rp 5.000.000. Limit kredit Rp 20.000.000, piutang Rp 2.000.000. Status Pending Confirmation. Klik Review untuk memproses.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection
@section('content')
    <x-metronic.page-title title="Antrian Order Gudang" description="Validasi order pelanggan, stok, limit, pembayaran, dan prioritas fulfillment." />
    <form method="GET" class="card card-body mb-5">
        <div class="row g-3">
            <div class="col-md-4"><input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid" placeholder="Cari nomor/customer"></div>
            <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
            <div class="col-md-2"><a href="{{ route('warehouse.reservations.index') }}" class="btn btn-light w-100">Reservations</a></div>
        </div>
    </form>
    <x-metronic.card>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order</th><th>Pelanggan</th><th>Ring/Limit</th><th>Total</th><th>Payment</th><th>Status</th><th>Umur</th><th></th></tr></thead><tbody>
            @forelse($orders as $order)
                <tr>
                    <td><div class="fw-bold">{{ $order->number }}</div><div class="text-muted">{{ $order->submitted_at?->format('d/m/Y H:i') }}</div></td>
                    <td>{{ $order->customer?->business_name }}<div class="text-muted">{{ $order->requester?->name }}</div></td>
                    <td>{{ $order->customer?->price_category }}<div class="text-muted">{{ App\Support\CurrencyFormatter::rupiah($order->customer?->credit_limit ?? 0) }}</div></td>
                    <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td>
                    <td>{{ ucfirst($order->payment_preference) }}<div class="text-muted">{{ ucfirst($order->delivery_method) }}</div></td>
                    <td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td>
                    <td>{{ $order->created_at->diffForHumans() }}</td>
                    <td class="text-end"><a href="{{ route('warehouse.b2b-orders.review', $order) }}" class="btn btn-sm btn-light-primary">Review</a></td>
                </tr>
            @empty
                <tr><td colspan="8"><x-metronic.empty-state title="Belum ada order B2B" description="Order dari portal langganan akan tampil di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $orders->links() }}</div>
    </x-metronic.card>
@endsection
