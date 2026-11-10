@extends('layouts.metronic.app')

@section('title', 'Pengaturan Printer & Format Cetak - ' . config('app.name'))
@section('page_title', 'Pengaturan Printer')

@section('page_guide')
    <x-metronic.page-guide id="admin-settings-printer" title="Panduan Konfigurasi Printer & Format Cetak">
        <x-slot:function>
            <p>Halaman ini digunakan untuk mengonfigurasi profil printer (Thermal 80mm dan Dot Matrix Epson LX-310), ukuran kertas continuous form, serta kustomisasi elemen yang tampil pada struk, faktur, surat jalan, dan laporan.</p>
        </x-slot:function>
        <x-slot:parts>
            <ul>
                <li><strong>Profil Printer Termal:</strong> Menentukan lebar kertas struk kasir (standar 80mm).</li>
                <li><strong>Profil Dot Matrix (Epson LX-310):</strong> Menentukan ukuran kertas continuous form (Setengah Folio 9.5 x 5.5 inci atau Full Folio).</li>
                <li><strong>Elemen Cetak:</strong> Mengaktifkan/menonaktifkan logo, barcode barang, info kasir, dan kolom tanda tangan 3 pihak.</li>
                <li><strong>Uji Coba Cetak (Test Print):</strong> Tombol tes langsung untuk melihat hasil layout sebelum dipakai operasional kasir.</li>
            </ul>
        </x-slot:parts>
        <x-slot:impacts>
            <p>Pengaturan ini langsung memengaruhi layout cetakan struk kasir POS, faktur penjualan, surat jalan pengiriman, dan laporan harian di seluruh toko.</p>
        </x-slot:impacts>
        <x-slot:operation>
            <ol>
                <li>Pilih profil lebar termal dan ukuran kertas continuous form LX-310.</li>
                <li>Atur saklar (toggle) elemen apa saja yang ingin ditampilkan.</li>
                <li>Klik tombol <strong>Uji Coba Cetak</strong> untuk verifikasi hasil pada printer fisik.</li>
                <li>Klik <strong>Simpan Pengaturan Printer</strong> jika sudah sesuai.</li>
            </ol>
        </x-slot:operation>
        <x-slot:warnings>
            <div class="alert alert-warning mb-0">
                <i class="ki-outline ki-information-2 me-1"></i>
                Pada dialog cetak browser (Chrome/Edge), pastikan memilih <strong>Margin: None</strong> dan <strong>Hilangkan centang Headers &amp; Footers</strong> bawaan browser agar cetakan rapi dan pas.
            </div>
        </x-slot:warnings>
        <x-slot:example>
            <p>Struk kasir menggunakan Thermal 80mm dengan auto-print aktif; Faktur penjualan menggunakan continuous form Setengah Folio (9.5" x 5.5") pada Epson LX-310 dengan kolom tanda tangan 3 pihak.</p>
        </x-slot:example>
    </x-metronic.page-guide>
@endsection

@section('content')
<form method="POST" action="{{ route('admin.settings.printer.update') }}" novalidate>
    @csrf
    @method('PUT')

    <div class="row g-6">
        {{-- Kolom Kiri: Profil Perangkat & Kertas --}}
        <div class="col-lg-6 d-flex flex-column gap-6">
            {{-- Kartu 1: Profil Perangkat Printer --}}
            <x-metronic.card title="Profil Perangkat &amp; Ukuran Kertas" subtitle="Spesifikasi hardware kasir dan continuous form">
                <div class="d-flex flex-column gap-5">
                    {{-- Printer Termal --}}
                    <div class="border border-gray-200 rounded p-4 bg-light-subtle">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="symbol symbol-35px symbol-circle bg-light-primary">
                                    <span class="symbol-label"><i class="ki-outline ki-printer text-primary fs-3"></i></span>
                                </div>
                                <div>
                                    <h5 class="fw-bolder text-gray-900 mb-0">Printer Termal Kasir (Struk)</h5>
                                    <span class="text-muted fs-8">Untuk struk transaksi POS &amp; slip ringkasan harian</span>
                                </div>
                            </div>
                            <span class="badge badge-light-primary fw-bold">POS Kasir</span>
                        </div>

                        <x-metronic.form-group name="printer_thermal_width" label="Lebar Kertas Termal" required>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center p-3 {{ $settings['printer_thermal_width'] === '80mm' ? 'active' : '' }}">
                                        <input class="form-check-input me-3" type="radio" name="printer_thermal_width" value="80mm" @checked($settings['printer_thermal_width'] === '80mm')>
                                        <span class="d-block text-start">
                                            <span class="fw-bolder d-block text-gray-800 fs-7">80 mm (Rekomendasi)</span>
                                            <span class="text-muted fs-8">Area cetak ~72mm, 42-48 kolom</span>
                                        </span>
                                    </label>
                                </div>
                                <div class="col-6">
                                    <label class="btn btn-outline btn-outline-dashed btn-active-light-primary d-flex align-items-center p-3 {{ $settings['printer_thermal_width'] === '58mm' ? 'active' : '' }}">
                                        <input class="form-check-input me-3" type="radio" name="printer_thermal_width" value="58mm" @checked($settings['printer_thermal_width'] === '58mm')>
                                        <span class="d-block text-start">
                                            <span class="fw-bolder d-block text-gray-800 fs-7">58 mm (Kecil)</span>
                                            <span class="text-muted fs-8">Area cetak ~48mm, 32 kolom</span>
                                        </span>
                                    </label>
                                </div>
                            </div>
                        </x-metronic.form-group>

                        <div class="form-check form-switch form-check-custom form-check-solid mt-4">
                            <input class="form-check-input" type="checkbox" name="printer_auto_print_pos" id="printer_auto_print_pos" value="1" @checked($settings['printer_auto_print_pos'])>
                            <label class="form-check-label fw-bold text-gray-700 fs-7" for="printer_auto_print_pos">
                                Otomatis buka dialog cetak struk setelah pembayaran di POS selesai
                            </label>
                        </div>
                    </div>

                    {{-- Dot Matrix Epson LX-310 --}}
                    <div class="border border-gray-200 rounded p-4 bg-light-subtle">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="symbol symbol-35px symbol-circle bg-light-success">
                                    <span class="symbol-label"><i class="ki-outline ki-document text-success fs-3"></i></span>
                                </div>
                                <div>
                                    <h5 class="fw-bolder text-gray-900 mb-0">Epson LX-310 (Dot Matrix)</h5>
                                    <span class="text-muted fs-8">Kertas continuous form (Faktur, Surat Jalan, PO, Laporan)</span>
                                </div>
                            </div>
                            <span class="badge badge-light-success fw-bold">9-Pin / 80 Kolom</span>
                        </div>

                        <x-metronic.form-group name="printer_dotmatrix_paper" label="Ukuran Continuous Form Default (Faktur &amp; Surat Jalan)" required>
                            <div class="d-flex flex-column gap-2.5">
                                <label class="btn btn-outline btn-outline-dashed btn-active-light-success d-flex align-items-center p-3 {{ $settings['printer_dotmatrix_paper'] === 'half_letter' ? 'active' : '' }}">
                                    <input class="form-check-input me-3" type="radio" name="printer_dotmatrix_paper" value="half_letter" @checked($settings['printer_dotmatrix_paper'] === 'half_letter')>
                                    <span class="d-block text-start">
                                        <span class="fw-bolder d-block text-gray-800 fs-7">Setengah Folio / Half Letter (9.5" &times; 5.5" / Bagi 2) &mdash; <span class="text-success">Rekomendasi</span></span>
                                        <span class="text-muted fs-8">Kertas continuous form 1 ply / 2 ply / 3 ply standar faktur &amp; surat jalan ritel-grosir Indonesia</span>
                                    </span>
                                </label>

                                <label class="btn btn-outline btn-outline-dashed btn-active-light-success d-flex align-items-center p-3 {{ $settings['printer_dotmatrix_paper'] === 'folio' ? 'active' : '' }}">
                                    <input class="form-check-input me-3" type="radio" name="printer_dotmatrix_paper" value="folio" @checked($settings['printer_dotmatrix_paper'] === 'folio')>
                                    <span class="d-block text-start">
                                        <span class="fw-bolder d-block text-gray-800 fs-7">Full Folio / Kertas Penuh (9.5" &times; 11")</span>
                                        <span class="text-muted fs-8">Ukuran continuous penuh, ideal untuk laporan banyak item atau rekapitulasi harian</span>
                                    </span>
                                </label>

                                <label class="btn btn-outline btn-outline-dashed btn-active-light-success d-flex align-items-center p-3 {{ $settings['printer_dotmatrix_paper'] === 'a4' ? 'active' : '' }}">
                                    <input class="form-check-input me-3" type="radio" name="printer_dotmatrix_paper" value="a4" @checked($settings['printer_dotmatrix_paper'] === 'a4')>
                                    <span class="d-block text-start">
                                        <span class="fw-bolder d-block text-gray-800 fs-7">Standard A4 / Laser (210 &times; 297 mm)</span>
                                        <span class="text-muted fs-8">Kertas lembaran standar untuk kantor dan printer laser</span>
                                    </span>
                                </label>
                            </div>
                        </x-metronic.form-group>
                    </div>
                </div>
            </x-metronic.card>

            {{-- Kartu Uji Coba Cetak Langsung --}}
            <x-metronic.card title="Uji Coba Cetak (Test Print Langsung)" subtitle="Verifikasi tampilan pada printer fisik sebelum digunakan transaksi">
                <div class="d-flex flex-column gap-3">
                    <p class="text-muted fs-8 mb-2">
                        Klik tombol di bawah ini untuk membuka lembar tes cetak sampel. Pastikan printer Anda sudah tersambung dan menyala.
                    </p>
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-light-primary w-100 fw-bold d-flex align-items-center justify-content-center py-3" onclick="openTestPrint('thermal')">
                                <i class="ki-outline ki-receipt-square fs-3 me-2"></i>
                                <span>Tes Thermal (80mm)</span>
                            </button>
                        </div>
                        <div class="col-sm-6">
                            <button type="button" class="btn btn-light-success w-100 fw-bold d-flex align-items-center justify-content-center py-3" onclick="openTestPrint('dotmatrix')">
                                <i class="ki-outline ki-document fs-3 me-2"></i>
                                <span>Tes Dot Matrix (LX-310)</span>
                            </button>
                        </div>
                    </div>
                    <div class="notice d-flex bg-light-warning rounded border-warning border border-dashed p-3 mt-2">
                        <i class="ki-outline ki-information-5 fs-4 text-warning me-2 mt-0.5"></i>
                        <div class="text-gray-700 fs-8">
                            <strong>Tips Setelan Cetak Browser:</strong> Pada jendela cetak Chrome/Edge, klik <em>More settings</em>, set <strong>Margins = None</strong> atau Minimum, dan <strong>matikan Headers and footers</strong> agar tidak muncul URL/tanggal browser.
                        </div>
                    </div>
                </div>
            </x-metronic.card>
        </div>

        {{-- Kolom Kanan: Kustomisasi Komponen Dokumen (Full Kustom) --}}
        <div class="col-lg-6 d-flex flex-column gap-6">
            <x-metronic.card title="Kustomisasi Elemen Dokumen (Full Kustom)" subtitle="Aktifkan atau sembunyikan komponen sesuai kebutuhan bisnis">
                <div class="d-flex flex-column gap-4">
                    {{-- Toggle Saklar Kustomisasi --}}
                    <div class="d-flex flex-column gap-3 border border-gray-200 rounded p-4 bg-light-subtle">
                        <span class="text-gray-800 fw-bolder fs-7 mb-1 text-uppercase">Komponen Header &amp; Detail Transaksi:</span>

                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="print_show_store_header" id="print_show_store_header" value="1" @checked($settings['print_show_store_header'])>
                            <label class="form-check-label fw-semibold text-gray-700 fs-8" for="print_show_store_header">
                                Tampilkan Header Toko (Nama Perusahaan, Alamat, dan Nomor Telepon)
                            </label>
                        </div>

                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="print_show_logo" id="print_show_logo" value="1" @checked($settings['print_show_logo'])>
                            <label class="form-check-label fw-semibold text-gray-700 fs-8" for="print_show_logo">
                                Tampilkan Logo Perusahaan (Jika tersedia di Pengaturan Umum)
                            </label>
                        </div>

                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="print_show_cashier_name" id="print_show_cashier_name" value="1" @checked($settings['print_show_cashier_name'])>
                            <label class="form-check-label fw-semibold text-gray-700 fs-8" for="print_show_cashier_name">
                                Tampilkan Nama Kasir / Petugas pembuat transaksi
                            </label>
                        </div>

                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="print_show_customer_name" id="print_show_customer_name" value="1" @checked($settings['print_show_customer_name'])>
                            <label class="form-check-label fw-semibold text-gray-700 fs-8" for="print_show_customer_name">
                                Tampilkan Nama Pelanggan / Member (jika terdaftar)
                            </label>
                        </div>

                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="print_show_item_barcode" id="print_show_item_barcode" value="1" @checked($settings['print_show_item_barcode'])>
                            <label class="form-check-label fw-semibold text-gray-700 fs-8" for="print_show_item_barcode">
                                Tampilkan Kode SKU / Barcode di bawah nama produk
                            </label>
                        </div>

                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="print_show_payment_details" id="print_show_payment_details" value="1" @checked($settings['print_show_payment_details'])>
                            <label class="form-check-label fw-semibold text-gray-700 fs-8" for="print_show_payment_details">
                                Tampilkan Rincian Metode Pembayaran (Tunai, QRIS, Transfer, Uang Diterima &amp; Kembalian)
                            </label>
                        </div>

                        <div class="form-check form-switch form-check-custom form-check-solid">
                            <input class="form-check-input" type="checkbox" name="print_show_signatures" id="print_show_signatures" value="1" @checked($settings['print_show_signatures'])>
                            <label class="form-check-label fw-semibold text-gray-700 fs-8" for="print_show_signatures">
                                Tampilkan Kolom Tanda Tangan 3 Pihak pada Faktur &amp; Surat Jalan (Hormat Kami, Sopir/Pengirim, Penerima)
                            </label>
                        </div>
                    </div>

                    {{-- Pesan Kaki Struk --}}
                    <x-metronic.form-group name="print_receipt_footer_note" label="Catatan Kaki Struk Kasir (Thermal)" help="Teks kebijakan retur barang atau ucapan terima kasih pada struk belanja kasir.">
                        <textarea name="print_receipt_footer_note" rows="3" class="form-control form-control-sm @error('print_receipt_footer_note') is-invalid @enderror">{{ old('print_receipt_footer_note', $settings['print_receipt_footer_note']) }}</textarea>
                    </x-metronic.form-group>

                    {{-- Pesan Kaki Faktur --}}
                    <x-metronic.form-group name="print_invoice_footer_note" label="Catatan Kaki Faktur Penjualan (Dot Matrix LX-310)" help="Teks syarat pembayaran atau ketentuan garansi pada faktur continuous form.">
                        <textarea name="print_invoice_footer_note" rows="3" class="form-control form-control-sm @error('print_invoice_footer_note') is-invalid @enderror">{{ old('print_invoice_footer_note', $settings['print_invoice_footer_note']) }}</textarea>
                    </x-metronic.form-group>
                </div>
            </x-metronic.card>
        </div>
    </div>

    {{-- Tombol Simpan --}}
    <div class="d-flex justify-content-end mt-6">
        <button type="submit" class="btn btn-primary fw-bold px-6">
            <i class="ki-outline ki-check fs-4 me-1"></i> Simpan Pengaturan Printer
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script>
function openTestPrint(type) {
    const url = "{{ url('/admin/settings/printer/test') }}/" + type;
    const width = type === 'thermal' ? 420 : 850;
    const height = 650;
    const left = (screen.width - width) / 2;
    const top = (screen.height - height) / 2;
    window.open(url, 'TestPrintWindow', `width=${width},height=${height},top=${top},left=${left},scrollbars=yes`);
}
</script>
@endpush

