<x-metronic.card title="Definisi KPI">
    <ul class="mb-0">
        @foreach($definitions as $definition)
            <li>{{ $definition }}</li>
        @endforeach
    </ul>
</x-metronic.card>
