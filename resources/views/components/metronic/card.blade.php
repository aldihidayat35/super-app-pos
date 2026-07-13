@props(['title' => null, 'flush' => false])
<section {{ $attributes->class(['card']) }}>
    @if ($title || isset($toolbar))<header class="card-header border-0"><h3 class="card-title fw-bold">{{ $title }}</h3>@isset($toolbar)<div class="card-toolbar">{{ $toolbar }}</div>@endisset</header>@endif
    <div class="card-body {{ $flush ? 'p-0' : '' }}">{{ $slot }}</div>
    @isset($footer)<footer class="card-footer">{{ $footer }}</footer>@endisset
</section>
