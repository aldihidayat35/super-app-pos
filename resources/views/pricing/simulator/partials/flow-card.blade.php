<x-metronic.card title="Alur Perhitungan Harga" class="flow-visualization">
    @if (!$result)
        <div class="text-center py-6">
            <i class="ki-outline ki-calculator fs-1 text-gray-400 mb-3 d-block"></i>
            <div class="text-muted fs-7">Alur perhitungan akan muncul setelah simulasi dijalankan</div>
        </div>
    @else
        <div class="process-flow">
            <!-- Step 1: Input -->
            <div class="step-row mb-3">
                <div class="d-flex align-items-start">
                    <div class="step-badge bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;flex-shrink:0">1</div>
                    <div class="step-card border border-primary border-opacity-25 rounded p-3 ms-3 flex-grow-1">
                        <div class="fw-semibold text-primary mb-1"><i class="ki-outline ki-input me-2"></i>Penerimaan Input</div>
                        <div class="row g-2 fs-7">
                            <div class="col-md-3"><span class="text-muted">Produk:</span> {{ $result['product_id'] }}</div>
                            <div class="col-md-3"><span class="text-muted">Channel:</span> <span class="badge bg-light">{{ ucfirst($filters['channel'] ?? 'retail') }}</span></div>
                            <div class="col-md-3"><span class="text-muted">Qty:</span> {{ $filters['quantity'] ?? 1 }} unit</div>
                            <div class="col-md-3"><span class="text-muted">Cabang:</span> {{ $filters['branch_id'] ? '#' . $filters['branch_id'] : 'Semua' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-3"><i class="ki-outline ki-arrow-down fs-4 text-muted"></i></div>

            <!-- Step 2: HPP -->
            <div class="step-row mb-3">
                <div class="d-flex align-items-start">
                    <div class="step-badge bg-success text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;flex-shrink:0">2</div>
                    <div class="step-card border border-success border-opacity-25 rounded p-3 ms-3 flex-grow-1">
                        <div class="fw-semibold text-success mb-1"><i class="ki-outline ki-coin me-2"></i>Perhitungan HPP</div>
                        <div class="d-flex align-items-center gap-3">
                            <div class="formula bg-light rounded p-2 fs-7 font-monospace flex-grow-1">
                                HPP/unit: <strong>Rp {{ number_format((float) $result['hpp_unit'], 0, ',', '.') }}</strong>
                            </div>
                            <span class="text-muted fs-7">Diambil dari cost_price produk</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-3"><i class="ki-outline ki-arrow-down fs-4 text-muted"></i></div>

            <!-- Step 3: Margin Rule -->
            <div class="step-row mb-3">
                <div class="d-flex align-items-start">
                    <div class="step-badge bg-warning text-dark d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;flex-shrink:0">3</div>
                    <div class="step-card border border-warning border-opacity-25 rounded p-3 ms-3 flex-grow-1">
                        <div class="fw-semibold text-warning mb-1"><i class="ki-outline ki-shield-check me-2"></i>Aturan Margin</div>
                        <div class="row g-2">
                            <div class="col-md-7">
                                <div class="fs-7">
                                    <span class="text-muted">Metode:</span>
                                    <span class="badge {{ $result['margin_method'] == 'nominal' ? 'bg-warning text-dark' : 'bg-info' }} ms-1">{{ $result['margin_method'] == 'nominal' ? 'Nominal' : 'Persentase' }}</span>
                                </div>
                                @if($result['margin_method'] == 'nominal')
                                <div class="formula bg-light rounded p-2 mt-2 fs-7 font-monospace">
                                    Minimum = HPP + Margin<br>
                                    = {{ number_format((float) $result['hpp_unit'], 0, ',', '.') }} + {{ number_format((float) $result['margin_amount_value'], 0, ',', '.') }} = <span class="fw-bold text-warning">{{ number_format((float) $result['minimum_price'], 0, ',', '.') }}</span>
                                </div>
                                @else
                                <div class="formula bg-light rounded p-2 mt-2 fs-7 font-monospace">
                                    Minimum = HPP × (1 + {{ $result['margin_percent_value'] }}%)<br>
                                    = {{ number_format((float) $result['hpp_unit'], 0, ',', '.') }} × (1 + {{ $result['margin_percent_value'] }}%) = <span class="fw-bold text-warning">{{ number_format((float) $result['minimum_price'], 0, ',', '.') }}</span>
                                </div>
                                @endif
                            </div>
                            <div class="col-md-5">
                                <div class="fs-7">
                                    <span class="text-muted">Aturan:</span>
                                    <div class="fw-semibold mt-1">{{ $result['rule_name'] ?? 'Default' }}</div>
                                    <div class="text-muted fs-7 mt-1">Prioritas: {{ $result['rule_priority'] ?? 9999 }}</div>
                                    @if(isset($result['maximum_price']) && $result['maximum_price'])
                                    <div class="text-muted fs-7 mt-1">Maks: Rp {{ number_format((float) $result['maximum_price'], 0, ',', '.') }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-3"><i class="ki-outline ki-arrow-down fs-4 text-muted"></i></div>

            <!-- Step 4: Candidates -->
            <div class="step-row mb-3">
                <div class="d-flex align-items-start">
                    <div class="step-badge bg-info text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;flex-shrink:0">4</div>
                    <div class="step-card border border-info border-opacity-25 rounded p-3 ms-3 flex-grow-1">
                        <div class="fw-semibold text-info mb-2"><i class="ki-outline ki-list-check me-2"></i>Proses Pemilihan Harga</div>
                        <div class="candidate-selection">
                            <div class="fs-7 text-muted mb-2">Kandidat yang dievaluasi (prioritas terkecil menang):</div>
                            @php $sortedCandidates = collect($result['candidates'] ?? [])->sortBy('priority'); @endphp
                            @forelse($sortedCandidates as $candidate)
                                <div class="d-flex align-items-center justify-content-between bg-light rounded p-2 mb-1 {{ $candidate['source'] == $result['selected_source'] ? 'border border-success border-opacity-50' : '' }}">
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge {{ $candidate['source'] == 'computed_minimum' ? 'bg-warning text-dark' : ($candidate['source'] == 'customer_special' ? 'bg-primary' : 'bg-secondary') }} fs-7">
                                            {{ $candidate['source'] == 'computed_minimum' ? 'Fallback' : ($candidate['source'] == 'customer_special' ? 'Customer' : 'Product') }}
                                        </span>
                                        <span class="fs-7 text-muted">{{ $candidate['reason'] ?? '-' }}</span>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-bold fs-7">Rp {{ number_format((float) $candidate['price_base'], 0, ',', '.') }}</span>
                                        <span class="badge bg-light text-dark fs-7">P{{ $candidate['priority'] }}</span>
                                        @if($candidate['source'] == $result['selected_source'])
                                            <i class="ki-outline ki-check-circle fs-4 text-success"></i>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="fs-7 text-muted">Tidak ada kandidat, menggunakan fallback minimum.</div>
                            @endforelse
                        </div>
                        <div class="mt-2 p-2 bg-info bg-opacity-10 rounded">
                            <div class="fs-7">
                                <i class="ki-outline ki-arrow-right me-1"></i>
                                Terpilih: <strong>
                                    @if($result['selected_source'] == 'computed_minimum') Harga Minimum (Fallback)
                                    @elseif($result['selected_source'] == 'customer_special') Harga Customer
                                    @elseif($result['selected_source'] == 'product_price') Harga Product
                                    @else {{ $result['selected_source'] }}
                                    @endif
                                    </strong>
                                <span class="text-muted ms-1">(prioritas terkecil)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mb-3"><i class="ki-outline ki-arrow-down fs-4 text-muted"></i></div>

            <!-- Step 5: Discount -->
            <div class="step-row mb-3">
                <div class="d-flex align-items-start">
                    <div class="step-badge text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;background:#a855f7;flex-shrink:0">5</div>
                    <div class="step-card border rounded p-3 ms-3 flex-grow-1" style="border-color:#a855f7 !important">
                        <div class="fw-semibold mb-1" style="color:#a855f7"><i class="ki-outline ki-discount me-2"></i>Penerapan Diskon</div>
                        @if((float)($filters['discount_percent'] ?? 0) > 0)
                            <div class="formula bg-light rounded p-2 fs-7 font-monospace mb-2">
                                Diskon = {{ number_format((float) $result['selected_price'], 0, ',', '.') }} × {{ $filters['discount_percent'] }}% = <span class="fw-bold" style="color:#a855f7">{{ number_format((float) ((float) $result['selected_price'] - (float) $result['discounted_price']), 0, ',', '.') }}</span><br>
                                Akhir = {{ number_format((float) $result['selected_price'], 0, ',', '.') }} - {{ number_format((float) ((float) $result['selected_price'] - (float) $result['discounted_price']), 0, ',', '.') }} = <span class="fw-bold" style="color:#a855f7">{{ number_format((float) $result['discounted_price'], 0, ',', '.') }}</span>
                            </div>
                            <div class="fs-7"><span class="text-muted">Batas maks:</span> <span class="badge bg-light text-dark">{{ number_format((float) $result['max_discount_percent'], 0, ',', '.') }}%</span></div>
                        @else
                            <div class="fs-7 text-muted">Tidak ada diskon — harga tetap Rp {{ number_format((float) $result['selected_price'], 0, ',', '.') }}</div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="text-center mb-3"><i class="ki-outline ki-arrow-down fs-4 text-muted"></i></div>

            <!-- Step 6: Validation -->
            <div class="step-row mb-3">
                <div class="d-flex align-items-start">
                    <div class="step-badge {{ $result['approval_required'] ? 'bg-danger' : 'bg-success' }} text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;flex-shrink:0">6</div>
                    <div class="step-card border {{ $result['approval_required'] ? 'border-danger' : 'border-success' }} border-opacity-25 rounded p-3 ms-3 flex-grow-1">
                        <div class="fw-semibold {{ $result['approval_required'] ? 'text-danger' : 'text-success' }} mb-2">
                            <i class="ki-outline {{ $result['approval_required'] ? 'ki-exclamation-triangle' : 'ki-check-circle' }} me-2"></i>
                            Validasi & Approval
                        </div>
                        <div class="validation-checks fs-7">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                @if(in_array('below_minimum', $result['approval_reasons'] ?? []))
                                    <i class="ki-outline ki-close-circle text-danger"></i>
                                    <span class="text-danger">Di bawah minimum (Rp {{ number_format((float) $result['minimum_price'], 0, ',', '.') }})</span>
                                @else
                                    <i class="ki-outline ki-check-circle text-success"></i>
                                    <span class="text-success">Memenuhi minimum (≥ Rp {{ number_format((float) $result['minimum_price'], 0, ',', '.') }})</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2 mb-1">
                                @if(in_array('overpricing', $result['approval_reasons'] ?? []))
                                    <i class="ki-outline ki-close-circle text-warning"></i>
                                    <span class="text-warning">Overpricing (di atas toleransi)</span>
                                @else
                                    <i class="ki-outline ki-check-circle text-success"></i>
                                    <span class="text-success">Harga dalam toleransi</span>
                                @endif
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                @if(in_array('discount_exceeds_cap', $result['approval_reasons'] ?? []))
                                    <i class="ki-outline ki-close-circle text-info"></i>
                                    <span class="text-info">Diskon {{ $filters['discount_percent'] ?? 0 }}% > batas {{ $result['max_discount_percent'] }}%</span>
                                @else
                                    <i class="ki-outline ki-check-circle text-success"></i>
                                    <span class="text-success">Diskon dalam batas</span>
                                @endif
                            </div>
                        </div>
                        @if($result['approval_required'])
                            <div class="mt-2 p-2 bg-danger bg-opacity-10 rounded">
                                <div class="fw-semibold text-danger fs-7"><i class="ki-outline ki-alert me-1"></i>Memerlukan Persetujuan</div>
                            </div>
                        @else
                            <div class="mt-2 p-2 bg-success bg-opacity-10 rounded">
                                <div class="fw-semibold text-success fs-7"><i class="ki-outline ki-check-circle me-1"></i>Harga Aman - Otomatis Disetujui</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="text-center mb-3"><i class="ki-outline ki-arrow-down fs-4 text-muted"></i></div>

            <!-- Step 7: Result -->
            <div class="step-row">
                <div class="d-flex align-items-start">
                    <div class="step-badge bg-success text-white d-inline-flex align-items-center justify-content-center" style="width:32px;height:32px;border-radius:50%;flex-shrink:0">7</div>
                    <div class="step-card border border-success border-opacity-50 rounded p-4 ms-3 flex-grow-1 bg-success bg-opacity-10">
                        <div class="fw-semibold text-success mb-3"><i class="ki-outline ki-flag me-2"></i>Hasil Akhir</div>
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="fs-1 fw-bolder text-success mb-1">Rp {{ number_format((float) $result['discounted_price'], 0, ',', '.') }}</div>
                                <div class="text-muted fs-7">Harga jual final</div>
                            </div>
                            <div class="col-md-6">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 bg-white rounded">
                                            <div class="text-muted fs-7">Margin</div>
                                            <div class="fw-bold {{ (float) $result['margin_amount'] >= 0 ? 'text-success' : 'text-danger' }} fs-3">{{ number_format((float) $result['margin_percent'], 1) }}%</div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 bg-white rounded">
                                            <div class="text-muted fs-7">Status</div>
                                            <div class="fw-bold {{ $result['approval_required'] ? 'text-warning' : 'text-success' }} fs-3">{{ $result['approval_required'] ? 'Approval' : 'Approved' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-metronic.card>
