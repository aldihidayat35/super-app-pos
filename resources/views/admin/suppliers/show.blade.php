@extends('layouts.metronic.app')
@section('title', $supplier->name)
@section('page_title', 'Detail Supplier')
@section('toolbar_actions')
    @can('update', $supplier)<a href="{{ route('admin.suppliers.edit', $supplier) }}" class="btn btn-primary">Edit Supplier</a>@endcan
@endsection
@section('content')
<div class="row g-6">
    <div class="col-lg-4"><x-metronic.card title="Profil Supplier"><div class="fw-bold fs-4">{{ $supplier->name }}</div><div class="text-muted mb-4">{{ $supplier->code }}</div><div>PIC: {{ $supplier->contact_name ?: '-' }}</div><div>WA: {{ $supplier->whatsapp_number ?: '-' }}</div><div>Email: {{ $supplier->email ?: '-' }}</div><div>Kota: {{ $supplier->city ?: '-' }}</div><div>Termin: {{ $supplier->payment_term_days }} hari</div><div>NPWP: {{ $supplier->tax_number ?: '-' }}</div><div class="mt-3"><x-metronic.status-badge :status="$supplier->is_active ? 'active' : 'inactive'" :label="$supplier->is_active ? 'Aktif' : 'Nonaktif'" /></div></x-metronic.card></div>
    <div class="col-lg-8">
        <ul class="nav nav-tabs nav-line-tabs mb-5 fs-6">
            @foreach(['produk'=>'Produk/Harga Terakhir','po'=>'PO','penerimaan'=>'Penerimaan','retur'=>'Retur','performa'=>'Performa','dokumen'=>'Dokumen'] as $key => $label)
                <li class="nav-item"><a class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" href="#tab_{{ $key }}">{{ $label }}</a></li>
            @endforeach
        </ul>
        <div class="tab-content">
            <div class="tab-pane fade show active" id="tab_produk"><x-metronic.card><table class="table"><thead><tr><th>Produk</th><th>Harga Terakhir</th><th>Terakhir Supply</th></tr></thead><tbody>@forelse($supplier->productsSupplied as $item)<tr><td>{{ $item->product?->name }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($item->last_price) }}</td><td>{{ $item->last_supplied_at?->format('d/m/Y') ?: '-' }}</td></tr>@empty<tr><td colspan="3"><x-metronic.empty-state title="Belum ada produk supplier" description="Relasi produk supplier akan terisi dari pembelian/penerimaan atau input manual fase berikutnya." /></td></tr>@endforelse</tbody></table></x-metronic.card></div>
            <div class="tab-pane fade" id="tab_dokumen"><x-metronic.card>@forelse($supplier->documents as $document)<div>{{ $document->name }} · {{ $document->type }}</div>@empty<x-metronic.empty-state title="Belum ada dokumen" description="Dokumen supplier dapat ditambahkan pada fase dokumen lanjutan." />@endforelse</x-metronic.card></div>
            @foreach(['po'=>'Purchase Order supplier akan tampil setelah modul pembelian aktif.','penerimaan'=>'Penerimaan barang supplier akan tampil setelah goods receipt aktif.','retur'=>'Retur supplier akan tampil setelah modul retur aktif.','performa'=>'Performa akan dihitung dari lead time, retur, dan ketepatan harga.'] as $key => $text)
                <div class="tab-pane fade" id="tab_{{ $key }}"><x-metronic.card><x-metronic.empty-state title="{{ $text }}" description="Tab disiapkan untuk fase transaksi berikutnya." /></x-metronic.card></div>
            @endforeach
        </div>
    </div>
</div>
@endsection
