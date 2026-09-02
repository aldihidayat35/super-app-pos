@extends('layouts.metronic.app')

@section('title', 'Target & Bonus')
@section('page_title', 'Target & Bonus')
@section('content')
    <x-metronic.page-title title="Target & Bonus" description="Riwayat target dan bonus berdasarkan order B2B berstatus Selesai." />
    <x-metronic.card><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Periode</th><th>Target</th><th>Penjualan</th><th>Pencapaian</th><th>Bonus %</th><th>Bonus</th><th>Status Histori</th></tr></thead><tbody>
        @forelse($rows as $row)
            @php($target = $row['target']) @php($metrics = $row['metrics'])
            <tr><td class="fw-bold">{{ DateTime::createFromFormat('!m', $target->month)->format('F') }} {{ $target->year }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($metrics['target_amount']) }}</td><td>{{ App\Support\CurrencyFormatter::rupiah($metrics['sales_amount']) }}</td><td>{{ number_format((float) $metrics['achievement_percentage'], 2, ',', '.') }}%</td><td>{{ number_format((float) $metrics['bonus_percentage'], 2, ',', '.') }}%</td><td class="fw-bold">{{ App\Support\CurrencyFormatter::rupiah($metrics['bonus_amount']) }}</td><td>{{ $metrics['bonus']?->finalized_at ? 'Terkunci' : 'Berjalan' }}</td></tr>
        @empty<tr><td colspan="7"><x-metronic.empty-state title="Target belum ditetapkan" description="Admin belum membuat target untuk akun Anda." /></td></tr>@endforelse
    </tbody></table></div><div class="mt-4">{{ $targets->links() }}</div></x-metronic.card>
@endsection
