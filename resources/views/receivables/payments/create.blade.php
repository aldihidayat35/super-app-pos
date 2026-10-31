@extends('layouts.metronic.app')

@section('title', 'Input Pembayaran Piutang')
@section('page_title', 'Pencatatan Pembayaran Piutang')

@section('content')
@php
    use App\Support\CurrencyFormatter;
    use App\Support\Decimal;
@endphp

<x-metronic.page-title 
    title="Pencatatan Pembayaran Piutang" 
    description="Alokasikan penerimaan pembayaran piutang pelanggan untuk multi-invoice/nota, pembayaran sebagian, dan arsip bukti transaksi." 
/>

@if($errors->any())
    <div class="alert alert-danger d-flex align-items-center p-5 mb-6">
        <i class="ki-outline ki-information-5 fs-2hx text-danger me-4"></i>
        <div class="d-flex flex-column">
            <h4 class="mb-1 text-danger">Terdapat Kesalahan Input</h4>
            <span>{{ $errors->first() }}</span>
        </div>
    </div>
@endif

<form method="POST" action="{{ route('receivables.payments.store') }}" enctype="multipart/form-data" id="payment-form">
    @csrf
    <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', (string) Illuminate\Support\Str::uuid()) }}">

    <div class="row g-6 mb-6">
        <!-- Panel 1: Pemilihan Pelanggan & Info Finansial -->
        <div class="col-12">
            <x-metronic.card title="1. Identitas & Informasi Finansial Pelanggan">
                <div class="row g-4 align-items-center">
                    <div class="col-md-6 col-lg-5">
                        <label class="form-label fw-bold required">Pilih Pelanggan</label>
                        <select name="customer_id" id="customer-picker" class="form-select form-select-solid" data-control="select2" data-placeholder="Cari nama atau kode pelanggan...">
                            <option value="">Pilih pelanggan...</option>
                            @foreach($customers as $c)
                                <option value="{{ $c->id }}" @selected((string) $selectedCustomerId === (string) $c->id)>
                                    {{ $c->business_name }} ({{ $c->code }}) · Sisa: {{ CurrencyFormatter::rupiah($c->receivable_balance) }}
                                </option>
                            @endforeach
                        </select>
                        <div class="form-text text-muted">Pilih pelanggan untuk memuat daftar tagihan yang belum lunas secara otomatis.</div>
                    </div>

                    @if($selectedCustomer)
                    <div class="col-md-6 col-lg-7">
                        <div class="d-flex flex-wrap align-items-center justify-content-between p-4 bg-light rounded border border-gray-300">
                            <div>
                                <span class="badge badge-light-primary fw-bold mb-1">{{ $selectedCustomer->type?->label() ?? 'Pelanggan' }}</span>
                                <div class="fs-5 fw-bolder text-gray-900">{{ $selectedCustomer->business_name }}</div>
                                <div class="text-muted fs-8">
                                    Kode: <strong class="text-gray-800">{{ $selectedCustomer->code }}</strong>
                                    @if($selectedCustomer->whatsapp_number)
                                        · WA: <strong class="text-gray-800">{{ $selectedCustomer->whatsapp_number }}</strong>
                                    @endif
                                    @if($selectedCustomer->business_address)
                                        · {{ Str::limit($selectedCustomer->business_address, 45) }}
                                    @endif
                                </div>
                            </div>
                            <div class="text-end mt-2 mt-sm-0">
                                <a href="{{ route('receivables.customers.show', $selectedCustomer) }}" target="_blank" class="btn btn-sm btn-light-primary">
                                    <i class="ki-outline ki-eye fs-7 me-1"></i>Buku Besar Piutang
                                </a>
                            </div>
                        </div>
                    </div>
                    @endif
                </div>

                @if($selectedCustomer)
                <div class="separator separator-dashed my-5"></div>
                <!-- Kartu Status Plafon & Saldo Piutang Pelanggan -->
                <div class="row g-4">
                    <div class="col-sm-6 col-xl-3">
                        <div class="card bg-light-danger border border-danger border-opacity-25 shadow-xs">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fs-7 fw-bold text-gray-700">Total Saldo Piutang</span>
                                    <i class="ki-outline ki-bill fs-3 text-danger"></i>
                                </div>
                                <div class="fs-2x fw-bolder text-danger">
                                    {{ CurrencyFormatter::rupiah($selectedCustomer->receivable_balance) }}
                                </div>
                                <div class="text-muted fs-9">Total tagihan belum dibayar</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card bg-light-primary border border-primary border-opacity-25 shadow-xs">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fs-7 fw-bold text-gray-700">Plafon Batas Kredit</span>
                                    <i class="ki-outline ki-shield-tick fs-3 text-primary"></i>
                                </div>
                                <div class="fs-2x fw-bolder text-primary">
                                    {{ CurrencyFormatter::rupiah($selectedCustomer->credit_limit) }}
                                </div>
                                <div class="text-muted fs-9">Limit pinjaman disepakati</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        @php
                            $availableCredit = Decimal::sub((string) $selectedCustomer->credit_limit, (string) $selectedCustomer->receivable_balance, 2);
                            $isOverLimit = Decimal::compare($availableCredit, '0', 2) < 0;
                        @endphp
                        <div class="card {{ $isOverLimit ? 'bg-light-danger border-danger' : 'bg-light-success border-success' }} border border-opacity-25 shadow-xs">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fs-7 fw-bold text-gray-700">Sisa Plafon Tersedia</span>
                                    <i class="ki-outline {{ $isOverLimit ? 'ki-cross-circle text-danger' : 'ki-check-circle text-success' }} fs-3"></i>
                                </div>
                                <div class="fs-2x fw-bolder {{ $isOverLimit ? 'text-danger' : 'text-success' }}">
                                    {{ CurrencyFormatter::rupiah($availableCredit) }}
                                </div>
                                <div class="text-muted fs-9">{{ $isOverLimit ? 'Melebihi limit kredit!' : 'Kredit aman dapat digunakan' }}</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-xl-3">
                        <div class="card bg-light-info border border-info border-opacity-25 shadow-xs">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <span class="fs-7 fw-bold text-gray-700">Termin Jatuh Tempo</span>
                                    <i class="ki-outline ki-calendar fs-3 text-info"></i>
                                </div>
                                <div class="fs-2x fw-bolder text-info">
                                    {{ $selectedCustomer->payment_term_days ?? 0 }} Hari
                                </div>
                                <div class="text-muted fs-9">Standar jatuh tempo pembayaran</div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </x-metronic.card>
        </div>

        <!-- Panel 2: Parameter Transaksi Pembayaran -->
        <div class="col-12">
            <x-metronic.card title="2. Rincian & Metode Pembayaran">
                <div class="row g-4">
                    <div class="col-md-3">
                        <label class="form-label fw-bold required">Metode Pembayaran</label>
                        <select name="method" class="form-select form-select-solid" data-control="select2" data-hide-search="true">
                            @foreach($methods as $value => $label)
                                @if($value !== 'credit')
                                    <option value="{{ $value }}" @selected(old('method', 'cash') === $value)>{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold required">Tanggal Pembayaran</label>
                        <input type="date" name="payment_date" value="{{ old('payment_date', now()->toDateString()) }}" class="form-control form-control-solid" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Nomor Referensi Transaksi</label>
                        <input type="text" name="reference_no" value="{{ old('reference_no') }}" placeholder="Contoh: BUKTI-TRF-001 / No. Slip" class="form-control form-control-solid">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Unggah Bukti Bayar</label>
                        <input type="file" name="proof" accept="image/*,.pdf" class="form-control form-control-solid">
                        <div class="form-text text-muted fs-9">Format: JPG, PNG, atau PDF (maks. 4MB)</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">Catatan Pembayaran</label>
                        <input type="text" name="notes" value="{{ old('notes') }}" placeholder="Catatan tambahan mengenai transaksi pembayaran ini (opsional)..." class="form-control form-control-solid">
                    </div>
                </div>
            </x-metronic.card>
        </div>

        <!-- Panel 3: Daftar Tagihan Piutang Terbuka & Input Alokasi -->
        <div class="col-12">
            <x-metronic.card title="3. Alokasi Pembayaran Piutang (Multi-Nota/Invoice)">
                @if(!$selectedCustomerId)
                    <div class="py-10 text-center">
                        <x-metronic.empty-state 
                            title="Pelanggan Belum Dipilih" 
                            description="Silakan pilih pelanggan terlebih dahulu pada bagian atas untuk memuat rincian nota piutang yang masih memiliki saldo terbuka." 
                            icon="ki-outline ki-user" 
                        />
                    </div>
                @else
                    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                        <div>
                            <span class="fs-6 fw-bold text-gray-800">Daftar Tagihan Belum Lunas</span>
                            <span class="badge badge-light-primary ms-2">{{ $receivables->count() }} Tagihan</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <button type="button" id="btn-pay-all" class="btn btn-sm btn-light-success">
                                <i class="ki-outline ki-check-circle fs-6 me-1"></i>Alokasikan Lunas Semua
                            </button>
                            <button type="button" id="btn-reset-all" class="btn btn-sm btn-light-secondary">
                                <i class="ki-outline ki-arrows-circle fs-6 me-1"></i>Reset Alokasi
                            </button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle table-row-dashed fs-6 gy-4">
                            <thead>
                                <tr class="text-start text-muted fw-bold fs-7 text-uppercase gs-0">
                                    <th class="min-w-150px">No Piutang / Asal</th>
                                    <th class="min-w-100px">Tanggal Terbit</th>
                                    <th class="min-w-150px">Jatuh Tempo & Status</th>
                                    <th class="min-w-125px text-end">Pokok Tagihan</th>
                                    <th class="min-w-130px text-end">Sisa Tagihan</th>
                                    <th class="min-w-200px text-end">Alokasi Bayar</th>
                                </tr>
                            </thead>
                            <tbody class="text-gray-700 fw-semibold">
                                @php
                                    $totalOutstandingAll = '0.00';
                                @endphp
                                @forelse($receivables as $receivable)
                                    @php
                                        $totalOutstandingAll = Decimal::add($totalOutstandingAll, (string) $receivable->outstanding_amount, 2);
                                        $isOverdue = now()->startOfDay()->gt($receivable->due_date);
                                        $daysDiff = (int) now()->startOfDay()->diffInDays($receivable->due_date, false);
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-bolder text-gray-900 fs-6">{{ $receivable->number }}</div>
                                            <div class="text-muted fs-8">
                                                <i class="ki-outline ki-document text-gray-500 fs-8 me-1"></i>Sumber: {{ $receivable->source_no ?: '-' }}
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-gray-800">{{ $receivable->issue_date?->format('d/m/Y') ?? '-' }}</span>
                                        </td>
                                        <td>
                                            <div class="d-flex flex-column">
                                                <span class="text-gray-900 fw-bold">{{ $receivable->due_date?->format('d/m/Y') }}</span>
                                                @if($isOverdue)
                                                    <span class="badge badge-light-danger fs-9 w-fit mt-1">
                                                        <i class="ki-outline ki-cross-circle fs-9 text-danger me-1"></i>Terlambat {{ abs($daysDiff) }} hari
                                                    </span>
                                                @elseif($daysDiff <= 3)
                                                    <span class="badge badge-light-warning fs-9 w-fit mt-1">
                                                        <i class="ki-outline ki-time fs-9 text-warning me-1"></i>H-{{ $daysDiff }} Jatuh Tempo
                                                    </span>
                                                @else
                                                    <span class="badge badge-light-success fs-9 w-fit mt-1">
                                                        <i class="ki-outline ki-check fs-9 text-success me-1"></i>Lancar ({{ $daysDiff }} hari lagi)
                                                    </span>
                                                @endif
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <span class="text-muted">{{ CurrencyFormatter::rupiah($receivable->principal_amount) }}</span>
                                        </td>
                                        <td class="text-end">
                                            <span class="fw-bolder text-danger fs-6 outstanding-val" data-amount="{{ $receivable->outstanding_amount }}">
                                                {{ CurrencyFormatter::rupiah($receivable->outstanding_amount) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light border-gray-300">Rp</span>
                                                <input 
                                                    type="number" 
                                                    step="0.01" 
                                                    min="0" 
                                                    max="{{ $receivable->outstanding_amount }}" 
                                                    name="allocations[{{ $receivable->id }}]" 
                                                    value="{{ old('allocations.'.$receivable->id, 0) }}" 
                                                    class="form-control form-control-solid text-end fw-bold allocation-input"
                                                    data-max="{{ $receivable->outstanding_amount }}"
                                                    data-id="{{ $receivable->id }}"
                                                >
                                                <button type="button" class="btn btn-light-primary btn-pay-row" data-id="{{ $receivable->id }}" title="Alokasikan penuh sesuai sisa tagihan">
                                                    Lunas
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center py-10">
                                            <x-metronic.empty-state 
                                                title="Tidak Ada Tagihan Piutang Terbuka" 
                                                description="Pelanggan ini tidak memiliki saldo tagihan yang belum lunas. Semua tagihan sudah selesai." 
                                                icon="ki-outline ki-check-circle" 
                                            />
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($receivables->isNotEmpty())
                    <!-- Panel Kalkulasi Real-time Ringkasan Pembayaran -->
                    <div class="bg-light-primary rounded border border-primary border-opacity-30 p-5 mt-6">
                        <div class="row align-items-center g-4">
                            <div class="col-md-4">
                                <div class="text-muted fs-7 mb-1">Total Piutang Terbuka</div>
                                <div class="fs-4 fw-bold text-gray-800" id="summary-total-outstanding">
                                    {{ CurrencyFormatter::rupiah($totalOutstandingAll) }}
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted fs-7 mb-1">Total Pembayaran Dialokasikan</div>
                                <div class="fs-2hx fw-bolder text-success" id="summary-total-allocated">
                                    Rp 0
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted fs-7 mb-1">Perkiraan Sisa Piutang Setelah Bayar</div>
                                <div class="fs-4 fw-bold text-primary" id="summary-total-remaining">
                                    {{ CurrencyFormatter::rupiah($totalOutstandingAll) }}
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif
                @endif

                <div class="d-flex justify-content-end gap-3 mt-6">
                    <a href="{{ route('receivables.index') }}" class="btn btn-light">Kembali</a>
                    <button type="submit" id="btn-submit-payment" class="btn btn-primary" {{ $receivables->isEmpty() ? 'disabled' : '' }}>
                        <i class="ki-outline ki-wallet fs-4 me-1"></i>Simpan & Proses Pembayaran
                    </button>
                </div>
            </x-metronic.card>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        const picker = $('#customer-picker');

        // Otomatis reload halaman saat pelanggan dipilih
        picker.on('change', function() {
            const customerId = $(this).val();
            if (customerId) {
                const url = new URL('{{ route('receivables.payments.create') }}', window.location.origin);
                url.searchParams.set('customer_id', customerId);
                window.location.href = url.toString();
            }
        });

        // Format angka ke format Rupiah
        function formatRupiah(amount) {
            const num = parseFloat(amount) || 0;
            return 'Rp' + Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Kalkulasi real-time seluruh input alokasi
        function recalculateAllocations() {
            let totalAllocated = 0;
            let totalOutstanding = 0;

            $('.allocation-input').each(function() {
                const max = parseFloat($(this).data('max')) || 0;
                let val = parseFloat($(this).val()) || 0;

                // Batasi agar alokasi tidak melebihi sisa tagihan per baris
                if (val > max) {
                    val = max;
                    $(this).val(max);
                } else if (val < 0) {
                    val = 0;
                    $(this).val(0);
                }

                totalOutstanding += max;
                totalAllocated += val;
            });

            const remaining = Math.max(0, totalOutstanding - totalAllocated);

            $('#summary-total-allocated').text(formatRupiah(totalAllocated));
            $('#summary-total-remaining').text(formatRupiah(remaining));

            // Matikan tombol submit jika total alokasi masih 0
            if (totalAllocated > 0) {
                $('#btn-submit-payment').prop('disabled', false);
            } else {
                $('#btn-submit-payment').prop('disabled', true);
            }
        }

        // Event listener saat user mengetik alokasi
        $(document).on('input change', '.allocation-input', function() {
            recalculateAllocations();
        });

        // Tombol "Lunas" per baris
        $(document).on('click', '.btn-pay-row', function() {
            const row = $(this).closest('tr');
            const input = row.find('.allocation-input');
            const maxVal = parseFloat(input.data('max')) || 0;
            input.val(maxVal);
            recalculateAllocations();
        });

        // Tombol "Alokasikan Lunas Semua"
        $('#btn-pay-all').on('click', function() {
            $('.allocation-input').each(function() {
                const max = parseFloat($(this).data('max')) || 0;
                $(this).val(max);
            });
            recalculateAllocations();
        });

        // Tombol "Reset Alokasi"
        $('#btn-reset-all').on('click', function() {
            $('.allocation-input').val(0);
            recalculateAllocations();
        });

        // Validasi saat form di-submit
        $('#payment-form').on('submit', function(e) {
            let totalAllocated = 0;
            $('.allocation-input').each(function() {
                totalAllocated += (parseFloat($(this).val()) || 0);
            });

            if (totalAllocated <= 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Alokasi Belum Diisi',
                    text: 'Silakan isi nominal pembayaran pada minimal satu baris tagihan piutang.'
                });
                return false;
            }
        });

        // Inisialisasi perhitungan awal
        recalculateAllocations();
    });
</script>
@endpush
