@extends('layouts.metronic.app')

@section('title', 'Keranjang')
@section('page_title', 'Keranjang')

@section('content')
    <x-metronic.page-title title="Keranjang" description="Harga selalu disegarkan sebelum checkout.">
        <a href="{{ route('langganan.katalog.index') }}" class="btn btn-light">Tambah Produk</a>
    </x-metronic.page-title>
    <form method="POST" action="{{ route('langganan.keranjang.update') }}">
        @csrf @method('PUT')
        <x-metronic.card title="Item Keranjang">
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produk</th><th>Satuan</th><th class="w-150px">Qty</th><th>Harga</th><th>Subtotal</th><th>Stok</th><th></th></tr></thead><tbody>
                @forelse($cart->items as $item)
                    <tr>
                        <td><input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}"><div class="fw-bold">{{ $item->product->name }}</div><div class="text-muted">{{ $item->product->sku }}</div><input name="items[{{ $loop->index }}][notes]" value="{{ $item->notes }}" class="form-control form-control-sm mt-2" placeholder="Catatan item"></td>
                        <td>{{ $item->unit->name }}</td>
                        <td><input name="items[{{ $loop->index }}][quantity]" type="number" min="0" step="1" value="{{ qty_input($item->quantity) }}" class="form-control"></td>
                        <td>{{ App\Support\CurrencyFormatter::rupiah($item->price_snapshot) }}</td>
                        <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($item->line_total) }}</td>
                        <td><span class="badge badge-light-{{ $item->availability_snapshot === 'available' ? 'success' : ($item->availability_snapshot === 'limited' ? 'warning' : 'danger') }}">{{ $item->availability_snapshot === 'available' ? 'Tersedia' : ($item->availability_snapshot === 'limited' ? 'Terbatas' : 'Kosong') }}</span></td>
                        <td class="text-end"><button form="delete-item-{{ $item->id }}" class="btn btn-sm btn-light-danger">Hapus</button></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-metronic.empty-state title="Keranjang kosong" description="Tambahkan produk dari katalog langganan." /></td></tr>
                @endforelse
            </tbody></table></div>
            @slot('footer')<div class="d-flex justify-content-between align-items-center"><button class="btn btn-light-primary">Update Keranjang</button><div class="fs-4 fw-bold">Total: {{ App\Support\CurrencyFormatter::rupiah($totals['grand_total']) }}</div></div>@endslot
        </x-metronic.card>
    </form>
    @foreach($cart->items as $item)<form id="delete-item-{{ $item->id }}" method="POST" action="{{ route('langganan.keranjang.items.destroy', $item) }}">@csrf @method('DELETE')</form>@endforeach
    @if($cart->items->isNotEmpty())
        <x-metronic.card title="Lanjut Checkout" class="mt-5">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <div class="fw-bold">Total sementara: {{ App\Support\CurrencyFormatter::rupiah($totals['grand_total']) }}</div>
                    <div class="text-muted">Alamat, metode pengiriman, pembayaran, dan persetujuan syarat diisi pada halaman checkout.</div>
                </div>
                <a href="{{ route('langganan.checkout.show') }}" class="btn btn-primary">Lanjut Checkout</a>
            </div>
        </x-metronic.card>
    @endif
@endsection
