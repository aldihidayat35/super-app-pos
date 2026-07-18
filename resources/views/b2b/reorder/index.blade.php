@extends('layouts.metronic.app')

@section('title', 'Reorder Cepat')
@section('page_title', 'Reorder Cepat')

@section('content')
    <x-metronic.page-title title="Favorit dan Reorder Cepat" description="Ambil dari produk yang pernah dibeli. Harga akan dihitung ulang saat masuk keranjang." />
    <form method="POST" action="{{ route('langganan.reorder.store') }}">
        @csrf
        <x-metronic.card title="Produk Sering Dibeli">
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Produk</th><th>Pernah Dibeli</th><th>Terakhir</th><th class="w-175px">Qty Baru</th></tr></thead><tbody>
                @forelse($items as $index => $item)
                    <tr><td><input type="hidden" name="items[{{ $index }}][product_id]" value="{{ $item->product_id }}"><input type="hidden" name="items[{{ $index }}][unit_id]" value="{{ $item->base_unit_id }}"><div class="fw-bold">{{ $item->name }}</div><div class="text-muted">{{ $item->sku }}</div></td><td>{{ $item->order_count }}x · {{ qty($item->total_quantity) }}</td><td>{{ $item->last_ordered_at ? \Illuminate\Support\Carbon::parse($item->last_ordered_at)->format('d/m/Y') : '-' }}</td><td><input type="number" step="1" min="1" name="items[{{ $index }}][quantity]" value="1" class="form-control"></td></tr>
                @empty
                    <tr><td colspan="4"><x-metronic.empty-state title="Belum ada riwayat produk" description="Setelah order pertama, produk akan muncul untuk reorder cepat." /></td></tr>
                @endforelse
            </tbody></table></div>
            @if($items->isNotEmpty())@slot('footer')<button class="btn btn-primary">Tambahkan ke Keranjang</button>@endslot@endif
        </x-metronic.card>
    </form>
@endsection
