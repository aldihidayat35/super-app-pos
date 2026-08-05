@extends('layouts.metronic.app')

@section('title', 'Persetujuan Stok Opname - ' . config('app.name'))
@section('page_title', 'Persetujuan Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-approval" title="Panduan Persetujuan Stok Opname">
        <x-slot:function><p>Atasan memeriksa hasil opname dan memberi keputusan sebelum penyesuaian stok dibuat.</p></x-slot:function>
        <x-slot:workflow><ol><li>Periksa ringkasan dan halaman Selisih Stok.</li><li>Setujui atau tolak disertai catatan.</li><li>Setelah disetujui, selesaikan opname untuk membuat mutasi penyesuaian.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Persetujuan Owner:</strong> diperlukan bila risiko melampaui aturan.</li><li><strong>Transaksi Setelah Acuan:</strong> harus diperiksa agar transaksi sah tidak terkoreksi.</li><li><strong>Selesaikan:</strong> tindakan yang benar-benar membuat penyesuaian stok.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Persetujuan saja belum mengubah stok. Saldo berubah ketika penyelesaian dijalankan melalui InventoryService.</p></x-slot:impacts>
        <x-slot:operation><ol><li>Buka Selisih Stok.</li><li>Periksa bukti dan alasan.</li><li>Berikan keputusan.</li><li>Jalankan penyelesaian hanya setelah disetujui.</li></ol></x-slot:operation>
        <x-slot:warnings><div class="alert alert-warning mb-0">Penyelesaian membuat mutasi stok yang tidak boleh diedit. Pastikan seluruh selisih sudah dipertanggungjawabkan.</div></x-slot:warnings>
        <x-slot:example><p>Jika ada tiga item berselisih dan satu transaksi setelah acuan, periksa Kartu Stok item tersebut sebelum menyetujui.</p></x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="'Persetujuan ' . $opname->number" description="Pemeriksaan akhir sebelum penyesuaian saldo stok dibuat.">
        <x-slot:actions><a href="{{ route('warehouse.stock-opnames.variance', $opname) }}" class="btn btn-light-info">Lihat Selisih Stok</a></x-slot:actions>
    </x-metronic.page-title>

    <div class="alert alert-info">Menyetujui opname belum langsung mengubah stok. Perubahan saldo akan dibuat saat proses penyelesaian dan penyesuaian dijalankan.</div>

    <div class="row g-4 mb-6">
        @foreach([['Total Item', $summary['total']], ['Item Sesuai', $summary['matching']], ['Item Berselisih', $summary['different']], ['Melewati Batas', $summary['above_threshold']], ['Transaksi Setelah Acuan', $summary['after_reference']]] as [$label, $value])
            <div class="col-6 col-xl"><x-metronic.card><div class="text-muted fs-7">{{ $label }}</div><div class="fs-2 fw-bold">{{ $value }}</div></x-metronic.card></div>
        @endforeach
    </div>

    <div class="row g-6">
        <div class="col-lg-4">
            <x-metronic.card title="Ringkasan Nilai">
                <div class="mb-4"><div class="text-muted">Status</div><x-metronic.status-badge :status="$opname->status" /></div>
                <div class="mb-4"><div class="text-muted">Total Selisih Jumlah</div><div class="fs-4 fw-bold">{{ qty($summary['quantity_difference']) }}</div></div>
                <div class="mb-4"><div class="text-muted">Total Nilai Selisih</div><div class="fs-4 fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($summary['value_difference']) }}</div></div>
                <div><div class="text-muted">Memerlukan Persetujuan Owner</div><span class="badge badge-light-{{ $opname->requires_owner_approval ? 'danger' : 'success' }}">{{ $opname->requires_owner_approval ? 'Ya, wajib' : 'Tidak' }}</span></div>
            </x-metronic.card>
        </div>
        <div class="col-lg-8">
            <x-metronic.card title="Keputusan">
                @if($summary['after_reference'] > 0)<div class="alert alert-warning">Ada {{ $summary['after_reference'] }} item dengan transaksi setelah acuan stok. Pastikan sudah diperiksa.</div>@endif
                @if($opname->status === \App\Enums\StockOpnameStatus::PENDING_APPROVAL)
                    <div class="row g-4">
                        <div class="col-md-6"><form method="POST" action="{{ route('warehouse.stock-opnames.approve', $opname) }}">@csrf<x-metronic.form-group name="notes" label="Catatan Persetujuan" required><textarea name="notes" rows="4" class="form-control form-control-solid" required>{{ old('notes') }}</textarea></x-metronic.form-group><button class="btn btn-success">Setujui Opname</button></form></div>
                        <div class="col-md-6"><form method="POST" action="{{ route('warehouse.stock-opnames.reject', $opname) }}">@csrf<x-metronic.form-group name="notes" label="Alasan Penolakan" required><textarea name="notes" rows="4" class="form-control form-control-solid" required>{{ old('notes') }}</textarea></x-metronic.form-group><button class="btn btn-light-danger">Tolak Opname</button></form></div>
                    </div>
                @elseif($opname->status === \App\Enums\StockOpnameStatus::APPROVED)
                    <div class="alert alert-danger"><strong>Perhatian:</strong> proses ini akan membuat mutasi penyesuaian stok berdasarkan hasil fisik yang telah disetujui.</div>
                    <form method="POST" action="{{ route('warehouse.stock-opnames.complete', $opname) }}">@csrf<button class="btn btn-primary">Selesaikan dan Buat Penyesuaian</button></form>
                @else
                    <x-metronic.empty-state title="Tidak menunggu persetujuan" description="Tindakan hanya tersedia saat dokumen menunggu persetujuan atau sudah disetujui." />
                @endif
            </x-metronic.card>
        </div>
    </div>

    <x-metronic.card title="Riwayat Persetujuan" class="mt-6">
        <div class="table-responsive"><table class="table table-row-dashed"><thead><tr><th>Tingkat</th><th>Status</th><th>Pemberi Keputusan</th><th>Catatan</th><th>Waktu</th></tr></thead><tbody>
            @forelse($opname->approvals as $approval)<tr><td>{{ $approval->approval_level }}</td><td>{{ $approval->status }}</td><td>{{ $approval->approver?->name ?: '-' }}</td><td>{{ $approval->notes }}</td><td>{{ $approval->approved_at?->format('d/m/Y H:i') ?: '-' }}</td></tr>
            @empty<tr><td colspan="5"><x-metronic.empty-state title="Belum ada riwayat persetujuan" description="Keputusan akan tampil di sini." /></td></tr>@endforelse
        </tbody></table></div>
    </x-metronic.card>
@endsection
