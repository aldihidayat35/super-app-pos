@php
    $moneyKeys = ['revenue','margin','value','amount','receivable','cash','price','cost','sales','profit'];
    $kpis = collect($report['summary'] ?? [])
        ->filter(fn($value) => is_scalar($value) || $value === null)
        ->take(8);
@endphp
<div class="row g-4 mb-6">
    @foreach($kpis as $key => $value)
        @php
            $isMoney = collect($moneyKeys)->contains(fn($needle) => str_contains((string)$key, $needle));
            $display = $value === null
                ? '—'
                : ($isMoney && is_numeric($value)
                    ? \App\Support\CurrencyFormatter::rupiah((string)$value)
                    : (is_numeric($value) ? qty($value) : (string)$value));
        @endphp
        <div class="col-6 col-xl-3">
            <div class="card report-profile-kpi h-100"><div class="card-body p-5">
                <div class="text-muted text-uppercase fs-9 fw-bold mb-2">{{ str($key)->replace('_', ' ')->title() }}</div>
                <div class="fs-2 fw-bold text-gray-900">{{ $display }}</div>
            </div></div>
        </div>
    @endforeach
</div>
