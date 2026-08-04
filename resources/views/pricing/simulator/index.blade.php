@extends('layouts.metronic.app')

@section('title', 'Simulasi Margin - ' . config('app.name'))
@section('page_title', 'Simulasi Margin')

@section('content')
    @php($canSensitive = auth()->user()?->can('margins.view_sensitive'))

    <div class="row g-6 align-items-start">

        {{-- ====================================================== --}}
        {{-- KOLOM KIRI: Parameter Simulasi dan Hasil Simulasi --}}
        {{-- ====================================================== --}}
        <div class="col-xl-7 col-lg-7">

            <div class="d-flex flex-column gap-6">

                {{-- Card Parameter Simulasi --}}
                <x-metronic.card title="Parameter Simulasi">
                    <form
                        method="GET"
                        action="{{ route('pricing.simulator.index') }}"
                        id="margin-simulator-form"
                    >
                        {{-- Produk --}}
                        <x-metronic.form-group
                            name="product_id"
                            label="Produk"
                            required
                        >
                            <select name="product_id" class="form-select">
                                <option value="">Pilih produk</option>

                                @foreach ($products as $product)
                                    <option
                                        value="{{ $product->id }}"
                                        @selected(($filters['product_id'] ?? '') == $product->id)
                                    >
                                        {{ $product->sku }} — {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-metronic.form-group>

                        {{-- Channel dan Quantity --}}
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-metronic.form-group
                                    name="channel"
                                    label="Channel"
                                    required
                                >
                                    <select name="channel" class="form-select">
                                        <option
                                            value="retail"
                                            @selected(($filters['channel'] ?? '') === 'retail')
                                        >
                                            Retail
                                        </option>

                                        <option
                                            value="b2b"
                                            @selected(($filters['channel'] ?? '') === 'b2b')
                                        >
                                            B2B
                                        </option>

                                        <option
                                            value="pos"
                                            @selected(($filters['channel'] ?? '') === 'pos')
                                        >
                                            POS
                                        </option>
                                    </select>
                                </x-metronic.form-group>
                            </div>

                            <div class="col-md-6">
                                <x-metronic.form-group
                                    name="quantity"
                                    label="Qty"
                                    required
                                >
                                    <input
                                        type="number"
                                        step="1"
                                        min="1"
                                        name="quantity"
                                        value="{{ qty_input($filters['quantity'] ?? 1) }}"
                                        class="form-control"
                                    >
                                </x-metronic.form-group>
                            </div>
                        </div>

                        {{-- Cabang --}}
                        <x-metronic.form-group
                            name="branch_id"
                            label="Cabang"
                        >
                            <select name="branch_id" class="form-select">
                                <option value="">Semua cabang</option>

                                @foreach ($branches as $branch)
                                    <option
                                        value="{{ $branch->id }}"
                                        @selected(($filters['branch_id'] ?? '') == $branch->id)
                                    >
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-metronic.form-group>

                        {{-- Pelanggan --}}
                        <x-metronic.form-group
                            name="customer_id"
                            label="Pelanggan"
                        >
                            <select name="customer_id" class="form-select">
                                <option value="">Umum</option>

                                @foreach ($customers as $customer)
                                    <option
                                        value="{{ $customer->id }}"
                                        @selected(($filters['customer_id'] ?? '') == $customer->id)
                                    >
                                        {{ $customer->business_name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-metronic.form-group>

                        {{-- Harga Uji dan Diskon --}}
                        <div class="row g-3">
                            <div class="col-md-6">
                                <x-metronic.form-group
                                    name="requested_price"
                                    label="Harga Uji"
                                >
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        name="requested_price"
                                        value="{{ $filters['requested_price'] ?? '' }}"
                                        class="form-control"
                                    >
                                </x-metronic.form-group>
                            </div>

                            <div class="col-md-6">
                                <x-metronic.form-group
                                    name="discount_percent"
                                    label="Diskon (%)"
                                >
                                    <input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        name="discount_percent"
                                        value="{{ $filters['discount_percent'] ?? 0 }}"
                                        class="form-control"
                                    >
                                </x-metronic.form-group>
                            </div>
                        </div>

                        {{-- Tombol Hitung --}}
                        <button
                            class="btn btn-primary w-100"
                            id="margin-simulator-submit"
                            type="submit"
                        >
                            <i class="ki-duotone ki-calculator fs-3 me-2"></i>

                            <span class="indicator-label">
                                Hitung Simulasi
                            </span>

                            <span class="indicator-progress d-none">
                                Menghitung...

                                <span
                                    class="spinner-border spinner-border-sm align-middle ms-2"
                                ></span>
                            </span>
                        </button>
                    </form>

                    {{-- Informasi Rule --}}
                    @if ($result)
                        <div class="mt-5 pt-5 border-top">
                            <div class="d-flex gap-2 flex-wrap">

                                <span class="badge bg-light text-dark fs-7">
                                    <i class="ki-outline ki-box fs-6 me-1"></i>
                                    Produk: #{{ $result['product_id'] }}
                                </span>

                                @if (!empty($result['rule_id']))
                                    <span class="badge bg-light text-dark fs-7">
                                        <i class="ki-outline ki-shield-check fs-6 me-1"></i>
                                        Rule: #{{ $result['rule_id'] }}
                                    </span>
                                @endif

                                @if (!empty($result['rule_name']))
                                    <span class="badge bg-primary fs-7">
                                        {{ $result['rule_name'] }}
                                    </span>
                                @endif

                            </div>
                        </div>
                    @endif
                </x-metronic.card>

                {{-- Card Hasil Simulasi --}}
                <x-metronic.card title="Hasil Simulasi">
                    @if ($result)
                        <div class="overflow-auto">
                            @include('pricing.simulator.partials.result-card', [
                                'result' => $result,
                                'canSensitive' => $canSensitive,
                            ])
                        </div>
                    @else
                        <x-metronic.empty-state
                            title="Belum ada simulasi"
                            description="Pilih produk dan channel untuk melihat minimum price, kandidat harga, margin, dan kebutuhan approval."
                            icon="ki-calculator"
                        />
                    @endif
                </x-metronic.card>

            </div>
        </div>

        {{-- ====================================================== --}}
        {{-- KOLOM KANAN: Alur Perhitungan Harga --}}
        {{-- ====================================================== --}}
        <div class="col-xl-5 col-lg-5">
            @include('pricing.simulator.partials.flow-card', [
                'result' => $result,
                'canSensitive' => $canSensitive,
            ])
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('margin-simulator-form');
            const submitBtn = document.getElementById('margin-simulator-submit');

            if (!form || !submitBtn) {
                return;
            }

            form.addEventListener('submit', function() {
                const label = submitBtn.querySelector('.indicator-label');
                const progress = submitBtn.querySelector('.indicator-progress');

                if (label) {
                    label.classList.add('d-none');
                }

                if (progress) {
                    progress.classList.remove('d-none');
                }

                submitBtn.setAttribute('disabled', 'disabled');
                submitBtn.classList.add('disabled');
            });
        });
    </script>
@endpush
