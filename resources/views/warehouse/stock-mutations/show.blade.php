@extends('layouts.metronic.app')

@section('title', 'Detail Mutasi Stok - ' . config('app.name'))
@section('page_title', 'Detail Mutasi Stok')

@section('toolbar_actions')
    <a href="{{ route('warehouse.stock-card.index', ['product_id' => $mutation->product_id]) }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-4"></i> Kartu Stok
    </a>
@endsection

@section('page_guide')
    <x-metronic.page-guide id="warehouse-stock-mutation-show" title="Panduan Halaman Detail Mutasi Stok">
        <x-slot:function>
            <p>Halaman ini menampilkan rincian satu mutasi stok spesifik secara read-only. Mutasi adalah jejak perubahan stok yang tercipta dari proses penerimaan, pengeluaran, transfer, reservasi, kerusakan, retur, atau penyesuaian.</p>
            <p>Halaman ini berguna untuk audit dan menelusuri alasan di balik perubahan saldo produk pada waktu tertentu.</p>
        </x-slot:function>
        <x-slot:workflow>
            <ol><li>Halaman dibuka dari link Detail pada Kartu Stok atau Transfer.</li><li>Sistem menampilkan semua informasi mutasi: waktu, produk, lokasi, perubahan on hand/reserved/damaged, referensi dokumen, dan metadata audit.</li><li>Pengguna memeriksa rincian untuk memahami perubahan stok.</li></ol>
        </x-slot:workflow>
        <x-slot:parts>
            <ul><li><strong>Waktu:</strong> saat mutasi terjadi.</li><li><strong>Produk:</strong> SKU dan nama produk yang berubah.</li><li><strong>Satuan Dasar:</strong> satuan pengukuran produk.</li><li><strong>Lokasi Kerja:</strong> gudang atau cabang tempat mutasi.</li><li><strong>Zona/Rak/Bin:</strong> lokasi fisik spesifik.</li><li><strong>Actor:</strong> pengguna yang menjalankan proses.</li><li><strong>On Hand Before/Change/After:</strong> saldo fisik sebelum, perubahan, dan sesudah.</li><li><strong>Reserved Before/Change/After:</strong> saldo reserved sebelum dan sesudah.</li><li><strong>Damaged Before/Change/After:</strong> saldo rusak sebelum dan sesudah.</li><li><strong>Referensi:</strong> jenis dan nomor dokumen asal mutasi.</li><li><strong>Idempotency Key:</strong> kunci unik mencegah duplikasi.</li><li><strong>Catatan:</strong> alasan atau keterangan perubahan.</li><li><strong>Metadata Audit:</strong> JSON lengkap data tambahan mutasi.</li></ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Halaman ini hanya menampilkan informasi dan tidak mengubah data apapun. Mutasi bersifat append-only; koreksi dilakukan melalui dokumen baru bukan peng editan.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol><li>Buka halaman dari detail mutasi yang ingin diperiksa.</li><li>Periksa Waktu, Produk, Lokasi, dan Jenis mutasi.</li><li>Bandingseluruhi nilai Before, Change, dan After untuk memastikan perubahan benar.</li><li>Lihat Referensi untuk mengetahui dokumen sumber.</li><li>Periksa Metadata Audit jika diperlukan investigasi mendalam.</li></ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0"><ul><li>Halaman ini read-only; Anda tidak dapat mengedit atau menghapus mutasi dari sini.</li><li>Jika ada ketidaksesuaian, buat dokumen koreksi resmi bukan mengubah data manual.</li><li>Metadata audit bersifat teknis; hubungi administrator jika diperlukan interpretasi.</li></ul></div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Mutasi transfer keluar menunjukkan On Hand Before 100, Change -10, After 90. Referensi ST-2024-001 mengaitkan mutasi ini dengan Surat Transfer nomor tersebut.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@php
    use App\Enums\StockMutationType;

    $type = $mutation->mutation_type;
    $typeValue = $type?->value ?? '';
    $isInbound = in_array($typeValue, ['receive', 'transfer_in', 'recover', 'return_in'], true);
    $isOutbound = in_array($typeValue, ['issue', 'transfer_out', 'damage', 'return_out', 'release_reservation'], true);
    $isNeutral = in_array($typeValue, ['reserve', 'adjust'], true);

    $typeMeta = match (true) {
        $isInbound => [
            'bg'      => 'bg-light-success',
            'text'    => 'text-success',
            'border'  => 'border-success',
            'icon'    => 'ki-arrow-down',
            'badge'   => 'badge-light-success',
            'label'   => 'Inbound',
        ],
        $isOutbound => [
            'bg'      => 'bg-light-danger',
            'text'    => 'text-danger',
            'border'  => 'border-danger',
            'icon'    => 'ki-arrow-up',
            'badge'   => 'badge-light-danger',
            'label'   => 'Outbound',
        ],
        default     => [
            'bg'      => 'bg-light-primary',
            'text'    => 'text-primary',
            'border'  => 'border-primary',
            'icon'    => 'ki-switch',
            'badge'   => 'badge-light-primary',
            'label'   => 'Penyesuaian',
        ],
    };

    $movementQty  = (float) $mutation->quantity_on_hand_change;
    $movementSign = $movementQty > 0 ? '+' : ($movementQty < 0 ? '−' : '');
    $movementAbs  = qty(abs($movementQty));
@endphp

@section('content')
    {{-- Hero Banner --}}
    <div class="card card-flush {{ $typeMeta['border'] }} border border-2 mb-6 shadow-sm">
        <div class="card-body p-5 p-lg-7">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                {{-- Left: type + label --}}
                <div class="d-flex align-items-center gap-4">
                    <span class="symbol symbol-65px {{ $typeMeta['bg'] }} rounded-2 flex-shrink-0">
                        <i class="ki-outline {{ $typeMeta['icon'] }} fs-2 {{ $typeMeta['text'] }}"></i>
                    </span>
                    <div>
                        <div class="text-muted fw-semibold fs-7 text-uppercase ls-1 mb-1">Jenis Mutasi</div>
                        <h2 class="fw-bolder text-gray-900 mb-1">{{ $type?->label() ?? 'Tidak diketahui' }}</h2>
                        <div class="d-flex align-items-center gap-3 text-muted fs-7">
                            <span><i class="ki-outline ki-calendar me-1"></i>{{ $mutation->occurred_at?->format('l, d F Y') }}</span>
                            <span class="text-gray-400">·</span>
                            <span class="fw-semibold text-gray-700">{{ $mutation->occurred_at?->format('H:i:s') }} WIB</span>
                        </div>
                    </div>
                </div>

                {{-- Right: On Hand movement --}}
                <div class="text-lg-end">
                    <div class="text-muted fw-semibold fs-7 text-uppercase ls-1 mb-2">Perubahan On Hand</div>
                    <div class="d-flex align-items-center justify-content-lg-end gap-3 flex-wrap">
                        <div>
                            <span class="d-block text-muted fs-8 lh-1">Before</span>
                            <span class="fs-3 fw-bolder text-gray-700">{{ qty((float) $mutation->quantity_on_hand_before) }}</span>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-2 text-gray-300"></i>
                        <div class="px-4 py-2 rounded-2 {{ $typeMeta['bg'] }}">
                            <span class="d-block text-muted fs-8 lh-1">Change</span>
                            <span class="fs-3 fw-bolder {{ $typeMeta['text'] }}">{{ $movementSign }}{{ $movementAbs }}</span>
                        </div>
                        <i class="ki-outline ki-arrow-right fs-2 text-gray-300"></i>
                        <div>
                            <span class="d-block text-muted fs-8 lh-1">After</span>
                            <span class="fs-3 fw-bolder text-gray-900">{{ qty((float) $mutation->quantity_on_hand_after) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- KPI Cards --}}
    <div class="row g-5 mb-6">
        {{-- On Hand --}}
        <div class="col-md-4">
            <div class="card card-flush border border-success border-2 h-100">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold fs-7 text-uppercase ls-1">On Hand</span>
                        <span class="symbol symbol-38px bg-light-success rounded">
                            <i class="ki-outline ki-package fs-4 text-success"></i>
                        </span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-3 fw-bolder text-gray-900">{{ qty((float) $mutation->quantity_on_hand_after) }}</span>
                        <span class="fs-7 text-muted">pcs</span>
                    </div>
                    @php
                        $ohBefore = (float) $mutation->quantity_on_hand_before;
                        $ohChange = (float) $mutation->quantity_on_hand_change;
                    @endphp
                    <div class="text-muted fs-7 mt-2">
                        <span class="fw-semibold text-gray-700">{{ qty($ohBefore) }}</span>
                        @if($ohChange != 0)
                            <i class="ki-outline {{ $ohChange > 0 ? 'ki-arrow-up text-success' : 'ki-arrow-down text-danger' }} fs-8 mx-1"></i>
                            <span class="fw-semibold {{ $ohChange > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $ohChange > 0 ? '+' : '' }}{{ qty(abs($ohChange)) }}
                            </span>
                        @else
                            <span class="text-gray-400 ms-1">— tidak berubah</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Reserved --}}
        <div class="col-md-4">
            <div class="card card-flush border border-warning border-2 h-100">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold fs-7 text-uppercase ls-1">Reserved</span>
                        <span class="symbol symbol-38px bg-light-warning rounded">
                            <i class="ki-outline ki-lock fs-4 text-warning"></i>
                        </span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-3 fw-bolder text-gray-900">{{ qty((float) $mutation->quantity_reserved_after) }}</span>
                        <span class="fs-7 text-muted">pcs</span>
                    </div>
                    @php
                        $reBefore = (float) $mutation->quantity_reserved_before;
                        $reChange = (float) $mutation->quantity_reserved_change;
                    @endphp
                    <div class="text-muted fs-7 mt-2">
                        <span class="fw-semibold text-gray-700">{{ qty($reBefore) }}</span>
                        @if($reChange != 0)
                            <i class="ki-outline {{ $reChange > 0 ? 'ki-arrow-up text-success' : 'ki-arrow-down text-danger' }} fs-8 mx-1"></i>
                            <span class="fw-semibold {{ $reChange > 0 ? 'text-success' : 'text-danger' }}">
                                {{ $reChange > 0 ? '+' : '' }}{{ qty(abs($reChange)) }}
                            </span>
                        @else
                            <span class="text-gray-400 ms-1">— tidak berubah</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Damaged --}}
        <div class="col-md-4">
            <div class="card card-flush border border-danger border-2 h-100">
                <div class="card-body p-5">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <span class="text-muted fw-semibold fs-7 text-uppercase ls-1">Damaged</span>
                        <span class="symbol symbol-38px bg-light-danger rounded">
                            <i class="ki-outline ki-shield-cross fs-4 text-danger"></i>
                        </span>
                    </div>
                    <div class="d-flex align-items-baseline gap-2">
                        <span class="fs-3 fw-bolder text-gray-900">{{ qty((float) $mutation->quantity_damaged_after) }}</span>
                        <span class="fs-7 text-muted">pcs</span>
                    </div>
                    @php
                        $daBefore = (float) $mutation->quantity_damaged_before;
                        $daChange = (float) $mutation->quantity_damaged_change;
                    @endphp
                    <div class="text-muted fs-7 mt-2">
                        <span class="fw-semibold text-gray-700">{{ qty($daBefore) }}</span>
                        @if($daChange != 0)
                            <i class="ki-outline {{ $daChange > 0 ? 'ki-arrow-up text-danger' : 'ki-arrow-down text-success' }} fs-8 mx-1"></i>
                            <span class="fw-semibold {{ $daChange > 0 ? 'text-danger' : 'text-success' }}">
                                {{ $daChange > 0 ? '+' : '' }}{{ qty(abs($daChange)) }}
                            </span>
                        @else
                            <span class="text-gray-400 ms-1">— tidak berubah</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5">
        {{-- Left: Product, Location, Actor, Reference --}}
        <div class="col-lg-7">
            <x-metronic.card>
                <x-slot:title>Informasi Produk &amp; Lokasi</x-slot:title>

                {{-- Product hero --}}
                <div class="d-flex align-items-center gap-4 p-4 bg-light-primary rounded mb-5">
                    <span class="symbol symbol-52px bg-primary rounded">
                        <i class="ki-outline ki-cube-2 fs-2 text-white"></i>
                    </span>
                    <div class="flex-grow-1">
                        <div class="fw-bolder text-gray-900 fs-5 mb-1">{{ $mutation->product?->name ?? '—' }}</div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <span class="badge badge-light-primary fw-bold">{{ $mutation->product?->sku ?? '—' }}</span>
                            <span class="text-muted fs-7">
                                <i class="ki-outline ki-category fs-6 me-1"></i>
                                {{ $mutation->product?->baseUnit?->name ?? 'Tanpa satuan' }}
                            </span>
                        </div>
                    </div>
                    @if($mutation->product)
                        <a href="{{ route('admin.products.show', $mutation->product) }}" class="btn btn-sm btn-primary">
                            <i class="ki-outline ki-eye fs-5"></i> Lihat Produk
                        </a>
                    @endif
                </div>

                {{-- Detail grid --}}
                <div class="row g-4">
                    {{-- Lokasi Kerja + Zona --}}
                    <div class="col-md-6">
                        <div class="border border-dashed border-gray-300 rounded p-4 h-100">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="ki-outline ki-geolocation text-primary fs-5"></i>
                                <span class="fw-semibold text-muted fs-7 text-uppercase ls-1">Lokasi Kerja</span>
                            </div>
                            <div class="fw-bold text-gray-900 fs-6 mb-2">{{ $mutation->workLocation?->name ?? '—' }}</div>
                            <div class="text-muted fs-7">
                                Zona/Rak/Bin:
                                <span class="fw-semibold text-gray-800">{{ $mutation->warehouseLocation?->full_code ?? 'Default lokasi' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Actor --}}
                    <div class="col-md-6">
                        <div class="border border-dashed border-gray-300 rounded p-4 h-100">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="ki-outline ki-user text-info fs-5"></i>
                                <span class="fw-semibold text-muted fs-7 text-uppercase ls-1">Pelaku Mutasi</span>
                            </div>
                            <div class="fw-bold text-gray-900 fs-6 mb-1">{{ $mutation->actor?->name ?? '—' }}</div>
                            <div class="text-muted fs-7">
                                ID: <span class="fw-semibold text-gray-800">#{{ $mutation->actor_user_id ?? '—' }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Referensi --}}
                    <div class="col-md-6">
                        <div class="border border-dashed border-gray-300 rounded p-4 h-100">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="ki-outline ki-document text-success fs-5"></i>
                                <span class="fw-semibold text-muted fs-7 text-uppercase ls-1">Referensi Dokumen</span>
                            </div>
                            @if($mutation->reference_no)
                                <span class="badge badge-light-success fw-bold fs-6">
                                    {{ $mutation->reference_type ?: 'DOC' }} / {{ $mutation->reference_no }}
                                </span>
                            @else
                                <div class="fw-bold text-gray-400">—</div>
                            @endif
                        </div>
                    </div>

                    {{-- Idempotency Key --}}
                    <div class="col-md-6">
                        <div class="border border-dashed border-gray-300 rounded p-4 h-100">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="ki-outline ki-key text-warning fs-5"></i>
                                <span class="fw-semibold text-muted fs-7 text-uppercase ls-1">Idempotency Key</span>
                            </div>
                            @if($mutation->idempotency_key)
                                <code class="fs-7 text-gray-800">{{ $mutation->idempotency_key }}</code>
                            @else
                                <div class="fw-bold text-gray-400">—</div>
                            @endif
                        </div>
                    </div>

                    {{-- Catatan --}}
                    <div class="col-12">
                        <div class="border border-dashed border-gray-300 rounded p-4">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="ki-outline ki-message-text text-gray-600 fs-5"></i>
                                <span class="fw-semibold text-muted fs-7 text-uppercase ls-1">Catatan / Alasan</span>
                            </div>
                            @if($mutation->reason)
                                <div class="text-gray-800 fs-6">{{ $mutation->reason }}</div>
                            @else
                                <div class="text-gray-400 fst-italic">Tidak ada catatan untuk mutasi ini.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </x-metronic.card>
        </div>

        {{-- Right: Stok breakdown + Metadata --}}
        <div class="col-lg-5">
            {{-- Rincian Perubahan Stok --}}
            <x-metronic.card title="Rincian Perubahan Stok">
                <x-slot:toolbar>
                    <span class="badge {{ $typeMeta['badge'] }}">{{ $typeMeta['label'] }}</span>
                </x-slot:toolbar>

                <div class="d-flex flex-column gap-4">
                    @php
                        $changeRows = [
                            ['label' => 'On Hand',    'before' => $mutation->quantity_on_hand_before,   'change' => $mutation->quantity_on_hand_change,   'after'  => $mutation->quantity_on_hand_after,   'border' => 'border-success', 'icon' => 'ki-package',     'iconColor' => 'text-success'],
                            ['label' => 'Reserved',   'before' => $mutation->quantity_reserved_before, 'change' => $mutation->quantity_reserved_change, 'after'  => $mutation->quantity_reserved_after, 'border' => 'border-warning', 'icon' => 'ki-lock',        'iconColor' => 'text-warning'],
                            ['label' => 'Damaged',    'before' => $mutation->quantity_damaged_before,  'change' => $mutation->quantity_damaged_change,  'after'  => $mutation->quantity_damaged_after,  'border' => 'border-danger',  'icon' => 'ki-shield-cross','iconColor' => 'text-danger'],
                        ];
                    @endphp

                    @foreach($changeRows as $row)
                        @php $ch = (float) $row['change']; @endphp
                        <div class="border-start border-3 {{ $row['border'] }} ps-4 py-2">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="ki-outline {{ $row['icon'] }} {{ $row['iconColor'] }} fs-5"></i>
                                <span class="fw-bold text-gray-800 fs-7 text-uppercase ls-1">{{ $row['label'] }}</span>
                            </div>
                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                <div class="text-center">
                                    <div class="text-muted fs-8 text-uppercase">Before</div>
                                    <div class="fw-bolder fs-5 text-gray-900">{{ qty((float) $row['before']) }}</div>
                                </div>
                                <i class="ki-outline ki-arrow-right fs-3 text-gray-300"></i>
                                <div class="text-center">
                                    <div class="text-muted fs-8 text-uppercase">Change</div>
                                    <div class="fw-bolder fs-5 {{ $ch > 0 ? 'text-success' : ($ch < 0 ? 'text-danger' : 'text-gray-600') }}">
                                        {{ $ch > 0 ? '+' : ($ch < 0 ? '−' : '') }}{{ qty(abs($ch)) }}
                                    </div>
                                </div>
                                <i class="ki-outline ki-arrow-right fs-3 text-gray-300"></i>
                                <div class="text-center">
                                    <div class="text-muted fs-8 text-uppercase">After</div>
                                    <div class="fw-bolder fs-5 text-gray-900">{{ qty((float) $row['after']) }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-metronic.card>

            {{-- Metadata Audit --}}
            <x-metronic.card title="Metadata Audit" class="mt-5">
                <x-slot:toolbar>
                    <span class="badge badge-light-dark fs-8">JSON</span>
                </x-slot:toolbar>

                @php $meta = $mutation->metadata ?? []; @endphp
                @if(empty($meta))
                    <div class="d-flex align-items-center gap-3 p-4 bg-light rounded">
                        <i class="ki-outline ki-information-2 fs-2 text-muted"></i>
                        <div class="text-muted">Tidak ada metadata tambahan untuk mutasi ini.</div>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless align-middle mb-0">
                            <tbody>
                            @foreach($meta as $key => $value)
                                <tr class="border-bottom border-gray-100">
                                    <th class="fw-semibold text-gray-600 ps-0 py-3" style="width: 40%;">{{ $key }}</th>
                                    <td class="text-gray-800 pe-0 py-3">
                                        @if(is_array($value) || is_object($value))
                                            <code class="fs-7 text-primary">{{ json_encode($value, JSON_UNESCAPED_UNICODE) }}</code>
                                        @elseif(is_bool($value))
                                            <span class="badge {{ $value ? 'badge-light-success' : 'badge-light-danger' }}">{{ $value ? 'true' : 'false' }}</span>
                                        @else
                                            <span class="fs-7">{{ $value }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <details class="mt-4">
                        <summary class="text-muted fs-8 fw-semibold cursor-pointer">Lihat sebagai JSON mentah</summary>
                        <pre class="bg-gray-100 p-4 rounded fs-7 mt-3 mb-0">{{ json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                    </details>
                @endif
            </x-metronic.card>
        </div>
    </div>
@endsection
