@extends('layouts.metronic.app')

@php
    use App\Enums\AttendanceStatus;
    $statusSeries = collect($statuses)->map(fn (AttendanceStatus $status) => ['label' => $status->label(), 'value' => (int) ($summary[$status->value] ?? 0)])->filter(fn (array $row) => $row['value'] > 0)->values();
@endphp

@section('title', 'Laporan Kehadiran - '.config('app.name'))
@section('page_title', 'Laporan Kehadiran')

@section('page_guide')
    <x-metronic.page-guide id="report-attendance" title="Panduan Laporan Kehadiran">
        <x-slot:function><p>Memantau tingkat kehadiran, keterlambatan, jam kerja, dan pola disiplin per lokasi atau karyawan.</p></x-slot:function>
        <x-slot:workflow><ol><li>Pilih periode, lokasi, karyawan, atau status.</li><li>Baca tingkat kehadiran dan total keterlambatan.</li><li>Bandingkan tren harian serta lokasi dengan menit terlambat tertinggi.</li><li>Telusuri catatan individu pada tabel detail.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>KPI:</strong> tingkat kehadiran dan beban waktu.</li><li><strong>Tren:</strong> hadir tepat waktu, terlambat, dan tidak hadir.</li><li><strong>Komposisi:</strong> proporsi status absensi.</li><li><strong>Lokasi:</strong> area dengan keterlambatan tertinggi.</li></ul></x-slot:parts>
        <x-slot:warnings><div class="alert alert-warning mb-0">Koreksi absensi harus dilakukan melalui proses koreksi dan approval agar rekam audit tetap utuh.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title title="Laporan Kehadiran" description="Kehadiran, ketepatan waktu, dan jam kerja berdasarkan lokasi serta karyawan.">
        @unless (in_array('reports.shift-productivity.index', config('ui_visibility.hidden_navigation_routes', []), true))
            <x-slot:actions><a href="{{ route('reports.shift-productivity.index', ['from' => $filters['from'], 'to' => $filters['to']]) }}" class="btn btn-light-primary"><i class="ki-outline ki-chart-line-up fs-5"></i> Produktivitas Shift</a></x-slot:actions>
        @endunless
    </x-metronic.page-title>

    <x-metronic.card class="mb-5">
        <form method="GET" class="row g-4 align-items-end">
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Mulai</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-solid"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Selesai</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-solid"></div>
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Status</label><select name="status" class="form-select form-select-solid"><option value="">Semua status</option>@foreach($statuses as $status)<option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Lokasi</label><select name="work_location_id" class="form-select form-select-solid" data-control="select2"><option value="">Semua lokasi</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected(request('work_location_id') == $location->id)>{{ $location->code }} — {{ $location->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><label class="form-label fw-semibold">Karyawan</label><select name="employee_id" class="form-select form-select-solid" data-control="select2"><option value="">Semua karyawan</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected(request('employee_id') == $employee->id)>{{ $employee->name }}</option>@endforeach</select></div>
            <div class="col-xl-2 col-md-4"><button class="btn btn-primary w-100"><i class="ki-outline ki-filter fs-5"></i> Terapkan</button></div>
        </form>
    </x-metronic.card>

    @php($attendanceKpis = [
        ['Catatan Kehadiran', number_format($metrics['total'], 0, ',', '.'), '', 'ki-calendar-tick', 'primary', 'Total catatan dalam filter'],
        ['Tingkat Kehadiran', number_format($metrics['attendance_rate'], 1, ',', '.'), '%', 'ki-check-circle', 'success', 'Hadir tepat waktu dan terlambat'],
        ['Terlambat', number_format((int) ($summary[AttendanceStatus::LATE->value] ?? 0), 0, ',', '.'), ' kali', 'ki-time', 'warning', 'Catatan berstatus terlambat'],
        ['Alfa', number_format((int) ($summary[AttendanceStatus::ALPHA->value] ?? 0), 0, ',', '.'), ' kali', 'ki-cross-circle', 'danger', 'Tidak hadir tanpa keterangan'],
        ['Jam Kerja', number_format($metrics['worked_hours'], 1, ',', '.'), ' jam', 'ki-timer', 'info', 'Akumulasi waktu kerja tercatat'],
        ['Menit Terlambat', number_format($metrics['late_minutes'], 0, ',', '.'), ' menit', 'ki-information-5', 'warning', 'Akumulasi keterlambatan'],
    ])
    <div class="row g-5 mb-5">@foreach($attendanceKpis as [$label, $value, $suffix, $icon, $tone, $help])<div class="col-xxl-2 col-xl-4 col-md-6"><div class="card attendance-kpi-card h-100"><div class="card-body p-5"><div class="d-flex align-items-center justify-content-between mb-4"><span class="report-kpi-icon bg-light-{{ $tone }} text-{{ $tone }}"><i class="ki-outline {{ $icon }} fs-2"></i></span><span class="text-muted text-uppercase fs-8 fw-bold">{{ $label }}</span></div><div class="fs-3 fw-bolder text-gray-900">{{ $value }}<span class="fs-7 text-muted fw-normal">{{ $suffix }}</span></div><div class="text-muted fs-8 mt-2">{{ $help }}</div></div></div></div>@endforeach</div>

    <div class="row g-5 mb-5">
        <div class="col-xl-8"><x-metronic.card title="Tren Kehadiran Harian" class="h-100"><div class="text-muted fs-7 mb-3">Perbandingan tepat waktu, terlambat, dan tidak hadir</div><div id="attendance-trend-chart" class="attendance-chart"></div></x-metronic.card></div>
        <div class="col-xl-4"><x-metronic.card title="Komposisi Status" class="h-100"><div class="text-muted fs-7 mb-3">Proporsi status pada scope filter</div><div id="attendance-status-chart" class="attendance-chart"></div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Lokasi yang Perlu Perhatian" class="mb-5">
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr class="text-muted text-uppercase fs-8 fw-bold"><th>Lokasi</th><th>Catatan</th><th>Terlambat</th><th>Total Menit</th><th>Rata-rata/Kejadian</th></tr></thead><tbody>@forelse($locationSummary as $location)<tr><td class="fw-semibold text-gray-900">{{ $location->location }}</td><td>{{ number_format($location->total, 0, ',', '.') }}</td><td><span @class(['badge','badge-light-danger'=>(int)$location->late_count>0,'badge-light-success'=>(int)$location->late_count===0])>{{ number_format($location->late_count, 0, ',', '.') }}</span></td><td>{{ number_format($location->late_minutes, 0, ',', '.') }} menit</td><td>{{ (int) $location->late_count === 0 ? '0' : number_format($location->late_minutes / $location->late_count, 1, ',', '.') }} menit</td></tr>@empty<tr><td colspan="5"><x-metronic.empty-state title="Belum ada data lokasi" description="Data akan muncul setelah absensi tercatat." /></td></tr>@endforelse</tbody></table></div>
    </x-metronic.card>

    <x-metronic.card title="Detail Kehadiran">
        <div class="table-responsive"><table class="table table-row-dashed align-middle"><thead><tr class="text-muted text-uppercase fs-8 fw-bold"><th>Tanggal</th><th>Karyawan</th><th>Lokasi</th><th>Masuk</th><th>Pulang</th><th>Status</th><th>Deviasi</th><th>Jam Kerja</th></tr></thead><tbody>
            @forelse($attendances as $attendance)<tr><td>{{ $attendance->attendance_date?->format('d/m/Y') }}</td><td><div class="fw-semibold text-gray-900">{{ $attendance->employee?->name }}</div><div class="text-muted fs-8">{{ $attendance->employee?->employee_no }}</div></td><td>{{ $attendance->workLocation?->name }}</td><td>{{ $attendance->check_in_at?->format('H:i') ?: '—' }}</td><td>{{ $attendance->check_out_at?->format('H:i') ?: '—' }}</td><td><x-metronic.status-badge :status="$attendance->status->value" :label="$attendance->status->label()" /></td><td><div>{{ $attendance->late_minutes }} menit telat</div><div class="text-muted fs-8">{{ $attendance->early_leave_minutes }} menit pulang cepat</div></td><td>{{ intdiv((int) $attendance->worked_minutes, 60) }}j {{ (int) $attendance->worked_minutes % 60 }}m</td></tr>@empty<tr><td colspan="8"><x-metronic.empty-state title="Belum ada data kehadiran" description="Ubah filter atau tunggu absensi tercatat." /></td></tr>@endforelse
        </tbody></table></div>{{ $attendances->links() }}
    </x-metronic.card>

    @push('styles')<style>.attendance-kpi-card{border:1px solid var(--bs-gray-200);box-shadow:none}.report-kpi-icon{align-items:center;border-radius:.65rem;display:inline-flex;height:42px;justify-content:center;width:42px}.attendance-chart{min-height:315px}</style>@endpush
    @push('scripts')
        <script>document.addEventListener('DOMContentLoaded',function(){const daily=@json($dailyRows);const statuses=@json($statusSeries);const trend=document.getElementById('attendance-trend-chart');const composition=document.getElementById('attendance-status-chart');const empty=(target,text)=>target.innerHTML=`<div class="d-flex align-items-center justify-content-center text-muted h-300px">${text}</div>`;if(typeof window.ApexCharts==='undefined'){empty(trend,'Grafik belum dapat dimuat.');empty(composition,'Grafik belum dapat dimuat.');return}if(!daily.length)empty(trend,'Belum ada data kehadiran pada periode ini.');else new ApexCharts(trend,{series:[{name:'Tepat Waktu',data:daily.map(row=>row.on_time)},{name:'Terlambat',data:daily.map(row=>row.late)},{name:'Tidak Hadir',data:daily.map(row=>row.absent)}],chart:{type:'bar',height:315,stacked:true,toolbar:{show:false},fontFamily:'Inter, sans-serif'},colors:['#17c653','#f6c000','#f8285a'],plotOptions:{bar:{borderRadius:4,columnWidth:'48%'}},dataLabels:{enabled:false},xaxis:{categories:daily.map(row=>row.date)},yaxis:{labels:{formatter:value=>Math.round(value)}},grid:{borderColor:'#e4e6ef',strokeDashArray:4},legend:{position:'top',horizontalAlign:'right'}}).render();if(!statuses.length)empty(composition,'Belum ada komposisi status.');else new ApexCharts(composition,{series:statuses.map(row=>row.value),labels:statuses.map(row=>row.label),chart:{type:'donut',height:315,fontFamily:'Inter, sans-serif'},colors:['#17c653','#f6c000','#1b84ff','#43ced7','#f8285a','#7239ea','#99a1b7'],dataLabels:{enabled:false},legend:{position:'bottom'},plotOptions:{pie:{donut:{size:'68%',labels:{show:true,total:{show:true,label:'Catatan',formatter:chart=>chart.globals.seriesTotals.reduce((a,b)=>a+b,0).toLocaleString('id-ID')}}}}}}).render()});</script>
    @endpush
@endsection
