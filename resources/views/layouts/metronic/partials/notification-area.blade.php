@if (!empty($compact))
    <div class="d-flex align-items-center ms-2"><button type="button" class="btn btn-icon btn-active-light-primary position-relative" aria-label="Notifikasi"><i class="ki-outline ki-notification-on fs-2"></i><span class="position-absolute top-25 start-75 translate-middle badge badge-circle badge-light-primary w-15px h-15px fs-9">0</span></button></div>
@elseif ($errors->any())
    <div class="alert alert-danger d-flex align-items-start p-5 mb-5"><i class="ki-outline ki-information-5 fs-2hx text-danger me-4"></i><div><h4 class="mb-2">Periksa kembali data yang diisi.</h4><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
@endif
