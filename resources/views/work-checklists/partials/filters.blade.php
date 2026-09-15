<form method="GET" class="row g-3 align-items-end">
    <input type="hidden" name="tab" value="{{ $targetTab }}">
    <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Mulai</label><input type="date" name="from" value="{{ $filters['from'] }}" class="form-control form-control-sm form-control-solid"></div>
    <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Selesai</label><input type="date" name="to" value="{{ $filters['to'] }}" class="form-control form-control-sm form-control-solid"></div>
    <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Frekuensi</label><select name="frequency" class="form-select form-select-sm form-select-solid"><option value="">Semua</option>@foreach($frequencies as $frequency)<option value="{{ $frequency->value }}" @selected($filters['frequency']===$frequency->value)>{{ $frequency->label() }}</option>@endforeach</select></div>
    <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Status</label><select name="status" class="form-select form-select-sm form-select-solid"><option value="">Semua</option><option value="open" @selected($filters['status']==='open')>Belum Selesai</option><option value="completed" @selected($filters['status']==='completed')>Selesai</option></select></div>
    <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Lokasi</label><select name="work_location_id" class="form-select form-select-sm form-select-solid"><option value="">Semua</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected($filters['work_location_id']===$location->id)>{{ $location->name }}</option>@endforeach</select></div>
    @if($showUserRole)
        <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Akun</label><select name="user_id" class="form-select form-select-sm form-select-solid"><option value="">Semua</option>@foreach($users as $optionUser)<option value="{{ $optionUser->id }}" @selected($filters['user_id']===$optionUser->id)>{{ $optionUser->name }}</option>@endforeach</select></div>
        <div class="col-xl-2 col-md-4"><label class="form-label fs-8">Role</label><select name="role" class="form-select form-select-sm form-select-solid"><option value="">Semua</option>@foreach($roles as $roleName=>$meta)<option value="{{ $roleName }}" @selected($filters['role']===$roleName)>{{ $meta['label'] }}</option>@endforeach</select></div>
    @endif
    <div class="col-xl-2 col-md-4"><button class="btn btn-sm btn-primary w-100"><i class="ki-outline ki-filter fs-6"></i>Terapkan</button></div>
</form>
