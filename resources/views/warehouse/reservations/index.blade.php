@extends('layouts.metronic.app')

@section('title', 'Monitor Reserved Stock')
@section('page_title', 'Monitor Reserved Stock')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-reservations" title="Panduan Halaman Monitor Reserved Stock">
        <x-slot:function>
            <p>Halaman ini memonitor stok yang sudah dialokasikan untuk order B2B. Reserved stock tidak dapat digunakan untuk kebutuhan lain hingga dirilis atau dikonversi menjadi shipped. Kepala Gudang dan Owner menggunakannya untuk memantau alokasi dan expiry reservation.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Sistem menampilkan daftar reservation aktif dengan order, produk, lokasi, qty, expiry, dan status.</li><li>Pengguna dapat mencari, memfilter, dan releasing reservation yang sudah expired atau tidak diperlukan.</li><li>Tombol Proses Expired membulkankan processing reservation yang waktunya sudah lewat.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Cari order/produk:</strong> mencari berdasarkan nomor order atau nama produk.</li><li><strong>Filter Status:</strong> menyaring berdasarkan status reservation.</li><li><strong>Order:</strong> nomor dan customer order.</li><li><strong>Produk:</strong> SKU dan nama produk yang di-reserve.</li><li><strong>Lokasi:</strong> gudang dan bin penyimpanan.</li><li><strong>Qty:</strong> reserved, released, dan issued.</li><li><strong>Expiry:</strong> waktu kadaluarsa reservation.</li><li><strong>Status:</strong> status reservation saat ini.</li><li><strong>Release:</strong> form untuk melepaskan reservation aktif.</li><li><strong>Proses Expired:</strong> memproses bulk reservation yang expired.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Releasing reservation mengembalikan stok reserved menjadi available untuk dialokasikan ke order lain. Proses Expired mengubah status reservation yang waktunya sudah lewat. Filter dan pencarian tidak mengubah data.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Gunakan pencarian atau filter status untuk menemukan reservation.</li><li>Periksa Expiry untuk reservation yang mendekati kadaluarsa.</li><li>Untuk reservation aktif, masukkan alasan release pada kolom yang tersedia.</li><li>Klik tombol <strong>Release</strong> untuk mengembalikan stok.</li><li>Atau klik <strong>Proses Expired</strong> untuk memproses reservation yang sudah melewati expiry.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Jangan release reservation tanpa alasan yang jelas.</li><li>Reserved stock tidak tersedia untuk order lain sampai dirilis.</li><li>Reservation expired otomatis mengurangi ketersediaan setelah diproses.</li><li>Periksa qty released dan issued untuk memastikan tracking akurat.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Order ORD-001 memesan 20 unit Kopi Arabika pada Bin A-01. Status ACTIVE, expiry 20/07/2025. Jika tidak dikerjakan, klik Release dengan alasan "Customer batalkan order" untuk mengembalikan 20 unit ke available.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Monitor Reserved Stock" description="Pantau stok yang dialokasikan untuk order B2B, expiry, release manual, dan konversi shipment.">
        <form method="POST" action="{{ route('warehouse.reservations.expire') }}">@csrf<button class="btn btn-light-warning">Proses Expired</button></form>
    </x-metronic.page-title>
    <form method="GET" class="card card-body mb-5">
        <div class="row g-3">
            <div class="col-md-5"><input name="q" value="{{ $filters['q'] }}" class="form-control form-control-solid" placeholder="Cari order/produk"></div>
            <div class="col-md-4"><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-md-3"><button class="btn btn-light-primary w-100">Filter</button></div>
        </div>
    </form>
    <x-metronic.card>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Order</th><th>Produk</th><th>Lokasi</th><th>Qty</th><th>Expiry</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($reservations as $reservation)
                <tr>
                    <td><a href="{{ route('warehouse.b2b-orders.review', $reservation->order) }}" class="fw-bold text-gray-900 text-hover-primary">{{ $reservation->order?->number }}</a><div class="text-muted">{{ $reservation->order?->customer?->business_name }}</div></td>
                    <td>{{ $reservation->product?->name }}<div class="text-muted">{{ $reservation->product?->sku }}</div></td>
                    <td>{{ $reservation->workLocation?->name }}<div class="text-muted">{{ $reservation->warehouseLocation?->full_code ?: 'Tanpa bin' }}</div></td>
                    <td>Reserved {{ qty($reservation->quantity_reserved) }}<div class="text-muted">Released {{ qty($reservation->quantity_released) }} · Issued {{ qty($reservation->quantity_issued) }}</div></td>
                    <td>{{ $reservation->expires_at?->format('d/m/Y H:i') ?: '-' }}</td>
                    <td><x-metronic.status-badge :status="$reservation->status->value" :label="$reservation->status->label()" /></td>
                    <td class="text-end">@if($reservation->status === App\Enums\StockReservationStatus::ACTIVE)<form method="POST" action="{{ route('warehouse.reservations.release', $reservation) }}" class="d-flex gap-2">@csrf<input name="reason" class="form-control form-control-sm" required placeholder="Alasan release"><button class="btn btn-sm btn-light-warning">Release</button></form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-metronic.empty-state title="Belum ada reservation" description="Reservation order B2B akan tampil di sini." /></td></tr>
            @endforelse
        </tbody></table></div>
        <div class="mt-4">{{ $reservations->links() }}</div>
    </x-metronic.card>
@endsection
