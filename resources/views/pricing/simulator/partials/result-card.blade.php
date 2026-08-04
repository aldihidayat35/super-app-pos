<div class="row g-4">
    @if($canSensitive)
    <div class="col-md-3">
        <div class="card card-flush border-primary bg-primary-50 h-100">
            <div class="card-body">
                <div class="text-muted fs-7 mb-1"><i class="ki-outline ki-coin me-1"></i>HPP</div>
                <div class="fs-3 fw-bold text-primary">Rp {{ number_format($result['hpp_base'], 0, ',', '.') }}</div>
                <div class="text-muted fs-7 mt-1">Biaya pokok</div>
            </div>
        </div>
    </div>
    @endif

    <div class="col-md-3">
        <div class="card card-flush border-warning bg-warning-50 h-100">
            <div class="card-body">
                <div class="text-muted fs-7 mb-1"><i class="ki-outline ki-graph me-1"></i>Minimum</div>
                <div class="fs-3 fw-bold text-warning">Rp {{ number_format($result['minimum_price'], 0, ',', '.') }}</div>
                <div class="text-muted fs-7 mt-1">Batas bawah</div>
                @if($canSensitive && $result['maximum_price'])
                    <div class="text-muted fs-7 mt-1">Maks: Rp {{ number_format($result['maximum_price'], 0, ',', '.') }}</div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-flush border-success bg-success-50 h-100">
            <div class="card-body">
                <div class="text-muted fs-7 mb-1"><i class="ki-outline ki-check-circle me-1"></i>Terpilih</div>
                <div class="fs-3 fw-bold text-success">Rp {{ number_format($result['selected_price'], 0, ',', '.') }}</div>
                <div class="text-muted fs-7 mt-1">{{ $result['selected_source'] == 'computed_minimum' ? 'Minimum' : $result['selected_source'] }}</div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card card-flush border-info bg-info-50 h-100">
            <div class="card-body">
                <div class="text-muted fs-7 mb-1"><i class="ki-outline ki-tag me-1"></i>Setelah Diskon</div>
                <div class="fs-3 fw-bold text-info">Rp {{ number_format($result['discounted_price'], 0, ',', '.') }}</div>
                @if($canSensitive && $result['margin_amount'] !== null)
                    <div class="mt-2">
                        <span class="badge {{ (float)$result['margin_amount'] >= 0 ? 'bg-success' : 'bg-danger' }} fs-7">
                            Margin {{ number_format($result['margin_amount'], 0, ',', '.') }} ({{ number_format($result['margin_percent'], 1) }}%)
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@if($result['approval_required'])
    <div class="alert alert-warning mt-4 d-flex align-items-center">
        <i class="ki-outline ki-exclamation-triangle fs-4 me-3"></i>
        <div>
            <div class="fw-semibold">{{ $result['reason'] }}</div>
            @foreach($result['approval_reasons'] as $reason)
                <span class="badge bg-danger me-1 fs-6">{{ $reason === 'below_minimum' ? 'Di Bawah Minimum' : ($reason === 'overpricing' ? 'Overpricing' : 'Diskon Tinggi') }}</span>
            @endforeach
        </div>
    </div>
@else
    <div class="alert alert-success mt-4 d-flex align-items-center">
        <i class="ki-outline ki-check-circle fs-4 me-3"></i>
        <div class="fw-semibold">{{ $result['reason'] }}</div>
    </div>
@endif

<div class="table-responsive mt-4">
    <table class="table table-row-dashed align-middle">
        <thead>
            <tr class="text-muted fw-bold text-uppercase fs-7">
                <th>Sumber</th>
                <th>Harga</th>
                <th>Prioritas</th>
                <th>Alasan</th>
            </tr>
        </thead>
        <tbody>
            @forelse($result['candidates'] as $candidate)
                <tr>
                    <td>
                        <span class="badge {{ $candidate['source'] == 'computed_minimum' ? 'bg-warning' : ($candidate['source'] == 'customer_special' ? 'bg-primary' : 'bg-secondary') }} fs-7 me-2">{{ $candidate['source'] }}</span>
                    </td>
                    <td class="fw-bold">Rp {{ number_format($candidate['price_base'], 0, ',', '.') }}</td>
                    <td><span class="badge bg-light fs-7">{{ $candidate['priority'] }}</span></td>
                    <td class="text-muted fs-7">{{ $candidate['reason'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted fs-7">Tidak ada kandidat harga eksplisit, menggunakan minimum.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
