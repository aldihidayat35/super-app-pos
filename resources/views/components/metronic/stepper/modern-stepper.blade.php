{{--
  Stepper progres pesanan B2B.
  Garis dibuat per segmen agar hanya menghubungkan dua status yang berdekatan.
--}}
<div class="gt-order-stepper mb-6">
    <div class="gt-order-stepper__viewport">
        <div class="gt-order-stepper__track">
            @foreach($steps as $idx => $step)
                @php
                    $isCompleted = $idx < $currentStepIndex;
                    $isCurrent = $idx === $currentStepIndex;
                @endphp

                <div class="gt-order-stepper__item">
                    @if(! $loop->last)
                        <span
                            class="gt-order-stepper__connector {{ $isCompleted ? 'is-completed' : '' }}"
                            data-step-connector="{{ $idx }}"
                            aria-hidden="true"
                        ></span>
                    @endif

                    <div class="gt-order-stepper__marker {{ $isCompleted ? 'is-completed' : ($isCurrent ? 'is-current' : 'is-pending') }}">
                        <i class="ki-outline {{ $isCompleted ? 'ki-check' : $step['icon'] }} fs-4"></i>
                    </div>

                    <div class="gt-order-stepper__content text-center">
                        <div class="fw-bold fs-7 {{ $idx <= $currentStepIndex ? 'text-gray-900' : 'text-gray-400' }} mb-1">
                            {{ $step['label'] }}
                        </div>

                        @if($step['status'] === 'pending_confirmation' && $order->submitted_at)
                            <div class="text-muted fs-8 text-nowrap">{{ $order->submitted_at->format('d M Y, H:i') }}</div>
                        @elseif($step['status'] === 'warehouse_validation' && $order->approved_at)
                            <div class="text-muted fs-8 text-nowrap">{{ $order->approved_at->format('d M Y, H:i') }}</div>
                        @else
                            <div class="gt-order-stepper__timestamp-placeholder" aria-hidden="true">&nbsp;</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

@once
    @push('styles')
        <style>
            .gt-order-stepper__viewport {
                overflow-x: auto;
                overflow-y: hidden;
                padding: .25rem .25rem .5rem;
                scrollbar-width: thin;
            }

            .gt-order-stepper__track {
                display: flex;
                min-width: 720px;
            }

            .gt-order-stepper__item {
                align-items: center;
                display: flex;
                flex: 1 0 120px;
                flex-direction: column;
                min-width: 0;
                position: relative;
            }

            .gt-order-stepper__connector {
                background-color: var(--bs-gray-300);
                border-radius: 999px;
                height: 2px;
                left: calc(50% + 32px);
                position: absolute;
                right: calc(-50% + 32px);
                top: 27px;
                transition: background-color .25s ease;
                z-index: 0;
            }

            .gt-order-stepper__connector.is-completed {
                background-color: var(--bs-primary);
            }

            .gt-order-stepper__marker {
                align-items: center;
                background-color: var(--bs-gray-100);
                border: 2px solid var(--bs-gray-300);
                border-radius: 50%;
                color: var(--bs-gray-400);
                display: flex;
                flex: 0 0 56px;
                height: 56px;
                justify-content: center;
                position: relative;
                transition: border-color .2s ease, background-color .2s ease, color .2s ease, transform .2s ease;
                width: 56px;
                z-index: 1;
            }

            .gt-order-stepper__marker.is-completed {
                background-color: var(--bs-primary);
                border-color: var(--bs-primary);
                color: var(--bs-white);
            }

            .gt-order-stepper__marker.is-current {
                background-color: var(--bs-primary-light);
                border-color: var(--bs-primary);
                box-shadow: 0 0 0 5px rgba(var(--bs-primary-rgb), .10);
                color: var(--bs-primary);
            }

            .gt-order-stepper__marker:hover {
                transform: translateY(-2px);
            }

            .gt-order-stepper__content {
                margin-top: .85rem;
                padding-inline: .5rem;
                width: 100%;
            }

            .gt-order-stepper__timestamp-placeholder {
                font-size: .85rem;
                min-height: 1.25rem;
            }

            [data-bs-theme='dark'] .gt-order-stepper__marker.is-pending {
                background-color: var(--bs-gray-200);
            }

            @media (max-width: 767.98px) {
                .gt-order-stepper__track {
                    min-width: 660px;
                }

                .gt-order-stepper__item {
                    flex-basis: 110px;
                }

                .gt-order-stepper__marker {
                    flex-basis: 48px;
                    height: 48px;
                    width: 48px;
                }

                .gt-order-stepper__connector {
                    left: calc(50% + 28px);
                    right: calc(-50% + 28px);
                    top: 23px;
                }
            }
        </style>
    @endpush
@endonce
