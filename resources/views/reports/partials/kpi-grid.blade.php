<div class="row g-5 mb-5">
    @foreach($items as $item)
        <div class="col-md-3">
            <x-metronic.card :title="$item['label']">
                <div class="fs-2 fw-bold text-{{ $item['color'] ?? 'primary' }}">{{ $item['value'] }}</div>
                <div class="text-muted">{{ $item['description'] ?? 'Periode filter aktif' }}</div>
            </x-metronic.card>
        </div>
    @endforeach
</div>
