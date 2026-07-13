<x-metronic.page-title :title="$title" :description="$description" />
<div class="row g-5 g-xl-8 mb-8">
    @foreach ($metrics as $metric)
        <div class="col-sm-6 col-xl-3"><x-metronic.card class="h-100"><div class="d-flex align-items-center justify-content-between"><div><div class="text-muted fw-semibold mb-2">{{ $metric['label'] }}</div><div class="fs-2x fw-bold text-gray-900">{{ $metric['value'] }}</div></div><span class="symbol symbol-50px"><span class="symbol-label bg-light-{{ $metric['color'] }}"><i class="{{ $metric['icon'] }} fs-2x text-{{ $metric['color'] }}"></i></span></span></div></x-metronic.card></div>
    @endforeach
</div>
<x-metronic.card title="Aktivitas Terbaru">
    <x-metronic.table-toolbar><button class="btn btn-light-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#dashboard-filter"><i class="ki-outline ki-filter fs-4"></i> Filter</button></x-metronic.table-toolbar>
    <x-metronic.empty-state title="Belum ada aktivitas" description="Aktivitas operasional akan muncul setelah modul transaksi diaktifkan." />
</x-metronic.card>
<x-metronic.filter-drawer id="dashboard-filter" title="Filter Dashboard"><div class="mb-5"><label class="form-label">Tanggal</label><input class="form-control" data-datepicker placeholder="Pilih tanggal"></div><button class="btn btn-primary w-100" data-bs-dismiss="offcanvas">Terapkan Filter</button></x-metronic.filter-drawer>
