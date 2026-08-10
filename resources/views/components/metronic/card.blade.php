@props(['title' => null, 'subtitle' => null, 'flush' => false])
<section {{ $attributes->class(['card']) }}>
    @if ($title || isset($toolbar))<header class="card-header border-0"><div class="card-title d-flex flex-column align-items-start"><h3 class="fw-bold mb-1">{{ $title }}</h3>@if($subtitle)<span class="text-muted fs-7 fw-normal">{{ $subtitle }}</span>@endif</div>@isset($toolbar)<div class="card-toolbar">{{ $toolbar }}</div>@endisset</header>@endif
    <div class="card-body {{ $flush ? 'p-0' : '' }}">{{ $slot }}</div>
    @isset($footer)<footer class="card-footer">{{ $footer }}</footer>@endisset
</section>
