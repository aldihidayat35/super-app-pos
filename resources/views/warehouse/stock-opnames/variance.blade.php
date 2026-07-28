@section('title', 'Variance Stok Opname - ' . config('app.name'))
@section('page_title', 'Variance Stok Opname')

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-opname-variance" title="Panduan Halaman Variance Stok Opname">
        <x-slot:function>
            <p>Halaman ini menampilkan perbandingan antara saldo sistem dan hasil fisik per item. Variance membantu approver menentukan apakah selisih masuk akal dan perlu approval tambahan.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Halaman menampilkan kartu ringkasan dan tabel daftar selisih.</li><li>Setiap item menampilkan sistem, fisik, selisih, nilai, alasan, dan tingkat risiko.</li><li>Ekspor CSV tersedia untuk analisis offline.</li><li>Approval navigasi mengarah ke halaman approval.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Total Selisih Qty:</strong> jumlah total selisih.</li><li><strong>Nilai Selisih:</strong> total selisih moneter.</li><li><strong>Threshold Qty/Nilai:</strong> batas flagging.</li><li><strong>Approval Owner:</strong> apakah perlu approval.</li><li><strong>Peringatan Transaksi:</strong> item berubah setelah snapshot.</li><li><strong>Produk:</strong> SKU dan nama item.</li><li><strong>Lokasi:</strong> bin penyimpanan.</li><li><strong>Sistem/Fisik/Selisih:</strong> perbandingan qty.</li><li><strong>Nilai:</strong> estimasi nilai selisih.</li><li><strong>Alasan:</strong> kategori selisih dan catatan.</li><li><strong>Risiko:</strong> Normal, Approval tinggi, atau Review transaksi.</li><li><strong>Export CSV:</strong> mengunduh laporan variance.</li><li><strong>Approval:</strong> navigasi ke halaman approval.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Halaman ini hanya untuk review. Filter dan export tidak mengubah data. Keputusan approval ada di halaman approval terpisah.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Periksa ringkasan selisih dan threshold.</li><li>Scroll tabel untuk review per item.</li><li>Perhatikan kolom Risiko untuk item yang perlu perhatian.</li><li>Klik <strong>Export CSV</strong> untuk analisis offline.</li><li>Klik <strong>Approval</strong> untuk melanjutkan.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Item dengan badge "Ada transaksi setelah snapshot" perlu review manual.</li><li>Risiko "Approval tinggi" berarti selisih melewati threshold.</li><li>Periksa sebelum lanjut ke approval.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Terlihat Kopi Arabika sistem 100, fisik 97, selisih -3, nilai Rp 150.000. Risiko Normal. Namun Kopi Robusta sistem 50, fisik 40, selisih -10, nilai Rp 500.000. Risiko "Approval tinggi".</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
    <x-metronic.page-title :title="'Variance ' . $opname->number" description="Perbandingan saldo sistem dengan hasil fisik dan estimasi nilai selisih.">
        <x-slot:actions>
            <a href="{{ route('warehouse.stock-opnames.variance.export', $opname) }}" class="btn btn-light-success">Export CSV</a>
            <a href="{{ route('warehouse.stock-opnames.approval', $opname) }}" class="btn btn-primary">Approval</a>
        </x-slot:actions>
    </x-metronic.page-title>

    @if($opname->items->contains('has_transaction_after_snapshot', true))
        <div class="alert alert-warning">Ada transaksi setelah snapshot pada sebagian item. Review sebelum approval agar koreksi tidak menimpa transaksi valid.</div>
    @endif

    <div class="row g-4 mb-6">
        <div class="col-md-3"><x-metronic.card title="Total Selisih Qty"><div class="fs-2 fw-bold">{{ qty($opname->total_difference_qty) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Nilai Selisih"><div class="fs-5 fw-bold">{{ \App\Support\CurrencyFormatter::rupiah($opname->total_difference_value) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Threshold Qty"><div class="fs-2 fw-bold">{{ qty($opname->threshold_qty) }}</div></x-metronic.card></div>
        <div class="col-md-3"><x-metronic.card title="Approval Owner"><div class="fs-4 fw-bold">{{ $opname->requires_owner_approval ? 'Wajib' : 'Tidak' }}</div></x-metronic.card></div>
    </div>

    <x-metronic.card title="Daftar Selisih">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead><tr class="text-muted fw-bold text-uppercase fs-7"><th>Produk</th><th>Lokasi</th><th class="text-end">Sistem</th><th class="text-end">Fisik</th><th class="text-end">Selisih</th><th class="text-end">Nilai</th><th>Alasan</th><th>Risiko</th></tr></thead>
                <tbody>
                @foreach($opname->items as $item)
                    @php
                        $absQty = abs((float) $item->difference_qty);
                        $risk = $item->has_transaction_after_snapshot ? 'Review transaksi' : ($absQty > (float) $opname->threshold_qty || (float) $item->estimated_value > (float) $opname->threshold_value ? 'Approval tinggi' : 'Normal');
                    @endphp
                    <tr>
                        <td class="fw-bold">{{ $item->product_sku_snapshot }}<div class="text-muted">{{ $item->product_name_snapshot }}</div></td>
                        <td>{{ $item->warehouseLocation?->full_code ?: '-' }}</td>
                        <td class="text-end">{{ qty($item->system_qty_snapshot) }}</td>
                        <td class="text-end">{{ $item->counted_qty === null ? '-' : qty($item->counted_qty) }}</td>
                        <td class="text-end fw-bold">{{ qty($item->difference_qty) }}</td>
                        <td class="text-end">{{ \App\Support\CurrencyFormatter::rupiah($item->estimated_value) }}</td>
                        <td>{{ $item->reason?->label() ?: '-' }}<div class="text-muted fs-8">{{ $item->note }}</div></td>
                        <td><span class="badge badge-light-{{ $risk === 'Normal' ? 'success' : 'warning' }}">{{ $risk }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </x-metronic.card>
@endsection
