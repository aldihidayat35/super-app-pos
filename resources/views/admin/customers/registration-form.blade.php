<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Formulir Pendaftaran Pelanggan</title>
    <style>
        @page {
            size: A4;
            margin: 14mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            color: #111827;
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.35;
        }

        .toolbar {
            margin: 0 0 12px;
            text-align: right;
        }

        .toolbar button {
            border: 1px solid #1f2937;
            background: #ffffff;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
        }

        .header {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            border-bottom: 2px solid #111827;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .logo {
            width: 68px;
            height: 68px;
            object-fit: contain;
            border: 1px solid #d1d5db;
            padding: 4px;
        }

        .company {
            flex: 1;
        }

        h1 {
            margin: 0 0 4px;
            font-size: 20px;
            text-transform: uppercase;
        }

        h2 {
            margin: 14px 0 8px;
            padding: 6px 8px;
            border: 1px solid #111827;
            background: #f3f4f6;
            font-size: 13px;
            text-transform: uppercase;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 14px;
        }

        .field {
            break-inside: avoid;
        }

        .label {
            margin-bottom: 3px;
            font-weight: 700;
        }

        .line {
            min-height: 24px;
            border-bottom: 1px solid #111827;
        }

        .box {
            min-height: 58px;
            border: 1px solid #111827;
            padding: 6px;
        }

        .help {
            margin-top: 2px;
            color: #4b5563;
            font-size: 10px;
        }

        .checks {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 6px 12px;
            margin-bottom: 8px;
        }

        .check {
            min-height: 22px;
        }

        .signature-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .signature {
            min-height: 112px;
            border: 1px solid #111827;
            padding: 8px;
        }

        .signature-space {
            height: 46px;
        }

        .section {
            break-inside: avoid;
        }

        @media print {
            .toolbar {
                display: none;
            }

            body {
                color: #000000;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak</button>
    </div>

    <div class="header">
        @if($company['logo_url'])
            <img src="{{ $company['logo_url'] }}" class="logo" alt="Logo">
        @endif
        <div class="company">
            <h1>{{ $company['company_name'] }}</h1>
            <div>{{ $company['company_address'] ?: 'Alamat perusahaan belum diatur' }}</div>
            <div>
                Telp: {{ $company['company_phone'] ?: '-' }}
                @if($company['company_email'])
                    | Email: {{ $company['company_email'] }}
                @endif
            </div>
            <strong>Formulir Pendaftaran Pelanggan</strong>
        </div>
    </div>

    <div class="section">
        <h2>Identitas Usaha</h2>
        <div class="grid">
            <div class="field">
                <div class="label">Tipe Pelanggan</div>
                <div class="line">
                    @foreach($types as $label)
                        [ ] {{ $label }}&nbsp;&nbsp;
                    @endforeach
                </div>
            </div>
            <div class="field"><div class="label">Nama Usaha / Nama Pelanggan</div><div class="line"></div></div>
            <div class="field"><div class="label">Nama Pemilik</div><div class="line"></div></div>
            <div class="field"><div class="label">Nama PIC / Penanggung Jawab</div><div class="line"></div><div class="help">Orang yang menjadi kontak utama pelanggan.</div></div>
            <div class="field"><div class="label">Nomor WhatsApp</div><div class="line"></div></div>
            <div class="field"><div class="label">Email</div><div class="line"></div></div>
            <div class="field"><div class="label">Kota / Kabupaten</div><div class="line"></div></div>
            <div class="field"><div class="label">Provinsi</div><div class="line"></div></div>
            <div class="field"><div class="label">Kode Pos</div><div class="line"></div></div>
        </div>
        <div class="field" style="margin-top: 8px;">
            <div class="label">Alamat</div>
            <div class="box"></div>
        </div>
    </div>

    <div class="section">
        <h2>Informasi Komersial</h2>
        <div class="grid">
            <div class="field">
                <div class="label">Kategori / Ring Harga</div>
                <div class="line">
                    @foreach($priceCategories as $label)
                        [ ] {{ $label }}&nbsp;&nbsp;
                    @endforeach
                </div>
                <div class="help">Kategori harga yang akan digunakan untuk menentukan harga jual pelanggan.</div>
            </div>
            <div class="field">
                <div class="label">Tempo Pembayaran</div>
                <div class="line"></div>
                <div class="help">Jumlah hari yang diberikan kepada pelanggan untuk membayar tagihan.</div>
            </div>
            <div class="field">
                <div class="label">Batas Maksimum Kredit</div>
                <div class="line">Rp</div>
                <div class="help">Jumlah maksimum utang/piutang pelanggan yang diperbolehkan sistem.</div>
            </div>
            <div class="field"><div class="label">Minimum Pesanan</div><div class="line">Rp</div></div>
        </div>
        <div class="field" style="margin-top: 8px;">
            <div class="label">Catatan</div>
            <div class="box"></div>
        </div>
    </div>

    <div class="section">
        <h2>Checklist Dokumen Usaha</h2>
        <div class="checks">
            <div class="check">[ ] NIB</div>
            <div class="check">[ ] NPWP</div>
            <div class="check">[ ] KTP Pemilik / PIC</div>
            <div class="check">[ ] Akta Usaha</div>
            <div class="check">[ ] Izin Usaha</div>
            <div class="check">[ ] Dokumen lainnya: __________________</div>
        </div>
        <div class="grid">
            <div class="field"><div class="label">Nomor Dokumen</div><div class="line"></div></div>
            <div class="field"><div class="label">Masa Berlaku</div><div class="line"></div></div>
        </div>
        <div class="field" style="margin-top: 8px;">
            <div class="label">Catatan Dokumen</div>
            <div class="box"></div>
        </div>
    </div>

    <div class="section">
        <h2>Persetujuan</h2>
        <div class="signature-grid">
            <div class="signature">
                <strong>Pemohon</strong>
                <div class="signature-space"></div>
                <div>Nama: ______________________________</div>
                <div>Jabatan: ___________________________</div>
                <div>Tanggal: ___________________________</div>
                <div>Tanda Tangan: ______________________</div>
            </div>
            <div class="signature">
                <strong>Penerima / Admin</strong>
                <div class="signature-space"></div>
                <div>Nama: ______________________________</div>
                <div>Tanggal: ___________________________</div>
                <div>Tanda Tangan: ______________________</div>
            </div>
        </div>
        <div class="field" style="margin-top: 10px;">
            <div class="label">Catatan Admin</div>
            <div class="box"></div>
        </div>
    </div>
</body>
</html>
