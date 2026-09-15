@php
    $flowId = (string) ($flow['id'] ?? 'workflow');
@endphp

<figure class="guide-diagram guide-workflow" data-guide-flow="{{ $flowId }}" aria-label="Diagram alur: {{ $flow['title'] }}">
    <figcaption class="guide-workflow-header">
        <span class="guide-workflow-kicker"><i class="ki-outline ki-notepad me-1"></i>Diagram alur sistem</span>
        <strong>{{ $flow['title'] }}</strong>
        <span>{{ $flow['summary'] }}</span>
    </figcaption>

    <div class="guide-workflow-body">
        @foreach ($flow['lanes'] as $lane)
            @php
                $steps = $lane['steps'] ?? [];
                $tone = $lane['tone'] ?? 'primary';
            @endphp
            <section class="guide-workflow-lane" aria-label="{{ $lane['label'] }}">
                <span class="guide-workflow-lane-label text-{{ $tone }}">{{ $lane['label'] }}</span>
                <ol class="guide-workflow-track">
                    @foreach ($steps as $index => $step)
                        @php
                            $stepData = is_array($step) ? $step : ['label' => $step];
                            $kind = $stepData['kind'] ?? 'action';
                        @endphp
                        <li class="guide-workflow-step guide-workflow-step-{{ $kind }}">
                            <div class="guide-workflow-node border-{{ $tone }}">
                                <span class="guide-workflow-number bg-light-{{ $tone }} text-{{ $tone }}">
                                    {{ $kind === 'decision' ? '?' : $index + 1 }}
                                </span>
                                <strong>{{ $stepData['label'] }}</strong>
                                @if (filled($stepData['detail'] ?? null))
                                    <small>{{ $stepData['detail'] }}</small>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
</figure>
