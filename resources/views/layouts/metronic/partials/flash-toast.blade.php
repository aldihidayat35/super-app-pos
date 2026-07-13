@if (session('notification'))
    @php($notification = session('notification'))
    <div class="position-fixed top-0 end-0 p-5" style="z-index: 1300">
        <div class="toast show border-0" role="alert"><div class="toast-header bg-light-{{ $notification['type'] }}"><strong class="me-auto">{{ config('app.name') }}</strong><button type="button" class="btn-close" data-bs-dismiss="toast"></button></div><div class="toast-body">{{ $notification['message'] }}</div></div>
    </div>
@endif
