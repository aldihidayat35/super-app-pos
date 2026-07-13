@extends('layouts.metronic.app')

@section('title', 'Konfigurasi Nomor Dokumen - ' . config('app.name'))
@section('page_title', 'Nomor Dokumen')

@section('content')
    <x-metronic.card title="Konfigurasi Nomor Dokumen">
        <form method="POST" action="{{ route('admin.settings.document-numbers.update') }}">
            @csrf
            @method('PUT')
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Dokumen</th><th>Prefix</th><th>Sequence Berikutnya</th><th>Padding</th><th>Reset Tahunan</th><th>Format</th><th>Preview</th></tr></thead>
                    <tbody>
                        @foreach ($sequences as $index => $sequence)
                            <tr>
                                <td class="fw-bold">{{ strtoupper($sequence->document_type) }}<input type="hidden" name="sequences[{{ $index }}][document_type]" value="{{ $sequence->document_type }}"></td>
                                <td><input name="sequences[{{ $index }}][prefix]" value="{{ old("sequences.$index.prefix", $sequence->prefix) }}" class="form-control form-control-sm"></td>
                                <td><input type="number" min="1" name="sequences[{{ $index }}][next_number]" value="{{ old("sequences.$index.next_number", $sequence->next_number) }}" class="form-control form-control-sm"></td>
                                <td><input type="number" min="3" max="10" name="sequences[{{ $index }}][padding]" value="{{ old("sequences.$index.padding", $sequence->padding) }}" class="form-control form-control-sm"></td>
                                <td>
                                    <input type="hidden" name="sequences[{{ $index }}][reset_yearly]" value="0">
                                    <input class="form-check-input" type="checkbox" name="sequences[{{ $index }}][reset_yearly]" value="1" @checked(old("sequences.$index.reset_yearly", $sequence->reset_yearly))>
                                </td>
                                <td><input name="sequences[{{ $index }}][format]" value="{{ old("sequences.$index.format", $sequence->format) }}" class="form-control form-control-sm"></td>
                                <td><span class="badge badge-light-primary">{{ $numberService->preview($sequence) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="alert alert-info">Token format: <code>{prefix}</code>, <code>{location}</code>, <code>{year}</code>, <code>{month}</code>, <code>{sequence}</code>. Nomor aktual dibuat atomic dengan row lock.</div>
            <div class="d-flex justify-content-end">
                <button class="btn btn-primary" type="submit">Simpan Nomor Dokumen</button>
            </div>
        </form>
    </x-metronic.card>
@endsection
