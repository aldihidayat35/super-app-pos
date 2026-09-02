@extends('layouts.metronic.app')

@section('title', 'Pajak & Kepatuhan')

@section('page_title', 'Pajak & Kepatuhan')
@section('page_description', 'Register PPN/PPh, rekonsiliasi, dan tutup masa pajak.')

@section('page_guide')
    <x-metronic.page-guide id="tax-compliance" title="Panduan Pajak & Kepatuhan">
        <x-slot:function>Menyatukan pajak keluaran, pajak masukan, retur, dan bukti potong dalam satu register yang dapat ditelusuri.</x-slot:function>
        <x-slot:workflow>Sinkronkan transaksi → lengkapi dokumen masukan/PPh → rekonsiliasi → review → approve → laporkan → bayar → kunci.</x-slot:workflow>
        <x-slot:parts>Ringkasan masa, indikator masalah, register dokumen, input manual, ekspor staging, dan workflow masa pajak.</x-slot:parts>
        <x-slot:impacts>Periode terkunci tidak dapat diubah sebelum dibuka kembali dengan alasan dan jejak audit.</x-slot:impacts>
        <x-slot:warnings>Ekspor CSV adalah staging rekonsiliasi. Validasi kembali dengan ketentuan serta format Coretax yang berlaku sebelum pelaporan.</x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-6">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-end">
            <div><label class="form-label">Bulan</label><input type="number" name="month" min="1" max="12" value="{{ $month }}" class="form-control w-100px"></div>
            <div><label class="form-label">Tahun</label><input type="number" name="year" min="2020" max="2100" value="{{ $year }}" class="form-control w-125px"></div>
            <button class="btn btn-light-primary"><i class="ki-outline ki-filter fs-5"></i> Tampilkan</button>
        </form>
        <div class="d-flex gap-2">
            <a href="{{ route('tax.settings') }}" class="btn btn-light"><i class="ki-outline ki-setting-2 fs-5"></i> Pengaturan</a>
            @if($period)
                <a href="{{ route('tax.periods.export', $period) }}" class="btn btn-light-success"><i class="ki-outline ki-file-down fs-5"></i> Export CSV</a>
            @endif
            <form method="POST" action="{{ route('tax.sync', ['month' => $month, 'year' => $year]) }}">@csrf
                <button class="btn btn-primary"><i class="ki-outline ki-arrows-circle fs-5"></i> Sinkronkan Transaksi</button>
            </form>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><strong>Data belum dapat diproses.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @if(!$period)
        <x-metronic.card>
            <x-metronic.empty-state title="Masa pajak belum dibuat" description="Klik Sinkronkan Transaksi untuk membuat register masa {{ sprintf('%02d/%04d', $month, $year) }}, atau tambahkan dokumen pajak manual." />
        </x-metronic.card>
    @else
        <div class="row g-5 mb-6">
            <div class="col-md-3"><x-metronic.card><div class="text-muted">PPN Keluaran</div><div class="fs-2 fw-bold">{{ App\Support\CurrencyFormatter::rupiah($period->output_tax_amount) }}</div><div class="text-muted fs-8">DPP {{ App\Support\CurrencyFormatter::rupiah($period->output_dpp_amount) }}</div></x-metronic.card></div>
            <div class="col-md-3"><x-metronic.card><div class="text-muted">PPN Masukan Dikreditkan</div><div class="fs-2 fw-bold text-success">{{ App\Support\CurrencyFormatter::rupiah($period->creditable_input_tax_amount) }}</div><div class="text-muted fs-8">Tidak dikreditkan {{ App\Support\CurrencyFormatter::rupiah($period->non_creditable_input_tax_amount) }}</div></x-metronic.card></div>
            <div class="col-md-3"><x-metronic.card><div class="text-muted">Kurang / (Lebih) Bayar</div><div class="fs-2 fw-bold {{ (float) $period->payable_amount > 0 ? 'text-danger' : 'text-success' }}">{{ App\Support\CurrencyFormatter::rupiah($period->payable_amount) }}</div><div class="text-muted fs-8">Kompensasi {{ App\Support\CurrencyFormatter::rupiah($period->compensation_amount) }}</div></x-metronic.card></div>
            <div class="col-md-3"><x-metronic.card><div class="text-muted">Status Masa</div><div class="mt-2"><x-metronic.status-badge :status="$period->status" /></div><div class="text-muted fs-8 mt-2">PPh dipotong {{ App\Support\CurrencyFormatter::rupiah($period->withholding_tax_amount) }}</div></x-metronic.card></div>
        </div>

        <div class="row g-5 mb-6">
            <div class="col-lg-5">
                <x-metronic.card title="Kontrol Masa Pajak">
                    <form method="POST" action="{{ route('tax.periods.update', $period) }}" class="row g-3 mb-5">@csrf @method('PUT')
                        <div class="col-md-6"><label class="form-label">Kompensasi Masa Sebelumnya</label><input type="number" step="0.01" min="0" name="compensation_amount" value="{{ $period->compensation_amount }}" class="form-control" @disabled($period->status->isFinal())></div>
                        <div class="col-md-6"><label class="form-label">Catatan</label><input name="notes" value="{{ $period->notes }}" class="form-control" @disabled($period->status->isFinal())></div>
                        <div class="col-12"><button class="btn btn-sm btn-light-primary" @disabled($period->status->isFinal())>Simpan Rekap</button></div>
                    </form>
                    <div class="d-flex flex-wrap gap-2">
                        @if($period->status === App\Enums\TaxPeriodStatus::OPEN)
                            <form method="POST" action="{{ route('tax.periods.transition', $period) }}">@csrf<input type="hidden" name="action" value="review"><button class="btn btn-warning">Review Masa</button></form>
                        @elseif($period->status === App\Enums\TaxPeriodStatus::REVIEWED)
                            <form method="POST" action="{{ route('tax.periods.transition', $period) }}">@csrf<input type="hidden" name="action" value="approve"><button class="btn btn-success">Approve Masa</button></form>
                        @elseif($period->status === App\Enums\TaxPeriodStatus::APPROVED)
                            <form method="POST" action="{{ route('tax.periods.transition', $period) }}" class="d-flex gap-2"><input type="hidden" name="action" value="report">@csrf<input name="filing_reference" required placeholder="Referensi pelaporan" class="form-control"><button class="btn btn-primary">Tandai Dilaporkan</button></form>
                        @elseif($period->status === App\Enums\TaxPeriodStatus::REPORTED)
                            <form method="POST" action="{{ route('tax.periods.transition', $period) }}" class="d-flex gap-2"><input type="hidden" name="action" value="pay">@csrf<input name="payment_reference" required placeholder="NTPN/referensi bayar" class="form-control"><button class="btn btn-success">Tandai Dibayar</button></form>
                            <form method="POST" action="{{ route('tax.periods.transition', $period) }}">@csrf<input type="hidden" name="action" value="lock"><button class="btn btn-dark">Kunci Tanpa Bayar</button></form>
                        @elseif($period->status === App\Enums\TaxPeriodStatus::PAID)
                            <form method="POST" action="{{ route('tax.periods.transition', $period) }}">@csrf<input type="hidden" name="action" value="lock"><button class="btn btn-dark">Kunci Masa</button></form>
                        @elseif($period->status === App\Enums\TaxPeriodStatus::LOCKED)
                            <form method="POST" action="{{ route('tax.periods.transition', $period) }}" class="d-flex gap-2">@csrf<input type="hidden" name="action" value="reopen"><input name="notes" required placeholder="Alasan pembukaan kembali" class="form-control"><button class="btn btn-danger">Buka Kembali</button></form>
                        @endif
                    </div>
                </x-metronic.card>
            </div>
            <div class="col-lg-7">
                <x-metronic.card title="Pemeriksaan Sebelum Lapor">
                    <div class="row g-4">
                        <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Belum direkonsiliasi</div><div class="fs-2 fw-bold {{ $issueCounts['unmatched'] ? 'text-warning' : 'text-success' }}">{{ $issueCounts['unmatched'] }}</div></div></div>
                        <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Identitas pajak kosong</div><div class="fs-2 fw-bold {{ $issueCounts['missing_tax_number'] ? 'text-danger' : 'text-success' }}">{{ $issueCounts['missing_tax_number'] }}</div></div></div>
                        <div class="col-md-4"><div class="border rounded p-4"><div class="text-muted">Dokumen draft</div><div class="fs-2 fw-bold {{ $issueCounts['draft'] ? 'text-warning' : 'text-success' }}">{{ $issueCounts['draft'] }}</div></div></div>
                    </div>
                    <div class="alert alert-light-warning mt-4 mb-0">Approval internal tidak mengirim SPT ke Coretax. Masukkan referensi setelah pelaporan resmi selesai.</div>
                </x-metronic.card>
            </div>
        </div>
    @endif

    <x-metronic.card title="Tambah Dokumen Pajak Manual" class="mb-6">
        <form method="POST" action="{{ route('tax.documents.store') }}" class="row g-3">@csrf
            <div class="col-md-2"><label class="form-label">Arah</label><select name="direction" class="form-select" required>@foreach($directions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Jenis Pajak</label><select name="tax_type" class="form-select" required>@foreach($taxTypes as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Jenis Dokumen</label><select name="document_type" class="form-select"><option value="supplier_invoice">Faktur Supplier</option><option value="tax_invoice">Faktur Pajak</option><option value="withholding_receipt">Bukti Potong</option><option value="credit_note">Nota Kredit</option><option value="return_note">Nota Retur</option><option value="other">Lainnya</option></select></div>
            <div class="col-md-3"><label class="form-label">Nomor Dokumen</label><input name="document_number" class="form-control" required></div>
            <div class="col-md-3"><label class="form-label">Lawan Transaksi</label><input name="counterparty_name" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">NPWP/NIK</label><input name="counterparty_tax_number" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Tanggal Dokumen</label><input type="date" name="issue_date" value="{{ sprintf('%04d-%02d-01', $year, $month) }}" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Tanggal Pajak</label><input type="date" name="tax_date" value="{{ sprintf('%04d-%02d-01', $year, $month) }}" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Aturan</label><select name="tax_rule_id" class="form-select"><option value="">Hitung manual</option>@foreach($rules as $rule)<option value="{{ $rule->id }}">{{ $rule->code }} — {{ $rule->name }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">DPP</label><input type="number" step="0.01" name="dpp_amount" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Tarif %</label><input type="number" step="0.0001" min="0" name="tax_rate" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Nilai Pajak</label><input type="number" step="0.01" name="tax_amount" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">PPh Dipotong</label><input type="number" step="0.01" name="withholding_tax_amount" value="0" class="form-control"></div>
            <div class="col-md-2"><label class="form-label">Referensi Coretax</label><input name="coretax_reference" class="form-control"></div>
            <div class="col-md-2 d-flex align-items-end"><label class="form-check form-check-custom mb-3"><input type="hidden" name="is_creditable" value="0"><input type="checkbox" name="is_creditable" value="1" class="form-check-input" checked><span class="form-check-label">Dapat dikreditkan</span></label></div>
            <div class="col-md-8"><label class="form-label">Catatan</label><input name="notes" class="form-control"></div>
            <div class="col-md-4 d-flex align-items-end"><button class="btn btn-primary w-100">Posting Dokumen Pajak</button></div>
        </form>
    </x-metronic.card>

    <x-metronic.card title="Register Dokumen Pajak">
        <form method="GET" class="row g-3 mb-5"><input type="hidden" name="month" value="{{ $month }}"><input type="hidden" name="year" value="{{ $year }}">
            <div class="col-md-3"><select name="direction" class="form-select"><option value="">Semua arah</option>@foreach($directions as $value => $label)<option value="{{ $value }}" @selected(($filters['direction'] ?? '') === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-3"><select name="reconciliation_status" class="form-select"><option value="">Semua rekonsiliasi</option><option value="unmatched" @selected(($filters['reconciliation_status'] ?? '') === 'unmatched')>Belum cocok</option><option value="matched" @selected(($filters['reconciliation_status'] ?? '') === 'matched')>Cocok</option><option value="reversed" @selected(($filters['reconciliation_status'] ?? '') === 'reversed')>Reversal</option></select></div>
            <div class="col-md-2"><button class="btn btn-light-primary w-100">Filter</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Tanggal/Dokumen</th><th>Arah</th><th>Lawan Transaksi</th><th>DPP</th><th>Pajak</th><th>Rekonsiliasi</th><th class="text-end">Aksi</th></tr></thead>
                <tbody>
                @forelse($documents as $document)
                    <tr>
                        <td>{{ $document->tax_date?->format('d/m/Y') }}<div class="fw-semibold">{{ $document->document_number }}</div><div class="text-muted fs-8">{{ str_replace('_', ' ', $document->document_type) }}</div></td>
                        <td>{{ $document->direction->label() }}<div class="text-muted">{{ $document->tax_type->label() }}</div></td>
                        <td>{{ $document->counterparty_name }}<div class="text-muted">{{ $document->counterparty_tax_number ?: 'Identitas pajak belum diisi' }}</div></td>
                        <td>{{ App\Support\CurrencyFormatter::rupiah($document->dpp_amount) }}</td>
                        <td>{{ App\Support\CurrencyFormatter::rupiah($document->tax_amount) }}<div class="text-muted">{{ $document->tax_rate }}%</div></td>
                        <td><x-metronic.status-badge :status="$document->status" /><div class="text-muted fs-8 mt-1">{{ $document->reconciliation_status }} {{ $document->coretax_reference ? '· '.$document->coretax_reference : '' }}</div></td>
                        <td class="text-end">
                            @if(!$period?->status->isFinal() && $document->status !== App\Enums\TaxDocumentStatus::REVERSED)
                                <form method="POST" action="{{ route('tax.documents.reconcile', $document) }}" class="d-inline">@csrf<input type="hidden" name="coretax_reference" value="{{ $document->coretax_reference }}"><button class="btn btn-sm btn-light-success">Cocok</button></form>
                                <form method="POST" action="{{ route('tax.documents.reverse', $document) }}" class="d-inline" id="reverse-tax-{{ $document->id }}">@csrf<input type="hidden" name="reason" value="Dibatalkan melalui register pajak."><button type="submit" class="btn btn-sm btn-light-danger" data-confirm data-confirm-form="reverse-tax-{{ $document->id }}" data-confirm-title="Reverse dokumen pajak?" data-confirm-text="Dokumen tidak dihapus dan tetap tersedia untuk audit." data-confirm-button="Ya, reverse">Reverse</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Belum ada dokumen" description="Sinkronkan transaksi atau tambahkan dokumen pajak manual." /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $documents->links() }}
    </x-metronic.card>
@endsection
