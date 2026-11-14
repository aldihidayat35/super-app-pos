@extends('layouts.metronic.app')

@section('title', 'Tutup Shift - ' . config('app.name'))
@section('page_title', 'Tutup Shift Kasir')

@push('styles')
<style>
    .closing-stat-card { border-radius: 0.75rem; transition: transform 0.15s ease; }
    .closing-stat-card:hover { transform: translateY(-2px); }
    .stat-icon { width: 42px; height: 42px; border-radius: 10px; display: inline-flex; align-items: center; justify-content: center; }
    .table-denom th { font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--bs-gray-600); border-bottom: 1px solid var(--bs-gray-200); padding: 0.75rem 0.75rem; }
    .table-denom td { padding: 0.75rem 0.75rem; vertical-align: middle; font-size: 0.875rem; }
    .denom-input { max-width: 120px; font-weight: 700; }
    .diff-box { border-radius: 0.65rem; padding: 1rem 1.25rem; transition: all 0.2s ease; }
</style>
@endpush

@section('toolbar_actions')
    <a href="{{ route('retail.shifts.current') }}" class="btn btn-light btn-sm me-2">
        <i class="ki-outline ki-arrow-left fs-5 me-1"></i>Kembali ke Shift
    </a>
    <a href="{{ route('retail.pos.index') }}" class="btn btn-light-primary btn-sm">
        <i class="ki-outline ki-shop fs-5 me-1"></i>Buka Kasir POS
    </a>
@endsection

@section('content')
<div class="d-flex flex-column gap-6">

    {{-- Alert Panduan --}}
    <div class="alert alert-dismissible bg-light-primary border border-primary border-dashed d-flex flex-column flex-sm-row p-5">
        <i class="ki-outline ki-information-5 fs-2hx text-primary me-4 mb-4 mb-sm-0"></i>
        <div class="d-flex flex-column pe-0 pe-sm-10">
            <h4 class="fw-bolder text-gray-900 mb-1">Proses Penutupan Shift Kasir (Closing POS)</h4>
            <span class="text-gray-700 fs-7">
                Silakan hitung seluruh uang fisik (uang kertas dan logam) yang berada di dalam laci kasir. Masukkan jumlah lembar atau koin pada tabel perhitungan di bawah. Sistem akan mengakumulasi total uang fisik dan membandingkannya dengan estimasi kas sistem secara otomatis.
            </span>
        </div>
    </div>

    {{-- Financial Overview KPI Cards --}}
    <div class="row g-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card closing-stat-card border-0 shadow-sm h-100">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fs-8 fw-bold text-uppercase ls-1">Modal Awal Laci</span>
                        <div class="stat-icon bg-light-info text-info"><i class="ki-outline ki-wallet fs-4"></i></div>
                    </div>
                    <div class="fs-2hx fw-bolder text-gray-900 mb-1">{{ \App\Support\CurrencyFormatter::rupiah($summary['opening_cash'] ?? 0) }}</div>
                    <div class="text-muted fs-8">Kas pembuka sesi shift</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card closing-stat-card border-0 shadow-sm h-100">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fs-8 fw-bold text-uppercase ls-1">Penjualan Tunai (+)</span>
                        <div class="stat-icon bg-light-success text-success"><i class="ki-outline ki-coin fs-4"></i></div>
                    </div>
                    <div class="fs-2hx fw-bolder text-success mb-1">{{ \App\Support\CurrencyFormatter::rupiah($summary['cash_sales'] ?? 0) }}</div>
                    <div class="text-muted fs-8">Penerimaan fisik kasir</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card closing-stat-card border-0 shadow-sm h-100">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fs-8 fw-bold text-uppercase ls-1">Pengeluaran &amp; Refund (-)</span>
                        <div class="stat-icon bg-light-danger text-danger"><i class="ki-outline ki-arrow-down-right fs-4"></i></div>
                    </div>
                    @php
                        $cashOutflow = (float)($summary['expenses'] ?? 0) + (float)($summary['refunds'] ?? 0) - (float)($summary['supplier_refunds'] ?? 0);
                    @endphp
                    <div class="fs-2hx fw-bolder text-danger mb-1">-{{ \App\Support\CurrencyFormatter::rupiah(max(0, $cashOutflow)) }}</div>
                    <div class="text-muted fs-8">Petty cash &amp; retur kasir</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card closing-stat-card border-0 shadow-sm h-100 bg-light-warning">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-gray-700 fs-8 fw-bold text-uppercase ls-1">Estimasi Kas Sistem</span>
                        <div class="stat-icon bg-white text-warning shadow-xs"><i class="ki-outline ki-calculator fs-4"></i></div>
                    </div>
                    <div class="fs-2hx fw-bolder text-warning mb-1" id="expected_cash_display">
                        {{ \App\Support\CurrencyFormatter::rupiah($summary['expected_cash'] ?? 0) }}
                    </div>
                    <div class="text-muted fs-8">Target uang kas di laci</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Form Penutupan Shift --}}
    <form id="closing_shift_form" method="POST" action="{{ route('retail.shifts.close.submit', $shift) }}">
        @csrf

        <div class="row g-6">
            {{-- Kolom Kiri: Tabel Denominasi Lembar & Koin --}}
            <div class="col-lg-7 col-xl-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header border-0 pt-5 pb-2 d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <h3 class="card-title align-items-start flex-column mb-0">
                            <span class="card-label fw-bold fs-5 text-gray-900">Perhitungan Lembar &amp; Koin Fisik</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Masukkan jumlah uang kertas dan logam yang ada di laci</span>
                        </h3>
                        <div class="card-toolbar">
                            <button type="button" class="btn btn-sm btn-light-danger" id="btn_reset_denominations">
                                <i class="ki-outline ki-arrows-circle fs-6 me-1"></i>Reset Pecahan (0)
                            </button>
                        </div>
                    </div>

                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-row-dashed align-middle mb-0 gs-5">
                                <thead>
                                    <tr class="text-muted fw-bold text-uppercase fs-8 bg-light">
                                        <th class="ps-5">Pecahan (Denominasi)</th>
                                        <th>Jenis</th>
                                        <th class="text-center" style="width: 160px;">Jumlah (Qty)</th>
                                        <th class="pe-5 text-end">Subtotal Nilai</th>
                                    </tr>
                                </thead>
                                <tbody class="fw-semibold text-gray-700">
                                    @foreach($denominations as $i => $denomination)
                                    <tr>
                                        <td class="ps-5">
                                            <input type="hidden" name="cash_counts[{{ $i }}][denomination]" value="{{ $denomination }}">
                                            <span class="fw-bolder text-gray-900 fs-6">Rp {{ number_format($denomination, 0, ',', '.') }}</span>
                                        </td>
                                        <td>
                                            @if($denomination >= 1000)
                                                <span class="badge badge-light-primary fs-8">Uang Kertas</span>
                                            @else
                                                <span class="badge badge-light-warning fs-8">Uang Logam</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center justify-content-center">
                                                <input type="number" 
                                                       min="0" 
                                                       name="cash_counts[{{ $i }}][quantity]" 
                                                       value="{{ old("cash_counts.{$i}.quantity", 0) }}" 
                                                       class="form-control form-control-sm form-control-solid text-center denom-qty-input fw-bold" 
                                                       data-denomination="{{ $denomination }}" 
                                                       data-index="{{ $i }}"
                                                       placeholder="0">
                                            </div>
                                        </td>
                                        <td class="pe-5 text-end">
                                            <span class="fw-bolder text-gray-900 fs-6 denom-subtotal-text" id="denom_subtotal_{{ $i }}">Rp 0</span>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light fw-bolder">
                                        <td colspan="3" class="ps-5 text-end fs-6 text-gray-800">Total Akumulasi Pecahan Fisik:</td>
                                        <td class="pe-5 text-end fs-5 text-primary" id="denom_total_text">Rp 0</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Kolom Kanan: Rekonsiliasi, Selisih, & Submit --}}
            <div class="col-lg-5 col-xl-4">
                <div class="card border-0 shadow-sm position-sticky" style="top: 20px;">
                    <div class="card-header border-0 pt-5 pb-2">
                        <h3 class="card-title align-items-start flex-column mb-0">
                            <span class="card-label fw-bold fs-5 text-gray-900">Rekonsiliasi &amp; Penyerahan</span>
                            <span class="text-muted mt-1 fw-semibold fs-7">Verifikasi saldo fisik dan selisih laci</span>
                        </h3>
                    </div>

                    <div class="card-body pt-3">
                        {{-- Input Uang Fisik Total --}}
                        <div class="mb-5">
                            <label class="form-label fw-bold text-gray-800 fs-6 mb-1">
                                Total Kas Fisik Aktual (Laci)
                                <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light-primary text-primary fw-bold">Rp</span>
                                <input type="number" 
                                       step="0.01" 
                                       min="0" 
                                       name="actual_cash_amount" 
                                       id="actual_cash_amount" 
                                       class="form-control form-control-solid fw-bolder fs-4 text-gray-900" 
                                       value="{{ old('actual_cash_amount', $summary['expected_cash'] ?? 0) }}" 
                                       required>
                            </div>
                            <div class="text-muted fs-8 mt-1">Nilai kas fisik terhitung otomatis dari lembar/koin di samping.</div>

                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <button type="button" class="btn btn-xs btn-light-success" id="btn_match_expected">
                                    <i class="ki-outline ki-check fs-7 me-1"></i>Samakan Estimasi (Pas)
                                </button>
                                <button type="button" class="btn btn-xs btn-light-primary" id="btn_sync_from_counts">
                                    <i class="ki-outline ki-sync fs-7 me-1"></i>Pakai Total Pecahan
                                </button>
                            </div>
                        </div>

                        {{-- Dynamic Discrepancy Box --}}
                        <div class="mb-5">
                            <label class="form-label fw-bold text-gray-800 fs-7 mb-2">Status Rekonsiliasi Kas</label>
                            <div id="discrepancy_indicator" class="diff-box bg-light-success border border-success border-dashed text-success">
                                <div class="d-flex align-items-center gap-3">
                                    <i id="discrepancy_icon" class="ki-outline ki-check-circle fs-2x text-success"></i>
                                    <div>
                                        <div class="fw-bolder fs-6" id="discrepancy_title">Kas Fisik Sesuai (Pas)</div>
                                        <div class="fs-8 text-gray-700" id="discrepancy_desc">Uang fisik di laci cocok dengan catatan sistem.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Alasan Selisih --}}
                        <div class="mb-5">
                            <label class="form-label fw-bold text-gray-800 fs-7 d-flex align-items-center justify-content-between mb-1">
                                <span>Alasan Selisih Kas</span>
                                <span id="discrepancy_badge" class="badge badge-light-danger fs-9 d-none">Wajib Diisi</span>
                            </label>
                            <textarea name="discrepancy_reason" 
                                      id="discrepancy_reason" 
                                      rows="3" 
                                      class="form-control form-control-solid fs-7" 
                                      placeholder="Wajib diisi jika terdapat selisih uang fisik (misal: selisih kembalian, pembulatan nota, uang rusak)...">{{ old('discrepancy_reason') }}</textarea>
                            @error('discrepancy_reason')
                                <div class="text-danger fs-8 mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Catatan Serah Terima --}}
                        <div class="mb-6">
                            <label class="form-label fw-bold text-gray-800 fs-7 mb-1">Catatan Serah Terima (Opsional)</label>
                            <textarea name="handover_notes" 
                                      id="handover_notes" 
                                      rows="2" 
                                      class="form-control form-control-solid fs-7" 
                                      placeholder="Catatan untuk kasir berikutnya atau supervisor (kondisi laci, titipan kasbon, dll)...">{{ old('handover_notes') }}</textarea>
                            @error('handover_notes')
                                <div class="text-danger fs-8 mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex flex-column gap-2">
                            <button type="submit" 
                                    id="btn_submit_closing" 
                                    class="btn btn-danger w-100 py-3 fs-6 fw-bold">
                                <i class="ki-outline ki-cross-circle fs-4 me-1"></i>Submit Closing Shift
                            </button>
                            <a href="{{ route('retail.shifts.current') }}" class="btn btn-light w-100 py-2 fs-7">
                                Batal &amp; Kembali ke Shift
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const expectedCash = parseFloat("{{ (float) ($summary['expected_cash'] ?? 0) }}") || 0;
    const actualCashInput = document.getElementById('actual_cash_amount');
    const denomInputs = document.querySelectorAll('.denom-qty-input');
    const denomTotalText = document.getElementById('denom_total_text');
    const btnReset = document.getElementById('btn_reset_denominations');
    const btnMatchExpected = document.getElementById('btn_match_expected');
    const btnSyncFromCounts = document.getElementById('btn_sync_from_counts');
    const discrepancyIndicator = document.getElementById('discrepancy_indicator');
    const discrepancyIcon = document.getElementById('discrepancy_icon');
    const discrepancyTitle = document.getElementById('discrepancy_title');
    const discrepancyDesc = document.getElementById('discrepancy_desc');
    const discrepancyBadge = document.getElementById('discrepancy_badge');
    const discrepancyReasonInput = document.getElementById('discrepancy_reason');
    const closingForm = document.getElementById('closing_shift_form');

    function formatRupiah(amount) {
        return 'Rp ' + Math.round(amount).toLocaleString('id-ID');
    }

    // Hitung total dari seluruh pecahan lembar & koin
    function calculateDenominations() {
        let total = 0;
        let hasAnyInput = false;

        denomInputs.forEach(input => {
            const denom = parseFloat(input.dataset.denomination) || 0;
            const qty = parseInt(input.value) || 0;
            const subtotal = denom * qty;
            const idx = input.dataset.index;
            const subtotalEl = document.getElementById('denom_subtotal_' + idx);
            if (subtotalEl) {
                subtotalEl.textContent = formatRupiah(subtotal);
            }
            if (qty > 0) {
                hasAnyInput = true;
            }
            total += subtotal;
        });

        if (denomTotalText) {
            denomTotalText.textContent = formatRupiah(total);
        }

        return { total, hasAnyInput };
    }

    // Update status perbedaan fisik vs estimasi
    function updateDiscrepancyStatus() {
        const actualCash = parseFloat(actualCashInput.value) || 0;
        const diff = Math.round(actualCash - expectedCash);

        if (diff === 0) {
            // Pas / Sesuai
            discrepancyIndicator.className = 'diff-box bg-light-success border border-success border-dashed text-success';
            discrepancyIcon.className = 'ki-outline ki-check-circle fs-2x text-success';
            discrepancyTitle.textContent = 'Kas Fisik Sesuai (Pas)';
            discrepancyDesc.textContent = 'Uang fisik di laci cocok dengan catatan sistem (Rp 0 selisih).';
            discrepancyBadge.classList.add('d-none');
            discrepancyReasonInput.removeAttribute('required');
        } else if (diff < 0) {
            // Kurang
            const absDiff = Math.abs(diff);
            discrepancyIndicator.className = 'diff-box bg-light-danger border border-danger border-dashed text-danger';
            discrepancyIcon.className = 'ki-outline ki-cross-circle fs-2x text-danger';
            discrepancyTitle.textContent = 'Kas Fisik Kurang: -' + formatRupiah(absDiff);
            discrepancyDesc.textContent = 'Uang di laci kurang Rp ' + absDiff.toLocaleString('id-ID') + ' dari estimasi sistem. Alasan selisih wajib diisi.';
            discrepancyBadge.classList.remove('d-none');
            discrepancyReasonInput.setAttribute('required', 'required');
        } else {
            // Lebih
            discrepancyIndicator.className = 'diff-box bg-light-info border border-info border-dashed text-info';
            discrepancyIcon.className = 'ki-outline ki-information-2 fs-2x text-info';
            discrepancyTitle.textContent = 'Kas Fisik Lebih: +' + formatRupiah(diff);
            discrepancyDesc.textContent = 'Uang di laci berlebih Rp ' + diff.toLocaleString('id-ID') + ' dari estimasi sistem. Alasan selisih wajib diisi.';
            discrepancyBadge.classList.remove('d-none');
            discrepancyReasonInput.setAttribute('required', 'required');
        }
    }

    // Event listener untuk input pecahan
    denomInputs.forEach(input => {
        input.addEventListener('input', function () {
            if (parseInt(this.value) < 0) this.value = 0;
            const res = calculateDenominations();
            if (res.hasAnyInput) {
                actualCashInput.value = res.total;
            }
            updateDiscrepancyStatus();
        });
    });

    // Event listener untuk input uang fisik manual
    if (actualCashInput) {
        actualCashInput.addEventListener('input', function () {
            updateDiscrepancyStatus();
        });
    }

    // Tombol Samakan Estimasi Kas (Pas)
    if (btnMatchExpected) {
        btnMatchExpected.addEventListener('click', function () {
            actualCashInput.value = expectedCash;
            updateDiscrepancyStatus();
        });
    }

    // Tombol Sinkronkan dari Pecahan
    if (btnSyncFromCounts) {
        btnSyncFromCounts.addEventListener('click', function () {
            const res = calculateDenominations();
            actualCashInput.value = res.total;
            updateDiscrepancyStatus();
        });
    }

    // Tombol Reset Pecahan
    if (btnReset) {
        btnReset.addEventListener('click', function () {
            denomInputs.forEach(input => {
                input.value = 0;
            });
            calculateDenominations();
        });
    }

    // Validasi & Konfirmasi Submit Closing
    if (closingForm) {
        closingForm.addEventListener('submit', function (e) {
            const actualCash = parseFloat(actualCashInput.value) || 0;
            const diff = Math.round(actualCash - expectedCash);
            const reason = discrepancyReasonInput.value.trim();

            if (diff !== 0 && !reason) {
                e.preventDefault();
                if (window.Swal) {
                    Swal.fire({
                        title: 'Alasan Selisih Wajib Diisi',
                        text: 'Terdapat selisih kas fisik sebesar ' + (diff > 0 ? '+' : '-') + formatRupiah(Math.abs(diff)) + '. Silakan isi alasan selisih sebelum submit.',
                        icon: 'warning',
                        confirmButtonText: 'Tutup & Lengkapi',
                        customClass: { confirmButton: 'btn btn-primary' }
                    });
                } else {
                    alert('Alasan selisih wajib diisi jika terdapat selisih uang kas fisik.');
                }
                discrepancyReasonInput.focus();
                return;
            }

            e.preventDefault();
            const confirmMsg = 'Submit closing akan mengunci input sesi shift #' + "{{ $shift->number }}" + ' untuk diverifikasi supervisor. Pastikan perhitungan uang fisik sudah benar.\n\nLanjutkan?';

            if (window.Swal) {
                Swal.fire({
                    title: 'Konfirmasi Penutupan Shift?',
                    text: 'Sesi shift #' + "{{ $shift->number }}" + ' akan dikunci untuk diverifikasi oleh supervisor.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Tutup Shift',
                    cancelButtonText: 'Batal',
                    customClass: {
                        confirmButton: 'btn btn-danger',
                        cancelButton: 'btn btn-light'
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        closingForm.submit();
                    }
                });
            } else {
                if (confirm(confirmMsg)) {
                    closingForm.submit();
                }
            }
        });
    }

    // Inisialisasi awal
    calculateDenominations();
    updateDiscrepancyStatus();
});
</script>
@endpush
