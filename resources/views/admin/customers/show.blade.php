@extends('layouts.metronic.app')

@section('title', $customer->business_name)
@section('page_title', 'Detail Pelanggan')

@section('toolbar_actions')
    <a href="{{ route('admin.customers.index') }}" class="btn btn-light">
        <i class="ki-outline ki-arrow-left fs-5 me-2"></i>Kembali
    </a>
    @can('manageAccess', $customer)
        <a href="{{ route('admin.customers.access.edit', $customer) }}" class="btn btn-light-primary">
            <i class="ki-outline ki-people fs-5 me-2"></i>Alamat & Akses
        </a>
    @endcan
    @can('update', $customer)
        <a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-primary">
            <i class="ki-outline ki-pencil fs-5 me-2"></i>Edit Pelanggan
        </a>
    @endcan
@endsection

@section('page_guide')
    <x-metronic.page-guide id="admin-customer-show" title="Panduan Dashboard Pelanggan">
        <x-slot:function><p>Halaman ini menjadi pusat informasi pelanggan: profil, aktivitas order, tagihan, pembayaran, pengiriman, alamat, akun portal, harga khusus, dan dokumen.</p></x-slot:function>
        <x-slot:workflow><ol><li>Periksa status akun, limit, piutang, dan kontak utama.</li><li>Buka tab sesuai aktivitas yang akan diperiksa.</li><li>Gunakan tombol aksi pada tab agar pelanggan otomatis terbawa ke halaman input terkait.</li><li>Gunakan halaman pengaturan untuk perubahan sensitif seperti verifikasi, kredit, dan harga.</li></ol></x-slot:workflow>
        <x-slot:parts><ul><li><strong>Ringkasan:</strong> identitas dan konfigurasi komersial.</li><li><strong>Pesanan:</strong> aktivitas order B2B terbaru.</li><li><strong>Tagihan:</strong> invoice dan piutang pelanggan.</li><li><strong>Pembayaran:</strong> riwayat pembayaran dan verifikasi.</li><li><strong>Pengiriman:</strong> status shipment dan nomor resi.</li><li><strong>Relasi & Akses:</strong> alamat kirim dan akun portal B2B.</li><li><strong>Harga & Dokumen:</strong> harga khusus serta dokumen verifikasi.</li></ul></x-slot:parts>
        <x-slot:impacts><p>Perubahan status, limit kredit, alamat, akun portal, dan harga khusus akan memengaruhi kemampuan pelanggan bertransaksi.</p></x-slot:impacts>
        <x-slot:warnings><div class="alert alert-warning mb-0">Data transaksi hanya tampil jika pengguna memiliki permission modul terkait. Akses yang tidak tersedia tidak dapat dibuka hanya dengan menyalin URL.</div></x-slot:warnings>
    </x-metronic.page-guide>
@endsection

@php
    $orders = $customer->relationLoaded('b2bOrders') ? $customer->b2bOrders : collect();
    $invoices = $customer->relationLoaded('invoices') ? $customer->invoices : collect();
    $payments = $customer->relationLoaded('payments') ? $customer->payments : collect();
    $shipments = $customer->relationLoaded('shipments') ? $customer->shipments : collect();
    $receivables = $customer->relationLoaded('receivables') ? $customer->receivables : collect();
    $priceOverrides = $customer->relationLoaded('priceOverrides') ? $customer->priceOverrides : collect();
    $initial = strtoupper(mb_substr($customer->business_name, 0, 1));
@endphp

@section('content')
    <div class="card mb-6 overflow-hidden">
        <div class="card-body p-6 p-lg-8">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-6">
                <div class="d-flex align-items-center gap-5 min-w-0">
                    <div class="symbol symbol-70px symbol-lg-90px flex-shrink-0">
                        <span class="symbol-label bg-light-primary text-primary fs-2x fw-bold">{{ $initial }}</span>
                    </div>
                    <div class="min-w-0">
                        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            <h1 class="fs-2x fw-bold text-gray-900 mb-0 text-break">{{ $customer->business_name }}</h1>
                            <x-metronic.status-badge :status="$customer->account_status->value" :label="$customer->account_status->label()" class="fs-7" />
                            <span class="badge badge-light-{{ $customer->is_active ? 'success' : 'secondary' }}">{{ $customer->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </div>
                        <div class="d-flex flex-wrap gap-4 text-gray-600 fs-7 fw-semibold">
                            <span><i class="ki-outline ki-tag fs-6 me-1"></i>{{ $customer->code }}</span>
                            <span><i class="ki-outline ki-category fs-6 me-1"></i>{{ $customer->type->label() }}</span>
                            <span><i class="ki-outline ki-geolocation fs-6 me-1"></i>{{ $customer->city ?: 'Kota belum diisi' }}</span>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    @if($customer->whatsapp_number)
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $customer->whatsapp_number) }}" target="_blank" rel="noopener" class="btn btn-light-success btn-sm">
                            <i class="ki-outline ki-whatsapp fs-5 me-1"></i>WhatsApp
                        </a>
                    @endif
                    @if($customer->email)
                        <a href="mailto:{{ $customer->email }}" class="btn btn-light-primary btn-sm">
                            <i class="ki-outline ki-sms fs-5 me-1"></i>Kirim Email
                        </a>
                    @endif
                    @can('manageSettings', $customer)
                        <a href="{{ route('admin.customers.settings.edit', $customer) }}" class="btn btn-light btn-sm">
                            <i class="ki-outline ki-setting-2 fs-5 me-1"></i>Pengaturan
                        </a>
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 mb-6">
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="text-muted fw-semibold mb-2">Total Pesanan</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['orders'] ?? '—' }}</div><div class="text-muted fs-8">Seluruh order B2B</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-primary"><i class="ki-outline ki-basket fs-2x text-primary"></i></span></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="text-muted fw-semibold mb-2">Invoice Terbuka</div><div class="fs-2x fw-bold text-gray-900">{{ $metrics['open_invoices'] ?? '—' }}</div><div class="text-muted fs-8">Belum lunas/dibatalkan</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-warning"><i class="ki-outline ki-document fs-2x text-warning"></i></span></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="text-muted fw-semibold mb-2">Saldo Piutang</div><div class="fs-3 fw-bold text-gray-900">{{ $metrics['receivable_balance'] !== null ? App\Support\CurrencyFormatter::rupiah($metrics['receivable_balance']) : '—' }}</div><div class="text-muted fs-8">Saldo berjalan pelanggan</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-danger"><i class="ki-outline ki-wallet fs-2x text-danger"></i></span></span>
            </div></div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card h-100"><div class="card-body d-flex align-items-center justify-content-between">
                <div><div class="text-muted fw-semibold mb-2">Sisa Limit Kredit</div><div class="fs-3 fw-bold text-gray-900">{{ $metrics['available_credit'] !== null ? App\Support\CurrencyFormatter::rupiah($metrics['available_credit']) : '—' }}</div><div class="text-muted fs-8">Limit dikurangi piutang</div></div>
                <span class="symbol symbol-50px"><span class="symbol-label bg-light-success"><i class="ki-outline ki-chart-line-up fs-2x text-success"></i></span></span>
            </div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header border-0 pt-2 px-4 px-lg-7">
            <ul class="nav nav-stretch nav-line-tabs nav-line-tabs-2x border-transparent fs-6 fw-semibold flex-nowrap overflow-auto w-100" role="tablist">
                @foreach([
                    ['ringkasan', 'ki-profile-circle', 'Ringkasan'],
                    ['pesanan', 'ki-basket', 'Pesanan'],
                    ['tagihan', 'ki-document', 'Tagihan'],
                    ['pembayaran', 'ki-wallet', 'Pembayaran'],
                    ['pengiriman', 'ki-delivery', 'Pengiriman'],
                    ['akses', 'ki-people', 'Relasi & Akses'],
                    ['harga', 'ki-price-tag', 'Harga & Dokumen'],
                ] as [$key, $icon, $label])
                    <li class="nav-item flex-shrink-0" role="presentation">
                        <button class="nav-link text-active-primary py-4 px-3 px-lg-4 {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#customer_tab_{{ $key }}" type="button" role="tab" aria-controls="customer_tab_{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                            <i class="ki-outline {{ $icon }} fs-4 me-2"></i>{{ $label }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card-body p-4 p-lg-7">
            <div class="tab-content">
                <div class="tab-pane fade show active" id="customer_tab_ringkasan" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-6">
                        <div><h2 class="fs-3 fw-bold mb-1">Ringkasan Pelanggan</h2><div class="text-muted">Identitas, kontak, dan ketentuan komersial yang sedang berlaku.</div></div>
                        @can('update', $customer)<a href="{{ route('admin.customers.edit', $customer) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Ubah Profil</a>@endcan
                    </div>
                    <div class="row g-6">
                        <div class="col-lg-6">
                            <div class="border border-gray-300 rounded p-5 h-100">
                                <h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-profile-circle fs-3 text-primary me-2"></i>Identitas & Kontak</h3>
                                <div class="row g-4">
                                    @foreach([
                                        ['Pemilik', $customer->owner_name],
                                        ['PIC Utama', $customer->pic_name],
                                        ['WhatsApp', $customer->whatsapp_number],
                                        ['Email', $customer->email],
                                        ['Kota', $customer->city],
                                        ['Alamat Usaha', $customer->business_address],
                                    ] as [$label, $value])
                                        <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">{{ $label }}</div><div class="fw-semibold text-gray-800 text-break">{{ $value ?: '—' }}</div></div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="border border-gray-300 rounded p-5 h-100">
                                <h3 class="fs-5 fw-bold mb-5"><i class="ki-outline ki-chart fs-3 text-success me-2"></i>Ketentuan Komersial</h3>
                                <div class="row g-4">
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Kategori Harga</div><div class="fw-semibold text-capitalize">{{ $customer->price_category }}</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Minimum Order</div><div class="fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($customer->minimum_order) }}</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Termin</div><div class="fw-semibold">{{ $customer->payment_term_days }} hari</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Limit Kredit</div><div class="fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($customer->credit_limit) }}</div></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Status Verifikasi</div><x-metronic.status-badge :status="$customer->verification_status->value" :label="$customer->verification_status->label()" /></div>
                                    <div class="col-sm-6"><div class="text-muted fs-8 text-uppercase fw-bold mb-1">Status Akun</div><x-metronic.status-badge :status="$customer->account_status->value" :label="$customer->account_status->label()" /></div>
                                </div>
                            </div>
                        </div>
                        @if($customer->notes || $customer->status_reason)
                            <div class="col-12"><div class="alert alert-light border mb-0"><div class="fw-bold mb-2">Catatan Internal</div><div>{{ $customer->status_reason ?: $customer->notes }}</div></div></div>
                        @endif
                    </div>
                </div>

                <div class="tab-pane fade" id="customer_tab_pesanan" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Pesanan B2B Terbaru</h2><div class="text-muted">Sepuluh order terakhir yang dibuat oleh akun pelanggan.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @can('manageAccess', $customer)<a href="{{ route('admin.customers.access.edit', $customer) }}" class="btn btn-sm btn-light"><i class="ki-outline ki-people fs-5 me-1"></i>Kelola Pemesan</a>@endcan
                            @if($access['orders'])<a href="{{ route('warehouse.b2b-orders.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Lihat Semua Order</a>@endif
                        </div>
                    </div>
                    @if(!$access['orders'])
                        <x-metronic.empty-state title="Akses pesanan tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat transaksi order B2B pelanggan ini." icon="ki-outline ki-lock" />
                    @else
                        <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Order</th><th>Tanggal</th><th>Item</th><th>Total</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                        @forelse($orders as $order)
                            <tr><td class="fw-bold">{{ $order->number }}</td><td>{{ ($order->submitted_at ?? $order->created_at)?->translatedFormat('d M Y, H:i') }}</td><td>{{ $order->items_count }} item</td><td class="fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($order->grand_total_amount) }}</td><td><x-metronic.status-badge :status="$order->status->value" :label="$order->status->label()" /></td><td class="text-end"><a href="{{ route('warehouse.b2b-orders.review', $order) }}" class="btn btn-sm btn-light-primary">Buka</a></td></tr>
                        @empty
                            <tr><td colspan="6"><x-metronic.empty-state title="Belum ada pesanan" description="Order akan tampil setelah pengguna pelanggan bertransaksi melalui portal B2B." icon="ki-outline ki-basket" /></td></tr>
                        @endforelse
                        </tbody></table></div>
                    @endif
                </div>

                <div class="tab-pane fade" id="customer_tab_tagihan" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Invoice & Piutang</h2><div class="text-muted">Dokumen tagihan dan saldo piutang terbaru pelanggan.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($access['receivables'])<a href="{{ route('receivables.customers.show', $customer) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-book fs-5 me-1"></i>Kartu Piutang</a>@endif
                            @if(auth()->user()?->can('receivables.pay') || auth()->user()?->can('payments.create'))<a href="{{ route('receivables.payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Input Pembayaran</a>@endif
                        </div>
                    </div>
                    @if(!$access['invoices'] && !$access['receivables'])
                        <x-metronic.empty-state title="Akses tagihan tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat invoice atau piutang." icon="ki-outline ki-lock" />
                    @else
                        @if($access['invoices'])
                            <h3 class="fs-5 fw-bold mb-3">Invoice Terbaru</h3>
                            <div class="table-responsive mb-7"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Invoice</th><th>Terbit/Jatuh Tempo</th><th>Total</th><th>Sisa</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                            @forelse($invoices as $invoice)
                                <tr><td><div class="fw-bold">{{ $invoice->number }}</div><div class="text-muted fs-8">{{ $invoice->order?->number ?: 'Tanpa order' }}</div></td><td>{{ $invoice->issue_date?->translatedFormat('d M Y') ?: '—' }}<div class="text-muted fs-8">Tempo {{ $invoice->due_date?->translatedFormat('d M Y') ?: '—' }}</div></td><td>{{ App\Support\CurrencyFormatter::rupiah($invoice->total_amount) }}</td><td class="fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($invoice->outstanding_amount) }}</td><td><x-metronic.status-badge :status="$invoice->status->value" :label="$invoice->status->label()" /></td><td class="text-end">@can('view', $invoice)<a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-light-primary">Detail</a>@endcan</td></tr>
                            @empty
                                <tr><td colspan="6"><x-metronic.empty-state title="Belum ada invoice" description="Invoice pelanggan akan tampil setelah order diterbitkan menjadi tagihan." icon="ki-outline ki-document" /></td></tr>
                            @endforelse
                            </tbody></table></div>
                        @endif
                        @if($access['receivables'])
                            <h3 class="fs-5 fw-bold mb-3">Piutang Terbaru</h3>
                            <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Nomor</th><th>Sumber</th><th>Lokasi</th><th>Jatuh Tempo</th><th>Sisa</th><th>Status</th></tr></thead><tbody>
                            @forelse($receivables as $receivable)
                                <tr><td class="fw-bold">{{ $receivable->number }}</td><td>{{ $receivable->source_no ?: $receivable->source_type }}</td><td>{{ $receivable->workLocation?->name ?: 'Pusat' }}</td><td>{{ $receivable->due_date?->translatedFormat('d M Y') }}</td><td class="fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</td><td><x-metronic.status-badge :status="$receivable->status->value" :label="$receivable->status->label()" /></td></tr>
                            @empty
                                <tr><td colspan="6"><x-metronic.empty-state title="Belum ada piutang" description="Saldo piutang akan terbentuk dari transaksi kredit yang sudah diposting." icon="ki-outline ki-wallet" /></td></tr>
                            @endforelse
                            </tbody></table></div>
                        @endif
                    @endif
                </div>

                <div class="tab-pane fade" id="customer_tab_pembayaran" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Pembayaran Terbaru</h2><div class="text-muted">Pembayaran pelanggan dan status verifikasinya.</div></div>
                        @if(auth()->user()?->can('receivables.pay') || auth()->user()?->can('payments.create'))<a href="{{ route('receivables.payments.create', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Tambah Pembayaran</a>@endif
                    </div>
                    @if(!$access['payments'])
                        <x-metronic.empty-state title="Akses pembayaran tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat data pembayaran." icon="ki-outline ki-lock" />
                    @else
                        <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Pembayaran</th><th>Tanggal</th><th>Metode</th><th>Referensi</th><th>Nominal</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                        @forelse($payments as $payment)
                            <tr><td class="fw-bold">{{ $payment->number }}</td><td>{{ $payment->payment_date?->translatedFormat('d M Y') }}</td><td>{{ method_exists($payment->method, 'label') ? $payment->method->label() : ucfirst((string) $payment->method->value) }}</td><td>{{ $payment->reference_no ?: '—' }}</td><td class="fw-semibold">{{ App\Support\CurrencyFormatter::rupiah($payment->amount) }}</td><td><x-metronic.status-badge :status="$payment->status->value" :label="$payment->status->label()" /></td><td class="text-end">@can('verify', App\Models\Payment::class)<a href="{{ route('payments.verify', $payment) }}" class="btn btn-sm btn-light-primary">Periksa</a>@endcan</td></tr>
                        @empty
                            <tr><td colspan="7"><x-metronic.empty-state title="Belum ada pembayaran" description="Gunakan tombol Tambah Pembayaran untuk mencatat pembayaran pelanggan ini." icon="ki-outline ki-wallet" /></td></tr>
                        @endforelse
                        </tbody></table></div>
                    @endif
                </div>

                <div class="tab-pane fade" id="customer_tab_pengiriman" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Pengiriman Terbaru</h2><div class="text-muted">Status fulfillment, kurir, dan nomor resi pelanggan.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @if($access['shipments'])<a href="{{ route('shipments.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-eye fs-5 me-1"></i>Lihat Semua</a>@endif
                            @if($shippableOrder)<a href="{{ route('shipments.create', ['order_id' => $shippableOrder->id, 'customer_id' => $customer->id]) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-plus fs-5 me-1"></i>Buat Pengiriman</a>@endif
                        </div>
                    </div>
                    @if(!$access['shipments'])
                        <x-metronic.empty-state title="Akses pengiriman tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat pengiriman." icon="ki-outline ki-lock" />
                    @else
                        <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Shipment</th><th>Order</th><th>Jadwal</th><th>Kurir/Resi</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
                        @forelse($shipments as $shipment)
                            <tr><td class="fw-bold">{{ $shipment->number }}</td><td>{{ $shipment->order?->number }}</td><td>{{ $shipment->scheduled_date?->translatedFormat('d M Y') ?: '—' }}</td><td>{{ $shipment->courier_name ?: '—' }}<div class="text-muted fs-8">{{ $shipment->tracking_no ?: 'Belum ada resi' }}</div></td><td><x-metronic.status-badge :status="$shipment->status->value" :label="$shipment->status->label()" /></td><td class="text-end">@can('view', $shipment)<a href="{{ route('shipments.show', $shipment) }}" class="btn btn-sm btn-light-primary">Detail</a>@endcan</td></tr>
                        @empty
                            <tr><td colspan="6"><x-metronic.empty-state title="Belum ada pengiriman" description="Pengiriman dapat dibuat setelah order lolos validasi dan siap diproses." icon="ki-outline ki-delivery" /></td></tr>
                        @endforelse
                        </tbody></table></div>
                    @endif
                </div>

                <div class="tab-pane fade" id="customer_tab_akses" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Alamat & Akun Portal</h2><div class="text-muted">Lokasi pengiriman dan pengguna yang mewakili pelanggan.</div></div>
                        @can('manageAccess', $customer)<a href="{{ route('admin.customers.access.edit', $customer) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-pencil fs-5 me-1"></i>Kelola Alamat & Akun</a>@endcan
                    </div>
                    <div class="row g-6">
                        <div class="col-lg-6">
                            <h3 class="fs-5 fw-bold mb-4">Alamat Pengiriman <span class="badge badge-light-primary ms-2">{{ $customer->addresses->count() }}</span></h3>
                            @forelse($customer->addresses as $address)
                                <div class="border border-gray-300 rounded p-4 mb-3">
                                    <div class="d-flex justify-content-between gap-3 mb-2"><div class="fw-bold">{{ $address->label }}</div>@if($address->is_primary)<span class="badge badge-light-success">Utama</span>@endif</div>
                                    <div class="text-gray-800 mb-2">{{ $address->address }}</div>
                                    <div class="text-muted fs-7">{{ $address->city }}{{ $address->postal_code ? ' · '.$address->postal_code : '' }}</div>
                                    <div class="text-muted fs-7">{{ $address->recipient_name ?: 'Penerima belum diisi' }} · {{ $address->phone_number ?: 'Nomor belum diisi' }}</div>
                                </div>
                            @empty
                                <x-metronic.empty-state class="border rounded" title="Belum ada alamat kirim" description="Tambahkan alamat agar order dapat menentukan tujuan pengiriman." icon="ki-outline ki-geolocation" />
                            @endforelse
                        </div>
                        <div class="col-lg-6">
                            <h3 class="fs-5 fw-bold mb-4">Akun Portal B2B <span class="badge badge-light-primary ms-2">{{ $customer->users->count() }}</span></h3>
                            @forelse($customer->users as $user)
                                <div class="d-flex align-items-center gap-4 border border-gray-300 rounded p-4 mb-3">
                                    <div class="symbol symbol-45px"><span class="symbol-label bg-light-primary text-primary fw-bold">{{ strtoupper(mb_substr($user->name, 0, 1)) }}</span></div>
                                    <div class="flex-grow-1 min-w-0"><div class="fw-bold text-gray-900 text-break">{{ $user->name }}</div><div class="text-muted fs-7 text-break">{{ $user->email }}</div><div class="text-muted fs-8">{{ str_replace('_', ' ', ucfirst($user->pivot->role)) }}</div></div>
                                    <span class="badge badge-light-{{ $user->pivot->is_active ? 'success' : 'secondary' }}">{{ $user->pivot->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                            @empty
                                <x-metronic.empty-state class="border rounded" title="Belum ada akun portal" description="Tambahkan akun agar pelanggan dapat masuk dan membuat order B2B." icon="ki-outline ki-people" />
                            @endforelse
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="customer_tab_harga" role="tabpanel">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-5">
                        <div><h2 class="fs-3 fw-bold mb-1">Harga Khusus & Dokumen</h2><div class="text-muted">Kontrak harga produk dan kelengkapan verifikasi pelanggan.</div></div>
                        <div class="d-flex flex-wrap gap-2">
                            @if(auth()->user()?->can('prices.view'))<a href="{{ route('pricing.special-prices.index', ['customer_id' => $customer->id]) }}" class="btn btn-sm btn-light-primary"><i class="ki-outline ki-price-tag fs-5 me-1"></i>Kelola Harga Khusus</a>@endif
                            @can('manageSettings', $customer)<a href="{{ route('admin.customers.settings.edit', $customer) }}" class="btn btn-sm btn-primary"><i class="ki-outline ki-file-added fs-5 me-1"></i>Tambah Dokumen</a>@endcan
                        </div>
                    </div>
                    <div class="row g-6">
                        <div class="col-xl-7">
                            <h3 class="fs-5 fw-bold mb-4">Harga Khusus Terbaru</h3>
                            @if(!$access['pricing'])
                                <x-metronic.empty-state class="border rounded" title="Akses harga tidak tersedia" description="Akun Anda tidak memiliki permission untuk melihat harga khusus pelanggan." icon="ki-outline ki-lock" />
                            @else
                                <div class="table-responsive"><table class="table table-row-dashed align-middle mb-0"><thead><tr class="text-muted fs-7 text-uppercase"><th>Produk</th><th>Harga</th><th>Min. Qty</th><th>Periode</th><th>Status</th></tr></thead><tbody>
                                @forelse($priceOverrides as $override)
                                    <tr><td><div class="fw-bold">{{ $override->product?->name ?: 'Produk tidak tersedia' }}</div><div class="text-muted fs-8">{{ $override->product?->sku }}</div></td><td>{{ App\Support\CurrencyFormatter::rupiah($override->price) }}</td><td>{{ qty($override->minimum_qty) }}</td><td>{{ $override->starts_at?->translatedFormat('d M Y') ?: '—' }}<div class="text-muted fs-8">s.d. {{ $override->ends_at?->translatedFormat('d M Y') ?: 'tanpa batas' }}</div></td><td><x-metronic.status-badge :status="$override->status ?? ($override->is_active ? 'active' : 'inactive')" /></td></tr>
                                @empty
                                    <tr><td colspan="5"><x-metronic.empty-state title="Belum ada harga khusus" description="Pelanggan menggunakan kategori harga {{ $customer->price_category }} sampai harga khusus ditambahkan." icon="ki-outline ki-price-tag" /></td></tr>
                                @endforelse
                                </tbody></table></div>
                            @endif
                        </div>
                        <div class="col-xl-5">
                            <h3 class="fs-5 fw-bold mb-4">Dokumen Verifikasi</h3>
                            @forelse($customer->documents as $document)
                                <div class="d-flex align-items-center gap-4 border border-gray-300 rounded p-4 mb-3">
                                    <span class="symbol symbol-45px"><span class="symbol-label bg-light-info"><i class="ki-outline ki-document fs-2 text-info"></i></span></span>
                                    <div class="flex-grow-1 min-w-0"><div class="fw-bold text-gray-900 text-break">{{ $document->name }}</div><div class="text-muted fs-7 text-uppercase">{{ $document->type }}</div></div>
                                    <div class="text-end fs-8 {{ $document->expires_at?->isPast() ? 'text-danger' : 'text-muted' }}">{{ $document->expires_at ? 'Berlaku s.d. '.$document->expires_at->translatedFormat('d M Y') : 'Tanpa kedaluwarsa' }}</div>
                                </div>
                            @empty
                                <x-metronic.empty-state class="border rounded" title="Belum ada dokumen" description="Unggah dokumen pendukung dari halaman verifikasi pelanggan." icon="ki-outline ki-document" />
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
