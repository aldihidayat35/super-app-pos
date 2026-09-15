@extends('layouts.metronic.app')

@section('title', 'Keuangan & Pajak Tahunan')

@section('content')
<x-metronic.page-title title="Keuangan & Pajak Tahunan" subtitle="Pembukuan sederhana non-PKP untuk laporan tahunan dan dokumen kerja Coretax." />

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if($errors->any()) <div class="alert alert-danger"><strong>Data belum dapat diproses.</strong><ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

<div class="d-flex flex-wrap gap-3 align-items-end mb-5">
    <form method="get" class="d-flex gap-2 align-items-end">
        <div><label class="form-label">Tahun laporan</label><select name="year" class="form-select" onchange="this.form.submit()">@forelse($years as $item)<option value="{{ $item->year }}" @selected($year?->id === $item->id)>{{ $item->year }} · {{ $item->status->label() }}</option>@empty<option>Belum ada data</option>@endforelse</select></div>
    </form>
    @can('finance_annual.manage')
    <form method="post" action="{{ route('tax.years.store') }}" class="d-flex flex-wrap gap-2 align-items-end">@csrf
        <div><label class="form-label">Buat tahun</label><input type="number" name="year" class="form-control" min="2000" max="2100" value="{{ now('Asia/Jakarta')->year }}" required></div>
        <div><label class="form-label">Nama badan</label><input name="legal_name" class="form-control" value="{{ config('app.name') }}" required></div>
        <label class="form-check mb-3"><input type="checkbox" name="is_historical" value="1" class="form-check-input"><span class="form-check-label">Data historis</span></label>
        <button class="btn btn-primary">Buat 12 Bulan</button>
    </form>
    @endcan
</div>

@if($year && $report)
<div class="row g-4 mb-5">
    @foreach([['Penjualan Bersih',$report['sales'],'primary'],['Laba Kotor',$report['gross_profit'],'info'],['Laba Setelah Pajak',$report['netProfit'],'success'],['Selisih Neraca',$report['balance_difference'],$report['balance_difference']==0?'success':'danger']] as [$label,$value,$color])
    <div class="col-md-3"><div class="card h-100"><div class="card-body"><span class="text-muted">{{ $label }}</span><div class="fs-3 fw-bold text-{{ $color }}">{{ App\Support\CurrencyFormatter::rupiah($value) }}</div></div></div></div>
    @endforeach
</div>

<div class="card">
 <div class="card-header border-0"><ul class="nav nav-tabs nav-line-tabs card-header-tabs">
    @foreach(['summary'=>'Ringkasan Tahun','monthly'=>'Rekap Bulanan','profit'=>'Laba Rugi','balance'=>'Neraca','fiscal'=>'Rekonsiliasi Fiskal & PPh','history'=>'Riwayat dan Dokumen'] as $key=>$label)
    <li class="nav-item"><a class="nav-link @if($tab===$key) active @endif" href="{{ route('tax.index',['year'=>$year->year,'tab'=>$key]) }}">{{ $label }}</a></li>
    @endforeach
 </ul></div>
 <div class="card-body">
 @if($tab==='summary')
    <div class="alert alert-info">Angka otomatis berasal dari transaksi yang disimpan saat snapshot. Input manual selalu dipisahkan dan wajib memiliki alasan.</div>
    <div class="row g-4"><div class="col-lg-7"><h3>Status kesiapan</h3><ul class="list-group">
       <li class="list-group-item d-flex justify-content-between">12 bulan dikunci <strong>{{ $year->months->where('status', App\Enums\FinancialMonthStatus::LOCKED)->count() }}/12</strong></li>
       <li class="list-group-item d-flex justify-content-between">HPP belum lengkap <strong>{{ $year->months->sum('missing_hpp_count') }}</strong></li>
       <li class="list-group-item d-flex justify-content-between">Skema pajak dikonfirmasi <strong>{{ $year->tax_scheme_confirmed ? 'Ya' : 'Belum' }}</strong></li>
       <li class="list-group-item d-flex justify-content-between">Neraca seimbang <strong>{{ bccomp((string)$report['balance_difference'],'0',2)===0 ? 'Ya' : 'Belum' }}</strong></li>
    </ul></div><div class="col-lg-5"><h3>Alur kerja</h3><p>Staf mengambil snapshot dan mengisi koreksi → Kepala Keuangan mengunci tiap bulan → laporan diperiksa, disetujui, lalu dikunci.</p><span class="badge badge-light-primary fs-6">{{ $year->status->label() }}</span></div></div>
 @elseif($tab==='monthly')
    <div class="table-responsive"><table class="table table-row-bordered align-middle"><thead><tr><th>Bulan</th><th>Status</th><th>Penjualan</th><th>HPP</th><th>Beban</th><th>Laba/Rugi</th><th>Aksi</th></tr></thead><tbody>
    @foreach($report['months'] as $row)<tr><td>{{ DateTime::createFromFormat('!m',$row['month'])->format('F') }}</td><td><span class="badge badge-light-primary">{{ $row['model']->status->label() }}</span></td><td>{{ App\Support\CurrencyFormatter::rupiah($row['net_sales']) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($row['cogs']) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($row['expenses']) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($row['profit']) }}</td><td class="d-flex gap-1 flex-wrap">
      @can('finance_annual.manage')<form method="post" action="{{ route('tax.months.snapshot',$row['model']) }}">@csrf<button class="btn btn-sm btn-light-primary">Ambil Snapshot</button></form><button class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#entry-{{ $row['month'] }}">Input Manual</button>@endcan
      @if($row['model']->status===App\Enums\FinancialMonthStatus::DRAFT) @can('finance_annual.manage')<form method="post" action="{{ route('tax.months.transition',$row['model']) }}">@csrf<input type="hidden" name="action" value="submit"><button class="btn btn-sm btn-light-success">Ajukan</button></form>@endcan @else @can('finance_annual.approve')<form method="post" action="{{ route('tax.months.transition',$row['model']) }}">@csrf<input type="hidden" name="action" value="{{ $row['model']->status===App\Enums\FinancialMonthStatus::SUBMITTED?'lock':'reopen' }}">@if($row['model']->status===App\Enums\FinancialMonthStatus::LOCKED)<input type="hidden" name="reason" value="Dibuka kembali untuk koreksi Kepala Keuangan">@endif<button class="btn btn-sm btn-light-success">{{ $row['model']->status===App\Enums\FinancialMonthStatus::SUBMITTED?'Kunci':'Buka Kembali' }}</button></form>@endcan @endif
    </td></tr>
    <tr class="collapse" id="entry-{{ $row['month'] }}"><td colspan="7"><form method="post" enctype="multipart/form-data" action="{{ route('tax.months.entries.store',$row['model']) }}" class="row g-2">@csrf<div class="col-md-3"><select name="account_code" class="form-select">@foreach($accounts as $code=>$label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div><div class="col-md-2"><input type="number" step="0.01" name="amount" class="form-control" placeholder="Nominal" required></div><div class="col-md-4"><input name="reason" class="form-control" placeholder="Alasan atau sumber angka" required></div><div class="col-md-2"><input type="file" name="proof" class="form-control"></div><div class="col-md-1"><button class="btn btn-primary">Simpan</button></div></form>
    @if($row['model']->entries->isNotEmpty())<div class="small mt-3">@foreach($row['model']->entries as $e)<span class="badge badge-light-dark me-2">{{ $e->label }}: {{ App\Support\CurrencyFormatter::rupiah($e->amount) }} · {{ $e->reason }}</span>@endforeach</div>@endif</td></tr>@endforeach
    </tbody></table></div>
 @elseif($tab==='profit')
    @php($profitRows=[['Penjualan bersih',$report['sales']],['HPP','-'.$report['cogs']],['Laba kotor',$report['gross_profit']],['Beban','-'.$report['expenses']],['Laba sebelum pajak',$report['profit']],['PPh Badan','-'.$report['tax']],['Laba tahun berjalan',$report['netProfit']]])
    <table class="table table-row-bordered mw-650px">@foreach($profitRows as [$label,$amount])<tr @class(['fw-bold'=>str_contains($label,'Laba')])><td>{{ $label }}</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah($amount) }}</td></tr>@endforeach</table>
 @elseif($tab==='balance')
    <div class="row"><div class="col-md-6"><h3>Aset</h3><table class="table"><tr><td>Kas dan bank</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah($report['accounts']['cash_bank']) }}</td></tr><tr><td>Piutang usaha</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah(App\Support\Decimal::add($year->months->last()->auto_receivable_balance, $report['accounts']['receivable_adjustment'], 2)) }}</td></tr><tr><td>Persediaan</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah(App\Support\Decimal::add($year->months->last()->auto_inventory_balance, $report['accounts']['inventory_adjustment'], 2)) }}</td></tr><tr class="fw-bold"><td>Total aset</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah($report['assets']) }}</td></tr></table></div><div class="col-md-6"><h3>Kewajiban dan Ekuitas</h3><table class="table"><tr><td>Total kewajiban</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah($report['liabilities']) }}</td></tr><tr><td>Modal dan laba</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah($report['equity']) }}</td></tr><tr class="fw-bold"><td>Selisih</td><td class="text-end">{{ App\Support\CurrencyFormatter::rupiah($report['balance_difference']) }}</td></tr></table></div></div>
 @elseif($tab==='fiscal')
    @can('finance_annual.manage')<form method="post" action="{{ route('tax.years.update',$year) }}" class="row g-3">@csrf @method('PUT')
      @foreach(['legal_name'=>'Nama badan','tax_number'=>'NPWP','report_city'=>'Kota laporan','commissioner_name'=>'Komisaris','director_name'=>'Direktur'] as $field=>$label)<div class="col-md-4"><label class="form-label">{{ $label }}</label><input name="{{ $field }}" class="form-control" value="{{ $year->$field }}" @if($field!=='tax_number') required @endif></div>@endforeach
      <div class="col-12"><label class="form-label">Alamat perusahaan</label><textarea name="company_address" class="form-control">{{ $year->company_address }}</textarea></div>
      <div class="col-md-4"><label class="form-label">Skema PPh</label><select name="tax_scheme" class="form-select">@foreach($schemes as $scheme)<option value="{{ $scheme->value }}" @selected($year->tax_scheme===$scheme)>{{ $scheme->label() }}</option>@endforeach</select></div>
      @foreach(['fiscal_positive_adjustment'=>'Koreksi fiskal positif','fiscal_negative_adjustment'=>'Koreksi fiskal negatif','tax_credit_amount'=>'Kredit pajak','installment_amount'=>'Angsuran','prior_payment_amount'=>'Pembayaran sebelumnya','manual_tax_amount'=>'Nominal manual'] as $field=>$label)<div class="col-md-4"><label class="form-label">{{ $label }}</label><input type="number" step="0.01" min="0" name="{{ $field }}" class="form-control" value="{{ $year->$field ?? 0 }}" @if($field!=='manual_tax_amount') required @endif></div>@endforeach
      @can('finance_annual.approve')<div class="col-12"><label class="form-check"><input type="checkbox" name="tax_scheme_confirmed" value="1" class="form-check-input" @checked($year->tax_scheme_confirmed)><span class="form-check-label">Saya telah memeriksa dan mengonfirmasi skema pajak tahun ini</span></label></div>@endcan
      <div class="col-12"><label class="form-label">Catatan</label><textarea name="notes" class="form-control">{{ $year->notes }}</textarea></div><div class="col-12"><button class="btn btn-primary">Simpan & Hitung Ulang</button></div>
    </form>@endcan
    <hr><div class="row g-3"><div class="col-md-3"><strong>PKP Fiskal</strong><div>{{ App\Support\CurrencyFormatter::rupiah($report['taxable']) }}</div></div><div class="col-md-3"><strong>PPh Terutang</strong><div>{{ App\Support\CurrencyFormatter::rupiah($report['tax']) }}</div></div><div class="col-md-3"><strong>Kurang/Lebih Bayar</strong><div>{{ App\Support\CurrencyFormatter::rupiah($report['taxPayable']) }}</div></div></div>
 @else
    <div class="d-flex flex-wrap gap-2 mb-4">@can('finance_annual.export')<a class="btn btn-light-danger" href="{{ route('tax.years.pdf',$year) }}">Unduh PDF</a><a class="btn btn-light-success" href="{{ route('tax.years.excel',$year) }}">Unduh Excel</a>@endcan
    @can('finance_annual.approve')@foreach(['review'=>'Periksa','approve'=>'Setujui','lock'=>'Kunci'] as $action=>$label)<form method="post" action="{{ route('tax.years.transition',$year) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="btn btn-primary">{{ $label }}</button></form>@endforeach<form method="post" action="{{ route('tax.years.transition',$year) }}" class="d-flex gap-2">@csrf<input type="hidden" name="action" value="reopen"><input name="reason" class="form-control" placeholder="Alasan buka kembali"><button class="btn btn-warning">Buka Kembali</button></form>@endcan</div>
    <div class="alert alert-secondary">Register PPN/PPh bulanan lama tetap tersimpan sebagai histori read-only di database. Data tersebut tidak dicampurkan otomatis ke perhitungan PPh tahunan.</div>
 @endif
 </div>
</div>
@else
<x-metronic.empty-state title="Belum ada tahun laporan" description="Staf Keuangan dapat membuat tahun berjalan atau tahun historis tanpa membuat transaksi POS palsu." />
@endif
@endsection
