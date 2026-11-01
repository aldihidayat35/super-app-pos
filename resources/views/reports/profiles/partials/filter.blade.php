<form method="GET" class="row g-3 align-items-end report-profile-filter">
    <div class="col-xl-2 col-md-4">
        <label class="form-label fw-semibold">Tanggal mulai</label>
        <input type="date" name="start_date" value="{{ $filters['start_date'] }}" class="form-control form-control-solid">
    </div>
    <div class="col-xl-2 col-md-4">
        <label class="form-label fw-semibold">Tanggal akhir</label>
        <input type="date" name="end_date" value="{{ $filters['end_date'] }}" class="form-control form-control-solid">
    </div>
    @if($workLocations->isNotEmpty())
        <div class="col-xl-3 col-md-4">
            <label class="form-label fw-semibold">Lokasi kerja</label>
            <select name="work_location_id" class="form-select form-select-solid" data-control="native">
                @if(($profile['location_type'] ?? null) === null)<option value="">Semua lokasi berizin</option>@endif
                @foreach($workLocations as $location)
                    <option value="{{ $location->id }}" @selected((int)($filters['work_location_id'] ?? 0) === $location->id)>{{ $location->name }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-xl-2 col-md-4">
        <label class="form-label fw-semibold">Rentang grafik</label>
        <select name="range" class="form-select form-select-solid" data-control="native">
            @foreach(['daily' => 'Harian', 'monthly' => 'Bulanan', 'yearly' => 'Tahunan'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['range'] ?? 'monthly') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-xl-2 col-md-4 d-flex gap-2">
        <button class="btn btn-primary flex-grow-1"><i class="ki-outline ki-filter fs-5"></i>Terapkan</button>
        <a href="{{ route(request()->route()->getName(), ['report' => $reportKey]) }}" class="btn btn-light" title="Reset filter"><i class="ki-outline ki-arrows-circle fs-5"></i></a>
    </div>
</form>
