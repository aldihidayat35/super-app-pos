@extends('layouts.metronic.app')

@section('title', 'Buka Shift Kasir - '.config('app.name'))
@section('page_title', 'Buka Shift Kasir')

@section('content')
    <div class="row justify-content-center">
        <div class="col-xl-9">
            <x-metronic.card title="Persiapan Shift">
                <form method="POST" action="{{ route('retail.shifts.store') }}" class="row g-4" id="open-shift-form">
                    @csrf
                    <div class="col-md-6">
                        <x-metronic.form-group name="branch_id" label="Cabang" required>
                            @if($branches->count() === 1)
                                <input type="hidden" name="branch_id" id="shift-branch" value="{{ $branches->first()->id }}">
                                <select class="form-select form-select-solid" data-searchable="false" disabled aria-readonly="true">
                                    <option value="{{ $branches->first()->id }}" selected>{{ $branches->first()->name }}</option>
                                </select>
                            @else
                                <select name="branch_id" id="shift-branch" class="form-select form-select-solid" required>
                                    @foreach($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected((int) old('branch_id', $selectedBranchId) === $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            @endif
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-3">
                        <x-metronic.form-group name="cashier" label="Kasir">
                            <input class="form-control form-control-solid" value="{{ auth()->user()->name }}" readonly>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-3">
                        <x-metronic.form-group name="time" label="Tanggal/Waktu">
                            <input class="form-control form-control-solid" value="{{ now()->format('d/m/Y H:i') }}" readonly>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="opening_cash_amount" label="Modal Awal" required>
                            <div class="input-group">
                                <span class="input-group-text">Rp</span>
                                <input type="number" step="1" min="0" name="opening_cash_amount" value="{{ old('opening_cash_amount', 0) }}" class="form-control" required>
                            </div>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-md-6">
                        <x-metronic.form-group name="terminal_code" label="Terminal">
                            <input name="terminal_code" value="{{ old('terminal_code') }}" class="form-control" placeholder="POS-01">
                        </x-metronic.form-group>
                    </div>
                    <input type="hidden" name="discrepancy_threshold_amount" value="50000">
                    <div class="col-12">
                        <x-metronic.form-group name="notes" label="Catatan Serah Terima">
                            <textarea name="notes" rows="3" class="form-control" placeholder="Catatan awal shift atau serah terima kas">{{ old('notes') }}</textarea>
                        </x-metronic.form-group>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('retail.shifts.current') }}" class="btn btn-light">Batal</a>
                        <button class="btn btn-primary" id="open-shift-submit" type="submit">
                            <i class="ki-outline ki-check fs-4 me-1"></i>Buka Shift
                        </button>
                    </div>
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection
