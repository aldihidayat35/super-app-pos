@extends('layouts.metronic.app')

@section('title', 'Reminder Piutang')
@section('page_title', 'Reminder Piutang')

@section('content')
    <x-metronic.page-title title="Reminder dan Penagihan" description="AR-06 daftar jatuh tempo, overdue, dan catatan follow-up." />

    <div class="row g-5">
        <div class="col-lg-8">
            <x-metronic.card title="Piutang Perlu Follow-up">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead><tr><th>Pelanggan</th><th>Nomor</th><th>Jatuh Tempo</th><th>Outstanding</th><th>Reminder Terakhir</th></tr></thead>
                        <tbody>
                        @forelse($receivables as $receivable)
                            <tr>
                                <td>{{ $receivable->customer?->business_name }}</td>
                                <td>{{ $receivable->number }}</td>
                                <td>{{ $receivable->due_date?->format('d/m/Y') }}</td>
                                <td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($receivable->outstanding_amount) }}</td>
                                <td>{{ $receivable->collectionNotes->last()?->note ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-metronic.empty-state title="Tidak ada reminder aktif" description="Tidak ada piutang jatuh tempo dalam tiga hari ke depan." /></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                {{ $receivables->links() }}
            </x-metronic.card>
        </div>
        <div class="col-lg-4">
            <x-metronic.card title="Tambah Catatan">
                <form method="POST" action="{{ route('receivables.reminders.store') }}" class="vstack gap-3">
                    @csrf
                    <input name="customer_id" class="form-control" placeholder="ID Pelanggan">
                    <input name="receivable_id" class="form-control" placeholder="ID Piutang opsional">
                    <select name="channel" class="form-select"><option value="wa">WhatsApp</option><option value="phone">Telepon</option><option value="email">Email</option><option value="manual">Manual</option></select>
                    <input name="contact_person" class="form-control" placeholder="Kontak">
                    <textarea name="note" class="form-control" rows="4" placeholder="Catatan penagihan"></textarea>
                    <input type="date" name="next_follow_up_date" class="form-control">
                    <button class="btn btn-primary">Simpan Reminder</button>
                </form>
            </x-metronic.card>
        </div>
    </div>
@endsection
