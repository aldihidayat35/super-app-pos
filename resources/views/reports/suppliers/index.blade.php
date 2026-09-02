@extends('layouts.metronic.app')

@section('title', 'Laporan Supplier - '.config('app.name'))
@section('page_title', 'Laporan Supplier')

@section('page_guide')
    <x-metronic.page-guide id="report-suppliers" title="Panduan Laporan Supplier">
        <x-slot:function><p>Menilai konsistensi supplier berdasarkan kualitas barang, ketepatan pengiriman, harga, dan tingkat penerimaan barang.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih periode, supplier, atau produk.</li><li>Bandingkan skor rata-rata dan acceptance rate.</li><li>Periksa tren agar penurunan kualitas tidak tertutup oleh nilai historis.</li><li>Tinjau receipt supplier yang berada di bawah standar.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>KPI:</strong> cakupan evaluasi dan hasil rata-rata.</li><li><strong>Tren:</strong> perubahan kualitas per tanggal penerimaan.</li><li><strong>Ranking:</strong> perbandingan supplier dalam scope filter.</li><li><strong>Detail:</strong> bukti evaluasi per goods receipt.</li></ul></x-slot:parts>
        <x-slot:warnings><div class="alert alert-warning mb-0">Skor adalah indikator evaluasi. Keputusan pembelian tetap perlu mempertimbangkan harga, lead time, kapasitas, dan kebutuhan produk.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Laporan Supplier" description="Performa Supplier berdasarkan penerimaan barang, kualitas, pengiriman, dan harga.">
        <x-slot:actions>@can('reports.export')<a href="{{ route('reports.exports.index', ['report_type' => 'suppliers', 'start_date' => $filters['date_from'], 'end_date' => $filters['date_to']]) }}" class="btn btn-light-primary"><i class="ki-outline ki-exit-down fs-5"></i> Export</a>@endcan</x-slot:actions>
    </x-metronic.page-title>

    <x-metronic.card class="mb-5">
        <form method="GET" class="row g-4 align-items-end">
            <div class="col-xl-3 col-md-6"><label class="form-label fw-semibold">Supplier</label><select name="supplier_id" class="form-select form-select-solid" data-control="select2"><option value="">Semua supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(($filters['supplier_id'] ?? '') == $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach</select></div>
            <div class="col-xl-3 col-md-6"><label class="form-label fw-semibold">Produk</label><select name="product_id" class="form-select form-select-solid" data-control="select2"><option value="">Semua produk</option>@foreach($products as $product)<option value="{{ $product->id }}" @selected(($filters['product_id'] ?? '') == $product->id)>{{ $product->sku }} — {{ $product->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Mulai</label><input type="date" name="date_from" value="{{ $filters['date_from'] }}" class="form-control form-control-solid"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Selesai</label><input type="date" name="date_to" value="{{ $filters['date_to'] }}" class="form-control form-control-solid"></div>
            <div class="col-xl-2 col-md-4"><button class="btn btn-primary w-100"><i class="ki-outline ki-filter fs-5"></i> Terapkan</button></div>
        </form>
    </x-metronic.card>

    @php($supplierKpis = [
        ['Receipt Dinilai', $summary['receipts_evaluated'], '', 'ki-document', 'primary', 'Jumlah penerimaan dalam filter'],
        ['Skor Keseluruhan', number_format($summary['average_score'], 1, ',', '.'), '/100', 'ki-award', 'success', 'Gabungan kualitas, kirim, dan harga'],
        ['Skor Kualitas', number_format($summary['average_quality'], 1, ',', '.'), '/100', 'ki-shield-tick', 'info', 'Konsistensi barang diterima'],
        ['Acceptance Rate', number_format((float) $summary['acceptance_rate'], 1, ',', '.'), '%', 'ki-check-circle', 'primary', 'Qty diterima dibanding qty datang'],
        ['Perlu Review', $summary['suppliers_need_review'], ' supplier', 'ki-information-5', 'danger', 'Memiliki skor di bawah 80'],
    ])
    <div class="row g-5 mb-5">@foreach($supplierKpis as [$label, $value, $suffix, $icon, $tone, $help])<div class="col-xl col-md-6"><div class="card supplier-kpi-card h-100"><div class="card-body p-5"><div class="d-flex align-items-center justify-content-between mb-4"><span class="report-kpi-icon bg-light-{{ $tone }} text-{{ $tone }}"><i class="ki-outline {{ $icon }} fs-2"></i></span><span class="text-muted text-uppercase fs-8 fw-bold">{{ $label }}</span></div><div class="fs-3 fw-bolder text-gray-900">{{ $value }}<span class="fs-7 text-muted fw-normal">{{ $suffix }}</span></div><div class="text-muted fs-8 mt-2">{{ $help }}</div></div></div></div>@endforeach</div>

    <div class="row g-5 mb-5">
        <div class="col-xl-8"><x-metronic.card title="Tren Skor Penerimaan" class="h-100"><div class="text-muted fs-7 mb-3">Perubahan skor keseluruhan, kualitas, dan pengiriman</div><div id="supplier-score-trend" class="supplier-chart"></div></x-metronic.card></div>
        <div class="col-xl-4"><x-metronic.card title="Ranking Supplier" class="h-100"><div class="text-muted fs-7 mb-3">Rata-rata skor dalam periode terpilih</div><div id="supplier-ranking-chart" class="supplier-chart"></div></x-metronic.card></div>
    </div>

    @if($summary['suppliers_need_review'] > 0)
        <div class="alert alert-light-danger d-flex align-items-center mb-5"><i class="ki-outline ki-information-5 fs-2x text-danger me-4"></i><div><div class="fw-bold text-gray-900">{{ $summary['suppliers_need_review'] }} supplier perlu ditinjau</div><div class="text-muted">Prioritaskan receipt dengan skor total atau kualitas terendah sebelum membuat PO berikutnya.</div></div></div>
    @endif

    <x-metronic.card title="Evaluasi per Penerimaan">
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted fw-bold text-uppercase fs-8"><th>Supplier</th><th>Receipt</th><th>Penerimaan Qty</th><th>Kualitas</th><th>Pengiriman</th><th>Harga</th><th>Skor Total</th><th>Tanggal</th></tr></thead><tbody>
            @forelse($scores as $score)
                <tr><td><div class="fw-semibold text-gray-900">{{ $score->supplier?->name }}</div><div class="text-muted fs-8">{{ $score->supplier?->code }}</div></td><td>@if($score->goodsReceipt)<a href="{{ route('warehouse.goods-receipts.show', $score->goodsReceipt) }}" class="fw-semibold">{{ $score->goodsReceipt->number }}</a>@else<span class="text-muted">—</span>@endif</td><td><div class="fw-semibold">{{ qty($score->quantity_accepted) }} / {{ qty($score->quantity_received) }}</div><div class="text-muted fs-8">{{ qty($score->quantity_rejected) }} ditolak · {{ qty($score->quantity_damaged) }} rusak</div></td><td><span @class(['badge', 'badge-light-success' => (float) $score->quality_score >= 80, 'badge-light-danger' => (float) $score->quality_score < 80])>{{ number_format((float) $score->quality_score, 1, ',', '.') }}</span></td><td>{{ number_format((float) $score->delivery_score, 1, ',', '.') }}</td><td>{{ number_format((float) $score->price_score, 1, ',', '.') }}</td><td><span @class(['badge', 'badge-light-success' => (float) $score->total_score >= 80, 'badge-light-warning' => (float) $score->total_score >= 65 && (float) $score->total_score < 80, 'badge-light-danger' => (float) $score->total_score < 65])>{{ number_format((float) $score->total_score, 1, ',', '.') }}/100</span></td><td>{{ $score->received_at?->format('d/m/Y') }}</td></tr>
            @empty<tr><td colspan="8"><x-metronic.empty-state title="Belum ada evaluasi supplier" description="Skor akan dibuat saat goods receipt selesai diposting." /></td></tr>@endforelse
        </tbody></table></div>{{ $scores->links() }}
    </x-metronic.card>

    @push('styles')<style>.supplier-kpi-card{border:1px solid var(--bs-gray-200);box-shadow:none}.report-kpi-icon{align-items:center;border-radius:.65rem;display:inline-flex;height:42px;justify-content:center;width:42px}.supplier-chart{min-height:315px}</style>@endpush
    @push('scripts')
        <script>document.addEventListener('DOMContentLoaded',function(){const trend=@json($scoreTrend);const ranking=@json($ranking);const empty=(target,text)=>target.innerHTML=`<div class="d-flex align-items-center justify-content-center text-muted h-300px">${text}</div>`;const trendTarget=document.getElementById('supplier-score-trend');const rankTarget=document.getElementById('supplier-ranking-chart');if(typeof window.ApexCharts==='undefined'){empty(trendTarget,'Grafik belum dapat dimuat.');empty(rankTarget,'Grafik belum dapat dimuat.');return}if(!trend.length)empty(trendTarget,'Belum ada skor pada periode ini.');else new ApexCharts(trendTarget,{series:[{name:'Total',data:trend.map(row=>Number(row.total_score))},{name:'Kualitas',data:trend.map(row=>Number(row.quality_score))},{name:'Pengiriman',data:trend.map(row=>Number(row.delivery_score))}],chart:{type:'line',height:315,toolbar:{show:false},fontFamily:'Inter, sans-serif'},colors:['#1b84ff','#17c653','#f6c000'],stroke:{curve:'smooth',width:3},dataLabels:{enabled:false},xaxis:{categories:trend.map(row=>row.date)},yaxis:{min:0,max:100,tickAmount:5},grid:{borderColor:'#e4e6ef',strokeDashArray:4},legend:{position:'top',horizontalAlign:'right'},tooltip:{y:{formatter:value=>`${value.toLocaleString('id-ID',{maximumFractionDigits:1})}/100`}}}).render();if(!ranking.length)empty(rankTarget,'Belum cukup data untuk ranking.');else new ApexCharts(rankTarget,{series:[{name:'Skor',data:ranking.map(row=>Number(row.total_score))}],chart:{type:'bar',height:315,toolbar:{show:false},fontFamily:'Inter, sans-serif'},colors:['#17c653'],plotOptions:{bar:{horizontal:true,borderRadius:5,barHeight:'52%'}},dataLabels:{enabled:false},xaxis:{categories:ranking.map(row=>row.supplier),min:0,max:100},grid:{borderColor:'#e4e6ef',strokeDashArray:4},tooltip:{y:{formatter:value=>`${value.toLocaleString('id-ID',{maximumFractionDigits:1})}/100`}}}).render()});</script>
    @endpush
@endsection
