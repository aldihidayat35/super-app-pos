@section('title', 'Approval Stok Opname - ' . config('app.name'))
@section('page_title', 'Approval Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-approval" title="Panduan Halaman Approval Stok Opname">
        <x-slot:function>
            <p>Halaman ini digunakan untuk approve atau reject hasil opname sebelum adjustment mutation dibuat. Approver (biasanya Kepala Gudang atau Owner) meninjau selisih dan keputusan approval.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Ringkasan approval ditampilkan termasuk nilai selisih dan threshold.</li><li>Approver meninjau variance detail.</li><li>Approve atau reject opname dengan catatan.</li><li>Setelah approved, selesaikan untuk membuat adjustment mutation.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Status:</strong> status approval opname.</li><li><strong>Nilai Selisih:</strong> total selisih nilai moneter.</li><li><strong>Threshold Nilai:</strong> batas yang memicu approval owner.</li><li><strong>Approval Owner:</strong> apakah butuh persetujuan Owner.</li><li><strong>Peringatan Transaksi:</strong> item berubah setelah snapshot.</li><li><strong>Catatan Approval:</strong> keterangan approve (wajib).</li><li><strong>Approve Opname:</strong> menyetujui opname.</li><li><strong>Alasan Reject:</strong> keterangan reject (wajib).</li><li><strong>Reject Opname:</strong> menolak opname.</li><li><strong>Selesaikan & Buat Adjustment:</strong> finalize adjustment.</li><li><strong>Riwayat Approval:</strong> histori keputusan approval.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Approve mengizinkan adjustment mutation dibuat. Reject mengembalikan opname untuk review ulang. Adjustment hanya membuat mutasi append-only, tidak mengubah histori lama.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa ringkasan: selisih, threshold, dan approval owner.</li><li>Review variance detail jika diperlukan.</li><li>Beri catatan approval.</li><li>Klik <strong>Approve Opname</strong> atau <strong>Reject Opname</strong>.</li><li>Setelah approved, klik <strong>Selesaikan & Buat Adjustment</strong>.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Approval wajib dicatat alasannya.</li><li>Nilai selisih tinggi mungkin perlu approval Owner.</li><li>Item dengan transaksi setelah snapshot perlu review khusus.</li><li>Reject mengembalikan opname ke status sebelumnya.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Selisih Rp 500.000, threshold Rp 1.000.000. Tidak butuh Owner. Approve dengan catatan "Selisih wajar, sudah diverifikasi counter".</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="'Approval ' . $opname->number" description="Approve/reject hasil opname sebelum adjustment mutation dibuat.">
        <x-slot:actions>
            <a href="{{ route('warehouse.stock-opnames.variance', $opname) }}" class="btn btn-light-info">Lihat Variance</a>
        </x-slot:actions>
    </x-metronic.page-title>

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Ringkasan Approval">
                <div class="mb-3"><div class="text-muted">Status</div><x-metronic.status-badge :status="$opname->status" /></div>
                <div class="mb-3"><div class="text-muted">Nilai Selisih</div><div class="fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div></div>
                <div class="mb-3"><div class="text-muted">Threshold Nilai</div><div>{{ \App\Support\CurrencyFormatter::rupiah($opname->threshold_value) }}</div></div>
                <div class="mb-3"><div class="text-muted">Approval Owner</div><div class="fw-bold">{{ $opname->requires_owner_approval ? 'Wajib' : 'Tidak wajib' }}</div></div>
                @if($opname->items->contains('has_transaction_after_snapshot', true))
                    <div class="alert alert-warning">Sebagian item memiliki transaksi setelah snapshot.</div>
                @endif
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Keputusan">
                @if($opname->status === \App\Enums\StockOpnameStatus::PENDING_APPROVAL)
                    <div class="row g-4">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('warehouse.stock-opnames.approve', $opname) }}">
                                @csrf
                                <x-metronic.form-group name="notes" label="Catatan Approval" required><textarea name="notes" rows="4" class="form-control form-control-solid" required>{{ old('notes', 'Disetujui setelah review variance.') }}</textarea></x-metronic.form-group>
                                <button class="btn btn-success">Approve Opname</button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('warehouse.stock-opnames.reject', $opname) }}">
                                @csrf
                                <x-metronic.form-group name="notes" label="Alasan Reject" required><textarea name="notes" rows="4" class="form-control form-control-solid" required>{{ old('notes') }}</textarea></x-metronic.form-group>
                                <button class="btn btn-light-danger">Reject Opname</button>
                            </form>
                        </div>
                    </div>
                @elseif($opname->status === \App\Enums\StockOpnameStatus::APPROVED)
                    <div class="alert alert-success">Opname sudah approved. Klik selesai untuk membuat adjustment append-only melalui InventoryService.</div>
                    <form method="POST" action="{{ route('warehouse.stock-opnames.complete', $opname) }}">@csrf<button class="btn btn-primary">Selesaikan & Buat Adjustment</button></form>
                @else
                    <x-metronic.empty-state title="Tidak menunggu approval" description="Approval hanya aktif pada status pending approval atau approved." />
                @endif
            </x-metronic.card>

            <x-metronic.card title="Riwayat Approval" class="mt-6">
                <table class="table"><thead><tr><th>Level</th><th>Status</th><th>Approver</th><th>Catatan</th><th>Waktu</th></tr></thead><tbody>
                    @forelse($opname->approvals as $approval)
                        <tr><td>{{ $approval->approval_level }}</td><td>{{ $approval->status }}</td><td>{{ $approval->approver?->name }}</td><td>{{ $approval->notes }}</td><td>{{ $approval->approved_at?->format('d/m/Y H:i') }}</td></tr>
                    @empty
                        <tr><td colspan="5"><x-metronic.empty-state title="Belum ada approval" description="Keputusan akan tampil di sini." /></td></tr>
                    @endforelse
                </tbody></table>
            </x-metronic.card>
        </div>
    </div>
@endsection
