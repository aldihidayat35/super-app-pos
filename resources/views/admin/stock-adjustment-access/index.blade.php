@extends('layouts.metronic.app')

@section('title', 'Akses Koreksi Stok - ' . config('app.name'))
@section('page_title', 'Akses Koreksi Stok')

@section('page_guide')
    <x-metronic.page-guide id="stock-adjustment-access" title="Panduan Akses Koreksi Stok">
        <x-slot:function><p>Membuka akses koreksi stok langsung untuk kepala toko atau kepala gudang dalam waktu terbatas.</p></x-slot:function>
        <x-slot:workflow><p>Aktifkan lokasi, kepala lokasi membuat dokumen, lalu kepala lain atau Super Admin menyetujui.</p></x-slot:workflow>
        <x-slot:warnings><div class="alert alert-warning mb-0">Setiap aktivasi dan penonaktifan dicatat sebagai audit berisiko tinggi.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    {{-- Grid Menu Card Berikon untuk Navigasi Pengaturan Sistem --}}
    @include('admin.settings._nav_cards')

    <div class="row g-5 mb-6">
        <div class="col-md-3"><x-metronic.card title="Lokasi Sedang Aktif"><div class="fs-2hx fw-bold text-success">{{ $activeCount }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Menunggu Persetujuan"><div class="fs-2hx fw-bold text-warning">{{ $pendingCount }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Total Selisih Qty"><div class="fs-2 fw-bold">{{ qty($totalDifferenceQty) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Nilai Selisih"><div class="fs-2 fw-bold">Rp {{ number_format((float)$totalDifferenceValue, 0, ',', '.') }}</div></x-metronic.card></div>
    </div>
    @error('access')<div class="alert alert-danger">{{ $message }}</div>@enderror
    <div class="row g-6">
        @forelse($locations as $location)
            @php($access = $accesses->get($location->id))
            <div class="col-xl-6">
                <x-metronic.card :title="$location->name">
                    <div class="d-flex justify-content-between mb-4">
                        <span class="badge {{ $access?->isCurrentlyActive() ? 'badge-light-success' : 'badge-light-secondary' }}">{{ $access?->isCurrentlyActive() ? 'Aktif' : 'Terkunci' }}</span>
                        <span class="text-muted">{{ $location->typeLabel() }} · {{ $location->code }}</span>
                    </div>
                    @if($access?->isCurrentlyActive())
                        <div class="alert alert-light-success">Aktif sampai <strong>{{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong><br>{{ $access->reason }}</div>
                    @endif
                    @if($access?->histories->isNotEmpty())
                        <div class="text-muted fs-8 mb-4">Tindakan terakhir: {{ ucfirst($access->histories->first()->action) }} pada {{ $access->histories->first()->occurred_at?->format('d/m/Y H:i') }}</div>
                    @endif
                    <form method="POST" action="{{ route('admin.settings.stock-adjustment-access.update', $location) }}">
                        @csrf @method('PUT')
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Mulai</label><input type="datetime-local" name="active_from" class="form-control" value="{{ now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}" required></div>
                            <div class="col-md-6"><label class="form-label">Berakhir</label><input type="datetime-local" name="active_until" class="form-control" value="{{ now()->timezone('Asia/Jakarta')->addDay()->format('Y-m-d\TH:i') }}" required></div>
                            <div class="col-12"><label class="form-label">Alasan aktivasi</label><textarea name="reason" class="form-control" rows="2" required></textarea></div>
                        </div>
                        <button class="btn btn-primary mt-4">{{ $access ? 'Perbarui Masa Aktif' : 'Aktifkan Akses' }}</button>
                    </form>
                    @if($access?->is_active)
                        <form method="POST" action="{{ route('admin.settings.stock-adjustment-access.destroy', $access) }}" class="mt-3">
                            @csrf @method('DELETE')
                            <div class="input-group"><input name="reason" class="form-control" minlength="10" placeholder="Alasan penonaktifan" required><button class="btn btn-light-danger">Nonaktifkan</button></div>
                        </form>
                    @endif
                </x-metronic.card>
            </div>
        @empty
            <div class="col-12"><x-metronic.empty-state title="Belum ada lokasi" description="Buat gudang atau cabang terlebih dahulu." /></div>
        @endforelse
    </div>
@endsection
