<div class="report-profile-nav d-flex gap-2 overflow-auto pb-2 mb-5">
    @foreach($profile['reports'] as $key => $item)
        <a href="{{ route("reports.profiles.{$profileKey}.show", array_filter(['report' => $key, 'work_location_id' => $selectedLocation?->id])) }}"
            @class(['btn btn-sm flex-shrink-0', 'btn-primary' => $key === $reportKey, 'btn-light' => $key !== $reportKey])>
            {{ $item['label'] }}
        </a>
    @endforeach
    @if($canExport)
        <a href="{{ route('reports.exports.index') }}" class="btn btn-sm btn-light-dark ms-auto flex-shrink-0"><i class="ki-outline ki-folder fs-6"></i>Riwayat</a>
        <form method="POST" action="{{ route('reports.profiles.exports.store', ['profile' => $profileKey, 'report' => $reportKey]) }}" class="d-flex gap-2 flex-shrink-0">
            @csrf
            @foreach(request()->except(['format', '_token']) as $name => $value)
                @if(is_scalar($value))<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endif
            @endforeach
            <select name="format" class="form-select form-select-sm w-auto" aria-label="Format export">
                <option value="xlsx">Excel</option><option value="pdf">PDF</option><option value="csv">CSV</option>
            </select>
            <button class="btn btn-sm btn-dark"><i class="ki-outline ki-exit-down fs-6"></i>Export</button>
        </form>
    @endif
</div>
