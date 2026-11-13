<x-metronic.card title="Konfigurasi Nomor Dokumen" subtitle="Kelola format penomoran otomatis transaksi">
    <form method="POST" action="{{ route('admin.settings.document-numbers.update') }}">
        @csrf
        @method('PUT')
        <div class="table-responsive mb-4">
            <table class="table table-row-dashed table-hover align-middle gs-2 gy-3 mb-0">
                <thead>
                    <tr class="text-muted fw-bold text-uppercase fs-8 border-bottom border-gray-200">
                        <th>Dokumen</th>
                        <th>Prefix</th>
                        <th>Nomor Berikutnya</th>
                        <th>Padding Digit</th>
                        <th>Reset Tahunan</th>
                        <th>Format Token</th>
                        <th>Contoh Hasil</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sequences as $index => $sequence)
                        <tr>
                            <td class="fw-bold fs-7">
                                {{ strtoupper($sequence->document_type) }}
                                <input type="hidden" name="sequences[{{ $index }}][document_type]" value="{{ $sequence->document_type }}">
                            </td>
                            <td>
                                <input name="sequences[{{ $index }}][prefix]" value="{{ old("sequences.$index.prefix", $sequence->prefix) }}" class="form-control form-control-sm form-control-solid w-100px">
                            </td>
                            <td>
                                <input type="number" min="1" name="sequences[{{ $index }}][next_number]" value="{{ old("sequences.$index.next_number", $sequence->next_number) }}" class="form-control form-control-sm form-control-solid w-90px">
                            </td>
                            <td>
                                <input type="number" min="3" max="10" name="sequences[{{ $index }}][padding]" value="{{ old("sequences.$index.padding", $sequence->padding) }}" class="form-control form-control-sm form-control-solid w-80px">
                            </td>
                            <td class="text-center">
                                <input type="hidden" name="sequences[{{ $index }}][reset_yearly]" value="0">
                                <input class="form-check-input" type="checkbox" name="sequences[{{ $index }}][reset_yearly]" value="1" @checked(old("sequences.$index.reset_yearly", $sequence->reset_yearly))>
                            </td>
                            <td>
                                <input name="sequences[{{ $index }}][format]" value="{{ old("sequences.$index.format", $sequence->format) }}" class="form-control form-control-sm form-control-solid">
                            </td>
                            <td>
                                <span class="badge badge-light-primary fs-8 font-monospace">{{ $numberService->preview($sequence) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="alert alert-light-primary border border-primary border-dashed d-flex align-items-center p-3 mb-5">
            <i class="ki-outline ki-information-2 fs-3 text-primary me-3"></i>
            <div class="text-gray-700 fs-8">
                Token format yang didukung: <code>{prefix}</code>, <code>{location}</code>, <code>{year}</code>, <code>{month}</code>, <code>{sequence}</code>. Nomor dibuat otomatis dan atomic saat transaksi diposting.
            </div>
        </div>
        <div class="d-flex justify-content-end">
            <button class="btn btn-primary fw-bold" type="submit">
                <i class="ki-outline ki-check fs-4 me-1"></i> Simpan Nomor Dokumen
            </button>
        </div>
    </form>
</x-metronic.card>
