<div class="guide-diagram" data-guide-flow="store-stock">
    <div class="guide-stock-flow" role="img" aria-labelledby="store-stock-flow-title store-stock-flow-description">
        <svg viewBox="0 0 1180 470" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <defs>
                <marker id="store-stock-arrow" markerWidth="10" markerHeight="10" refX="8" refY="3" orient="auto" markerUnits="strokeWidth">
                    <path d="M0,0 L0,6 L9,3 z" fill="currentColor" />
                </marker>
            </defs>

            <text class="flow-label" x="20" y="24">Jalur pembelian langsung toko</text>
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M160 75 H205" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M365 75 H410" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M550 75 H595" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M795 75 H840" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M1000 75 H1040" />

            <g class="flow-node" transform="translate(20 45)">
                <rect width="140" height="60" rx="10" />
                <text x="70" y="36" text-anchor="middle">Pemasok</text>
            </g>
            <g class="flow-node flow-node-primary" transform="translate(205 45)">
                <rect width="160" height="60" rx="10" />
                <text x="80" y="36" text-anchor="middle">Pembelian Toko</text>
            </g>
            <g class="flow-node" transform="translate(410 45)">
                <rect width="140" height="60" rx="10" />
                <text x="70" y="36" text-anchor="middle">Persetujuan</text>
            </g>
            <g class="flow-node" transform="translate(595 45)">
                <rect width="200" height="60" rx="10" />
                <text x="100" y="27" text-anchor="middle">
                    <tspan x="100">Penerimaan Barang</tspan>
                    <tspan x="100" dy="20">di Toko</tspan>
                </text>
            </g>
            <g class="flow-node flow-node-success" transform="translate(840 45)">
                <rect width="160" height="60" rx="10" />
                <text x="80" y="27" text-anchor="middle">
                    <tspan x="80">Stok Reguler</tspan>
                    <tspan x="80" dy="20">Toko</tspan>
                </text>
            </g>
            <g class="flow-node flow-node-primary" transform="translate(1040 45)">
                <rect width="120" height="60" rx="10" />
                <text x="60" y="27" text-anchor="middle">
                    <tspan x="60">Penjualan</tspan>
                    <tspan x="60" dy="20">POS</tspan>
                </text>
            </g>

            <text class="flow-label" x="20" y="174">Jalur transfer gudang utama</text>
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M180 225 H235" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M395 225 H920 V113" />
            <g class="flow-node" transform="translate(20 195)">
                <rect width="160" height="60" rx="10" />
                <text x="80" y="27" text-anchor="middle">
                    <tspan x="80">Gudang</tspan>
                    <tspan x="80" dy="20">Utama</tspan>
                </text>
            </g>
            <g class="flow-node" transform="translate(235 195)">
                <rect width="160" height="60" rx="10" />
                <text x="80" y="27" text-anchor="middle">
                    <tspan x="80">Transfer</tspan>
                    <tspan x="80" dy="20">Stok</tspan>
                </text>
            </g>

            <text class="flow-label" x="20" y="324">Jalur pembelian darurat</text>
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M190 375 H245" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M405 375 H1100 V113" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M405 390 H445 V425 H480" />
            <path class="flow-line" marker-end="url(#store-stock-arrow)" d="M680 425 H920 V113" />
            <g class="flow-node flow-node-warning" transform="translate(20 345)">
                <rect width="170" height="60" rx="10" />
                <text x="85" y="27" text-anchor="middle">
                    <tspan x="85">Pembelian</tspan>
                    <tspan x="85" dy="20">Darurat</tspan>
                </text>
            </g>
            <g class="flow-node flow-node-warning" transform="translate(245 345)">
                <rect width="160" height="60" rx="10" />
                <text x="80" y="27" text-anchor="middle">
                    <tspan x="80">Stok</tspan>
                    <tspan x="80" dy="20">Darurat</tspan>
                </text>
            </g>
            <g class="flow-node flow-node-success" transform="translate(480 395)">
                <rect width="200" height="60" rx="10" />
                <text x="100" y="27" text-anchor="middle">
                    <tspan x="100">Konversi ke</tspan>
                    <tspan x="100" dy="20">Stok Reguler</tspan>
                </text>
            </g>
        </svg>

        <div class="visually-hidden">
            <span id="store-stock-flow-title">Diagram alur persediaan toko</span>
            <p id="store-stock-flow-description">
                Pemasok menuju pembelian toko, persetujuan, penerimaan barang di toko, stok reguler toko, lalu penjualan POS.
                Gudang utama menuju transfer stok lalu stok reguler toko.
                Pembelian darurat menuju stok darurat, kemudian dapat dijual melalui POS atau dikonversi ke stok reguler toko.
            </p>
        </div>
    </div>
</div>
