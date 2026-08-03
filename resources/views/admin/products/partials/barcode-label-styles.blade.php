<style>
    .barcode-document,
    .barcode-document * {
        box-sizing: border-box;
    }

    .barcode-document {
        color: #111827;
        font-family: DejaVu Sans, Arial, sans-serif;
    }

    .barcode-page {
        background: #fff;
        overflow: hidden;
        position: relative;
    }

    .barcode-page--break-before {
        page-break-before: always;
    }

    .barcode-page--a4 {
        height: 297mm;
        padding: 6mm;
        width: 210mm;
    }

    .barcode-label-grid {
        border-collapse: separate;
        border-spacing: 2mm;
        table-layout: fixed;
        width: 100%;
    }

    .barcode-label-cell {
        height: 36mm;
        padding: 0;
        vertical-align: top;
        width: 33.333%;
    }

    .barcode-label-shell {
        background: #fff;
        border: 0.25mm solid #d1d5db;
        border-radius: 1mm;
        height: 36mm;
        overflow: hidden;
        padding: 2mm 2.4mm 1.6mm;
        text-align: center;
        width: 100%;
    }

    .barcode-product-name {
        font-size: 8pt;
        font-weight: 700;
        height: 7.4mm;
        line-height: 3.7mm;
        margin: 0;
        overflow: hidden;
    }

    .barcode-meta {
        border-collapse: collapse;
        color: #4b5563;
        font-size: 6.5pt;
        line-height: 3.5mm;
        margin: 0;
        table-layout: fixed;
        width: 100%;
    }

    .barcode-meta td {
        overflow: hidden;
        padding: 0;
        white-space: nowrap;
    }

    .barcode-meta td {
        text-align: center;
        width: 100%;
    }

    .barcode-graphic {
        height: 13mm;
        line-height: 13mm;
        margin: 0 auto;
        overflow: hidden;
        text-align: center;
        width: 100%;
    }

    .barcode-graphic img {
        display: inline-block;
        image-rendering: crisp-edges;
        max-width: 100%;
        vertical-align: middle;
    }

    .barcode-graphic--linear img {
        height: 10mm;
        width: auto;
    }

    .barcode-graphic--qr img {
        height: 12mm;
        width: 12mm;
    }

    .barcode-code {
        color: #111827;
        font-family: DejaVu Sans Mono, Courier New, monospace;
        font-size: 7pt;
        font-weight: 700;
        letter-spacing: 0.2mm;
        line-height: 3.5mm;
        margin: 0;
        overflow: hidden;
        white-space: nowrap;
    }

    .barcode-page--thermal {
        height: {{ $paperSize->heightMillimeters() }}mm;
        padding: 0.5mm;
        width: {{ $paperSize->widthMillimeters() }}mm;
    }

    .barcode-page--thermal .barcode-label-shell {
        border: 0;
        height: {{ $paperSize->heightMillimeters() - 5 }}mm;
        padding: 2mm 3mm 1.5mm;
    }

    .barcode-page--thermal .barcode-product-name {
        font-size: 9pt;
    }

    .barcode-page--thermal .barcode-meta {
        font-size: 7pt;
    }

    .barcode-page--thermal .barcode-graphic {
        height: 14mm;
        line-height: 14mm;
    }

    .barcode-page--thermal .barcode-graphic--linear img {
        height: 11mm;
    }

    .barcode-page--thermal .barcode-graphic--qr img {
        height: 13mm;
        width: 13mm;
    }

    .barcode-page--thermal .barcode-code {
        font-size: 7.5pt;
    }

    .barcode-preview-stage {
        background: #eef1f5;
        border: 1px solid #e5e7eb;
        border-radius: 0.75rem;
        max-height: 760px;
        overflow: auto;
        padding: 20px;
    }

    .barcode-preview-stage .barcode-page {
        box-shadow: 0 4px 18px rgba(15, 23, 42, 0.12);
        margin: 0 auto 20px;
    }

    .barcode-pdf-body .barcode-page--a4 {
        height: auto;
        width: auto;
    }

    .barcode-pdf-body .barcode-page--thermal {
        height: {{ $paperSize->heightMillimeters() - 4 }}mm;
        padding: 0.5mm;
        width: auto;
    }

    .barcode-pdf-body .barcode-page--thermal .barcode-label-shell {
        height: {{ $paperSize->heightMillimeters() - 5 }}mm;
    }
</style>
