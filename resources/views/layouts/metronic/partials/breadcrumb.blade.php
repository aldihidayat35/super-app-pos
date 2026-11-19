@php
    $breadcrumbHomeUrl = route('dashboard');
    if (auth()->check() && auth()->user()->hasAnyRole(['owner_viewer', 'owner_approver'])) {
        $breadcrumbHomeUrl = route('reports.profiles.owner.show', ['report' => 'overview']);
    }
@endphp
<ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1">
    <li class="breadcrumb-item text-muted"><a href="{{ $breadcrumbHomeUrl }}" class="text-muted text-hover-primary">Beranda</a></li>
    @foreach (($breadcrumbs ?? []) as $breadcrumb)
        <li class="breadcrumb-item"><span class="bullet bg-gray-500 w-5px h-2px"></span></li>
        <li class="breadcrumb-item {{ $loop->last ? 'text-gray-900' : 'text-muted' }}">
            @if (!empty($breadcrumb['route']) && !$loop->last)<a href="{{ route($breadcrumb['route']) }}" class="text-muted text-hover-primary">{{ $breadcrumb['label'] }}</a>@else {{ $breadcrumb['label'] }} @endif
        </li>
    @endforeach
</ul>
