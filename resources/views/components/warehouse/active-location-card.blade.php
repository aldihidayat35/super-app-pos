@php
    $user = auth()->user();
    $activeId = (int) session('work_location.id');
    
    if ($user?->hasUnrestrictedLocationScope()) {
        $locations = \App\Models\WorkLocation::query()
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    } else {
        $permittedIds = $user?->permittedWorkLocationIds() ?? [];
        $locations = \App\Models\WorkLocation::query()
            ->whereIn('id', $permittedIds)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }

    $activeLocation = $locations->firstWhere('id', $activeId) ?? $locations->first();
@endphp

@if ($activeLocation)
    <div class="card card-flush shadow-2xs border border-gray-200 mb-6 bg-body">
        <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="symbol symbol-40px symbol-circle {{ $activeLocation->type === 'warehouse' ? 'bg-light-primary' : 'bg-light-info' }}">
                    <span class="symbol-label">
                        <i class="ki-outline {{ $activeLocation->type === 'warehouse' ? 'ki-delivery-3 text-primary' : 'ki-shop text-info' }} fs-2"></i>
                    </span>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="text-muted fs-8 text-uppercase fw-semibold">Lokasi Operasional Aktif</span>
                        <span class="badge badge-light-{{ $activeLocation->type === 'warehouse' ? 'primary' : 'info' }} fs-9 fw-bold">
                            {{ ucfirst($activeLocation->type) }}
                        </span>
                        <span class="badge badge-light-success fs-9 fw-semibold">
                            <i class="ki-outline ki-check-circle fs-8 text-success me-1"></i> Data Tersaring
                        </span>
                    </div>
                    <div class="fs-5 fw-bold text-gray-900 mt-0.5">
                        {{ $activeLocation->name }}
                        <span class="text-muted fs-8 font-monospace fw-normal">({{ $activeLocation->code }})</span>
                    </div>
                </div>
            </div>

            @if ($locations->count() > 1)
                <div class="d-flex align-items-center gap-2">
                    <span class="text-muted fs-8 fw-semibold text-nowrap d-none d-sm-inline">Ganti Lokasi:</span>
                    <form method="POST" action="{{ route('work-locations.switch') }}" class="m-0">
                        @csrf
                        <select name="work_location_id" class="form-select form-select-sm form-select-solid fw-bold fs-7 w-200px" onchange="this.form.submit()">
                            @foreach ($locations as $loc)
                                <option value="{{ $loc->id }}" @selected($activeLocation->id === $loc->id)>
                                    {{ $loc->name }} ({{ ucfirst($loc->type) }})
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @endif
        </div>
    </div>
@endif
