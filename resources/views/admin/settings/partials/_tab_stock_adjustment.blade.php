<div class="row g-5 mb-6">
    <div class="col-md-3">
        <x-metronic.card title="Lokasi Sedang Aktif">
            <div class="fs-2hx fw-bold text-success">{{ $stockActiveCount }}</div>
        </x-metronic.card>
    </div>
    <div class="col-md-3">
        <x-metronic.card title="Menunggu Persetujuan">
            <div class="fs-2hx fw-bold text-warning">{{ $stockPendingCount }}</div>
        </x-metronic.card>
    </div>
    <div class="col-md-3">
        <x-metronic.card title="Total Selisih Qty">
            <div class="fs-2 fw-bold">{{ qty($stockTotalDifferenceQty) }}</div>
        </x-metronic.card>
    </div>
    <div class="col-md-3">
        <x-metronic.card title="Nilai Selisih">
            <div class="fs-2 fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($stockTotalDifferenceValue) }}</div>
        </x-metronic.card>
    </div>
</div>

@error('access')
    <div class="alert alert-danger mb-5">{{ $message }}</div>
@enderror

<div class="row g-6">
    @forelse($stockLocations as $location)
        @php($access = $stockAccesses->get($location->id))
        <div class="col-xl-6">
            <x-metronic.card :title="$location->name">
                <div class="d-flex justify-content-between mb-4">
                    <span class="badge {{ $access?->isCurrentlyActive() ? 'badge-light-success' : 'badge-light-secondary' }}">
                        {{ $access?->isCurrentlyActive() ? 'Aktif' : 'Terkunci' }}
                    </span>
                    <span class="text-muted fs-8">{{ $location->typeLabel() }} · {{ $location->code }}</span>
                </div>
                @if($access?->isCurrentlyActive())
                    <div class="alert alert-light-success p-3 fs-8 mb-3">
                        Aktif sampai <strong>{{ $access->active_until?->timezone('Asia/Jakarta')->format('d M Y H:i') }} WIB</strong><br>
                        <span class="text-muted">{{ $access->reason }}</span>
                    </div>
                @endif
                @if($access?->histories->isNotEmpty())
                    <div class="text-muted fs-8 mb-4">
                        Tindakan terakhir: {{ ucfirst($access->histories->first()->action) }} pada {{ $access->histories->first()->occurred_at?->format('d/m/Y H:i') }}
                    </div>
                @endif
                <form method="POST" action="{{ route('admin.settings.stock-adjustment-access.update', $location) }}">
                    @csrf
                    @method('PUT')
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fs-8 fw-semibold">Mulai</label>
                            <input type="datetime-local" name="active_from" class="form-control form-control-sm form-control-solid" value="{{ now()->timezone('Asia/Jakarta')->format('Y-m-d\TH:i') }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fs-8 fw-semibold">Berakhir</label>
                            <input type="datetime-local" name="active_until" class="form-control form-control-sm form-control-solid" value="{{ now()->timezone('Asia/Jakarta')->addDay()->format('Y-m-d\TH:i') }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fs-8 fw-semibold">Alasan aktivasi</label>
                            <textarea name="reason" class="form-control form-control-sm form-control-solid" rows="2" placeholder="Jelaskan kebutuhan koreksi stok..." required></textarea>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-primary mt-4">
                        <i class="ki-outline ki-key fs-6 me-1"></i> {{ $access ? 'Perbarui Masa Aktif' : 'Aktifkan Akses' }}
                    </button>
                </form>
                @if($access?->is_active)
                    <form method="POST" action="{{ route('admin.settings.stock-adjustment-access.destroy', $access) }}" class="mt-3">
                        @csrf
                        @method('DELETE')
                        <div class="input-group input-group-sm">
                            <input name="reason" class="form-control form-control-solid" minlength="10" placeholder="Alasan penonaktifan" required>
                            <button class="btn btn-light-danger">Nonaktifkan</button>
                        </div>
                    </form>
                @endif
            </x-metronic.card>
        </div>
    @empty
        <div class="col-12">
            <x-metronic.empty-state title="Belum ada lokasi" description="Buat gudang atau cabang terlebih dahulu." />
        </div>
    @endforelse
</div>
