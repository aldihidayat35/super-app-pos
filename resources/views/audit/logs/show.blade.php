@extends('layouts.metronic.app')

@section('title', 'Detail Audit Log')
@section('page_title', 'Detail Audit Log')

@section('content')
    <x-metronic.page-title title="Detail Audit #{{ $log->id }}" description="Detail JSON aman untuk investigasi." />
    <x-metronic.card>
        <dl class="row"><dt class="col-sm-3">Actor</dt><dd class="col-sm-9">{{ $log->actor?->email }}</dd><dt class="col-sm-3">Event</dt><dd class="col-sm-9">{{ $log->event }}</dd><dt class="col-sm-3">Route</dt><dd class="col-sm-9">{{ $log->route_name }} {{ $log->http_method }}</dd><dt class="col-sm-3">IP/Device</dt><dd class="col-sm-9">{{ $log->ip_address }} / {{ $log->user_agent_hash }}</dd><dt class="col-sm-3">Reason</dt><dd class="col-sm-9">{{ $log->reason }}</dd></dl>
        <div class="row"><div class="col-md-6"><h6>Old</h6><pre class="bg-light p-3 rounded small">{{ json_encode($log->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div><div class="col-md-6"><h6>New</h6><pre class="bg-light p-3 rounded small">{{ json_encode($log->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></div>
    </x-metronic.card>
@endsection
