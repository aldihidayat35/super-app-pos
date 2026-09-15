use App\Support\CurrencyFormatter;
use App\Enums\CashShiftStatus;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Shift #{{ $shift->number }}</title>
    <style>
        @page { margin: 15mm; }
        body { font-family: 'Helvetica', sans-serif; font-size: 10pt; color: #333; margin: 0; padding: 0; }
        
        .header { text-align: center; border-bottom: 2px solid #2c3e50; padding-bottom: 10px; margin-bottom: 15px; }
        .header h1 { margin: 0; font-size: 16pt; color: #2c3e50; }
        .header p { margin: 3px 0 0; font-size: 9pt; color: #666; }
        
        .info-grid { display: table; width: 100%; margin-bottom: 15px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 4px 8px; font-size: 9pt; border-bottom: 1px solid #eee; }
        .info-label { font-weight: bold; width: 140px; color: #555; }
        
        .summary-table { width: 100%; margin-bottom: 15px; border-collapse: collapse; }
        .summary-table th { background: #2c3e50; color: #fff; padding: 6px 10px; text-align: left; font-size: 8pt; }
        .summary-table td { padding: 5px 10px; border-bottom: 1px solid #ddd; font-size: 9pt; }
        .summary-table tr:nth-child(even) { background: #f9f9f9; }
        .summary-table .total-row { background: #e8f4fd !important; font-weight: bold; }
        
        .section-title { background: #34495e; color: #fff; padding: 4px 10px; font-size: 9pt; font-weight: bold; margin: 15px 0 8px; }
        
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .data-table th { background: #ecf0f1; padding: 5px 8px; text-align: left; font-size: 8pt; border: 1px solid #bdc3c7; }
        .data-table td { padding: 5px 8px; font-size: 8pt; border: 1px solid #ddd; }
        .data-table tr:nth-child(even) { background: #fafafa; }
        
        .amount { text-align: right; font-family: 'Courier New', monospace; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; font-size: 7pt; font-weight: bold; }
        .badge-open { background: #d4edda; color: #155724; }
        .badge-closed { background: #cce5ff; color: #004085; }
        .badge-approved { background: #d4edda; color: #155724; }
        
        .footer { margin-top: 20px; text-align: center; font-size: 7pt; color: #999; border-top: 1px solid #eee; padding-top: 10px; }
        
        .proof-img { max-width: 50px; max-height: 40px; object-fit: cover; border-radius: 3px; border: 1px solid #ddd; }
        
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>LAPORAN SHIFT KASIR</h1>
        <p>Shift #{{ $shift->number }} &mdash; {{ $shift->started_at->format('d M Y') }}</p>
        <p>{{ config('app.name') }} &mdash; {{ date('d M Y H:i') }} WIB</p>
    </div>

    <table class="info-grid">
        <tr><td class="info-label">Kasir</td><td class="info-value">{{ $shift->cashier?->name ?? '-' }}</td>
            <td class="info-label">شعبه</td><td class="info-value">{{ $shift->branch?->name ?? '-' }}</td></tr>
        <tr><td class="info-label">آغاز</td><td class="info-value">{{ $shift->opened_at?->format('d/m/Y H:i') ?? '-' }}</td>
            <td class="info-label">پایان</td><td class="info-value">{{ $shift->closed_at?->format('d/m/Y H:i') ?? 'باز' }}</td></tr>
        <tr><td class="info-label">موجودی اولیه</td><td class="info-value amount">{{ App\Support\CurrencyFormatter::rupiah($shift->opening_cash_amount) }}</td>
            <td class="info-label">وضعیت</td><td class="info-value">
                @if($shift->status == \App\Enums\CashShiftStatus::CLOSED)
                    <span class="badge badge-closed">بسته شده</span>
                @elseif($shift->status == \App\Enums\CashShiftStatus::OPEN)
                    <span class="badge badge-open">باز</span>
                @else
                    <span class="badge badge-approved">تایید شده</span>
                @endif
            </td></tr>
    </table>

    <div class="section-title">RINGKASAN KEUANGAN</div>
    <table class="summary-table">
        <tr>
            <th>Uang Awal</th>
            <th class="amount">Penjualan Tunai</th>
            <th class="amount">Penjualan Non-Tunai</th>
            <th class="amount">Total Pengeluaran</th>
            <th class="amount">Kas yang Diharapkan</th>
            <th class="amount">Selisih</th>
        </tr>
        <tr>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($shift->opening_cash_amount) }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($summary['cash_sales']) }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($summary['non_cash_sales']) }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($summary['expenses']) }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($summary['expected_cash']) }}</td>
            <td class="amount" style="color: {{ $summary['difference'] >= 0 ? '#155724' : '#721c24' }}">
                {{ App\Support\CurrencyFormatter::rupiah($summary['difference']) }}
            </td>
        </tr>
    </table>

    @if($shift->sales && $shift->sales->count() > 0)
    <div class="section-title">DAFTAR TRANSAKSI PENJUALAN ({{ $shift->sales->count() }} transaksi)</div>
    <table class="data-table">
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>No. Invoice</th>
            <th class="amount">Subtotal</th>
            <th class="amount">Diskon</th>
            <th class="amount">Pajak</th>
            <th class="amount">Total</th>
            <th class="text-center">Status</th>
        </tr>
        @foreach($shift->sales as $i => $sale)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $sale->completed_at ? $sale->completed_at->format('d/m/Y H:i') : '-' }}</td>
            <td>{{ $sale->invoice_no ?? $sale->id }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($sale->subtotal) }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($sale->discount) }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($sale->tax) }}</td>
            <td class="amount"><strong>{{ App\Support\CurrencyFormatter::rupiah($sale->total) }}</strong></td>
            <td class="text-center">{{ ucfirst($sale->status) }}</td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3" class="text-right"><strong>TOTAL</strong></td>
            <td class="amount"><strong>{{ App\Support\CurrencyFormatter::rupiah($shift->sales->sum('subtotal')) }}</strong></td>
            <td class="amount"><strong>{{ App\Support\CurrencyFormatter::rupiah($shift->sales->sum('discount')) }}</strong></td>
            <td class="amount"><strong>{{ App\Support\CurrencyFormatter::rupiah($shift->sales->sum('tax')) }}</strong></td>
            <td class="amount"><strong>{{ App\Support\CurrencyFormatter::rupiah($shift->sales->sum('total')) }}</strong></td>
            <td></td>
        </tr>
    </table>
    @endif

    @if($shift->expenses && $shift->expenses->count() > 0)
    <div class="section-title">DAFTAR PENGELUARAN ({{ $shift->expenses->count() }} item)</div>
    <table class="data-table">
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Kategori</th>
            <th class="amount">Jumlah</th>
            <th>Catatan</th>
            <th class="text-center">Bukti</th>
        </tr>
        @foreach($shift->expenses as $i => $expense)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $expense->spent_at->format('d/m/Y H:i') }}</td>
            <td>{{ $expense->category ?? '-' }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($expense->amount) }}</td>
            <td>{{ $expense->notes ?? '-' }}</td>
            <td class="text-center">
                @if($expense->proof_path)
                    <img src="{{ Storage::url($expense->proof_path) }}" class="proof-img">
                @else
                    -
                @endif
            </td>
        </tr>
        @endforeach
        <tr class="total-row">
            <td colspan="3" class="text-right"><strong>TOTAL PENGELUARAN</strong></td>
            <td class="amount"><strong>{{ App\Support\CurrencyFormatter::rupiah($shift->expenses->sum('amount')) }}</strong></td>
            <td colspan="2"></td>
        </tr>
    </table>
    @endif

    @if($shift->cashCounts && $shift->cashCounts->count() > 0)
    <div class="section-title">PENGGABUNGAN UANG TUNAI</div>
    <table class="data-table">
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Dihitung Oleh</th>
            <th class="amount">Total Uang</th>
        </tr>
        @foreach($shift->cashCounts as $i => $cc)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $cc->counted_at->format('d/m/Y H:i') }}</td>
            <td>{{ $cc->counted_by_name ?? '-' }}</td>
            <td class="amount">{{ App\Support\CurrencyFormatter::rupiah($cc->total_amount) }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    @if($shift->approvals && $shift->approvals->count() > 0)
    <div class="section-title">RIWAYAT PERSETUJUAN</div>
    <table class="data-table">
        <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Disetujui Oleh</th>
            <th>Catatan</th>
        </tr>
        @foreach($shift->approvals as $i => $approval)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $approval->approved_at->format('d/m/Y H:i') }}</td>
            <td>{{ $approval->actor->name ?? '-' }}</td>
            <td>{{ $approval->notes ?? '-' }}</td>
        </tr>
        @endforeach
    </table>
    @endif

    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh sistem &mdash; {{ config('app.name') }} &copy; {{ date('Y') }}</p>
    </div>
</body>
</html>
