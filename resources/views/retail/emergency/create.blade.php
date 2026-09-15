@extends('layouts.metronic.app')

@section('title', 'Pembelian dan Restok Darurat')
@section('content')
    <x-metronic.page-title title="Pembelian dan Restok Darurat" description="Penuhi kebutuhan pelanggan atau siapkan stok darurat toko sebelum terjadi permintaan." />
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <x-metronic.card title="Kebutuhan toko">
        <form method="POST" action="{{ route('retail.emergency.store') }}" class="row g-4">
            @csrf
            <input type="hidden" name="confirmation_key" value="{{ old('confirmation_key', (string) \Illuminate\Support\Str::uuid()) }}">
            <div class="col-md-6"><label class="form-label" for="purpose">Jenis kebutuhan</label><select id="purpose" name="purpose" class="form-select" required>
                <option value="customer_request" @selected(old('purpose', $purpose) === 'customer_request')>Permintaan pelanggan</option>
                <option value="proactive_restock" @selected(old('purpose', $purpose) === 'proactive_restock')>Restok darurat toko</option>
            </select><div class="form-text">Restok toko langsung masuk pool darurat setelah pembelian dicatat.</div></div>
            <div class="col-md-6"><label class="form-label" for="branch_id">Toko</label><select id="branch_id" name="branch_id" class="form-select" required>
                @foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $branchId) == $branch->id)>{{ $branch->name }}</option>@endforeach
            </select></div>
            <div class="col-md-6" id="customer-field"><label class="form-label" for="customer_id">Pelanggan (opsional)</label><select id="customer_id" name="customer_id" class="form-select"><option value="">Pelanggan umum</option>
                @foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->business_name }}</option>@endforeach
            </select></div>
            <div class="col-12"><div class="table-responsive"><table class="table align-middle emergency-entry-table" data-mobile-table="off"><thead><tr><th>Produk</th><th style="min-width:110px">Qty kebutuhan</th><th style="min-width:150px">Estimasi biaya per satuan dasar</th><th style="min-width:180px">Pratinjau</th></tr></thead><tbody id="emergency-items">
                <tr><td data-label="Produk"><select name="items[0][product_id]" class="form-select emergency-product" required><option value="">Pilih produk</option>
                    @foreach($products as $product)<option value="{{ $product->id }}" @selected(old('items.0.product_id', $productId) == $product->id)>{{ $product->sku }} — {{ $product->name }} ({{ $product->baseUnit?->name }})</option>@endforeach
                </select></td><td data-label="Qty jual"><input name="items[0][quantity]" type="number" step="0.0001" min="0.0001" value="{{ old('items.0.quantity', 1) }}" required class="form-control emergency-qty"></td>
                <td data-label="Estimasi biaya per satuan dasar"><input name="items[0][unit_cost]" type="number" step="0.01" min="0.01" value="{{ old('items.0.unit_cost') }}" required class="form-control emergency-cost"></td>
                <td data-label="Pratinjau" class="emergency-margin text-muted">Isi produk dan biaya</td></tr>
            </tbody></table></div><button type="button" class="btn btn-light btn-sm" id="add-emergency-item">Tambah produk</button></div>
            <div class="col-12"><label class="form-label" for="notes">Catatan kebutuhan</label><textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea></div>
            <div class="col-12 text-muted fs-7">Stok reguler dan pool darurat diperiksa ulang saat konfirmasi. Restok proaktif tidak membuat kejadian stockout.</div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" id="submit-label">Ajukan pembelian darurat</button><a class="btn btn-light" href="{{ route('retail.emergency.index') }}">Batal</a></div>
        </form>
    </x-metronic.card>
@endsection

@push('styles')
<style>
@media (max-width: 767.98px) {
    .emergency-entry-table, .emergency-entry-table tbody, .emergency-entry-table tr, .emergency-entry-table td { display: block; width: 100%; }
    .emergency-entry-table thead { display: none; }
    .emergency-entry-table tr { border: 1px solid #e4e6ef; border-radius: .5rem; padding: .75rem; margin-bottom: .75rem; }
    .emergency-entry-table td { border: 0; padding: .35rem 0; }
    .emergency-entry-table td::before { content: attr(data-label); display: block; font-weight: 600; margin-bottom: .35rem; color: #3f4254; }
}
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.getElementById('emergency-items');
    const previewUrl = @json(route('retail.emergency.preview'));
    const purpose = document.getElementById('purpose');
    const customer = document.getElementById('customer_id');
    const syncPurpose = () => {
        const proactive = purpose.value === 'proactive_restock';
        document.getElementById('customer-field').classList.toggle('d-none', proactive);
        customer.disabled = proactive;
        document.getElementById('submit-label').textContent = proactive ? 'Ajukan restok darurat toko' : 'Konfirmasi kekurangan dan ajukan';
    };
    const refresh = () => body.querySelectorAll('tr').forEach(row => {
        const quote = row.emergencyQuote;
        const qty = Number(row.querySelector('.emergency-qty').value || 0);
        const cost = Number(row.querySelector('.emergency-cost').value || 0);
        const requestedBase = quote ? qty * Number(quote.unit_factor) : 0;
        const target = purpose.value === 'proactive_restock' ? requestedBase : Math.max(0, requestedBase - Number(quote?.sellable_stock_base || 0));
        const margin = quote && target > 0 ? qty * Number(quote.discounted_price) * target / requestedBase - target * cost : null;
        const stock = quote ? `Reguler ${Number(quote.regular_stock_base).toLocaleString('id-ID')} · Darurat ${Number(quote.emergency_stock_base).toLocaleString('id-ID')}` : '';
        row.querySelector('.emergency-margin').textContent = margin === null ? (quote ? `${stock} · Kebutuhan sudah tercukupi` : 'Pilih produk') : `${stock} · Est. margin ${new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR'}).format(margin)}`;
    });
    let timer;
    const load = () => {
        clearTimeout(timer);
        timer = setTimeout(() => body.querySelectorAll('tr').forEach(async row => {
            row.emergencyQuote = null;
            const productId = row.querySelector('.emergency-product').value;
            if (!productId) { refresh(); return; }
            const params = new URLSearchParams({purpose:purpose.value, branch_id:document.getElementById('branch_id').value, product_id:productId, quantity:row.querySelector('.emergency-qty').value || '1'});
            const customerId = document.getElementById('customer_id').value;
            if (customerId) params.set('customer_id', customerId);
            try {
                const response = await fetch(`${previewUrl}?${params}`, {credentials:'same-origin'});
                if (!response.ok) throw new Error('Pratinjau gagal');
                row.emergencyQuote = await response.json(); refresh();
            } catch (_) { row.querySelector('.emergency-margin').textContent = 'Pratinjau belum tersedia'; }
        }), 250);
    };
    body.addEventListener('input', event => event.target.matches('.emergency-cost') ? refresh() : load());
    body.addEventListener('change', load);
    document.getElementById('branch_id').addEventListener('change', load);
    document.getElementById('customer_id').addEventListener('change', load);
    purpose.addEventListener('change', () => { syncPurpose(); load(); });
    document.getElementById('add-emergency-item').addEventListener('click', () => {
        const row = body.querySelector('tr').cloneNode(true), index = body.children.length;
        row.querySelectorAll('[name]').forEach(input => { input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`); input.value = ''; });
        row.querySelector('.emergency-qty').value = 1; row.emergencyQuote = null; body.appendChild(row); load();
    });
    syncPurpose(); load();
});
</script>
@endpush
