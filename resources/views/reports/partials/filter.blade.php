<form method="GET" class="row g-3 mb-5">
    <div class="col-md-2">
        <label class="form-label">Tanggal Mulai</label>
        <input type="date" name="start_date" value="{{ request('start_date', $filters['start_date'] ?? now()->startOfMonth()->toDateString()) }}" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Tanggal Akhir</label>
        <input type="date" name="end_date" value="{{ request('end_date', $filters['end_date'] ?? now()->toDateString()) }}" class="form-control">
    </div>
    <div class="col-md-2">
        <label class="form-label">Lokasi</label>
        <input type="number" name="work_location_id" value="{{ request('work_location_id') }}" class="form-control" placeholder="ID lokasi">
    </div>
    <div class="col-md-2">
        <label class="form-label">Channel</label>
        <input name="channel" value="{{ request('channel') }}" class="form-control" placeholder="retail/b2b">
    </div>
    <div class="col-md-2">
        <label class="form-label">Status</label>
        <input name="status" value="{{ request('status') }}" class="form-control" placeholder="Status">
    </div>
    <div class="col-md-2 d-flex align-items-end">
        <button class="btn btn-light-primary w-100">Terapkan Filter</button>
    </div>
</form>
