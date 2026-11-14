@once
<style>
/* ══════════════════════════════════════════════════════════════════════════
   LAPORAN OWNER · EXECUTIVE DASHBOARD (DARI dashboard-laporan-gudang.html)
   ══════════════════════════════════════════════════════════════════════════ */
.gudang-dashboard-wrap {
    --navy: #102a4b;
    --dark: #10213c;
    --gold: #edba52;
    --gold-ink: #875c08;
    --green: #078455;
    --green-light: #e9f7ef;
    --bg: #f4f7fb;
    --white: #fff;
    --line: #e0e7ef;
    --muted: #556780;
    --soft: #eef3f8;
    --red: #b44136;
    --shadow: 0 3px 12px rgba(29, 54, 83, 0.05);
    --rad: 16px;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
    color: var(--dark);
    font-size: 14px;
    line-height: 1.5;
    max-width: 1540px;
    margin: 0 auto;
    padding-bottom: 30px;
}

.gudang-dashboard-wrap * { box-sizing: border-box; }

/* SVG Icons */
.gudang-dashboard-wrap .ico {
    fill: none;
    stroke: currentColor;
    stroke-width: 1.75;
    stroke-linecap: round;
    stroke-linejoin: round;
    width: 19px;
    height: 19px;
    display: inline-block;
    flex: none;
    vertical-align: middle;
}

/* Hero Section */
.gudang-dashboard-wrap .hero {
    border-radius: 20px;
    background: linear-gradient(112deg, #102945 7%, #15365a 70%, #194163 100%);
    color: #fff;
    position: relative;
    overflow: hidden;
    padding: 28px 29px 31px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 20px;
    isolation: isolate;
    margin-bottom: 14px;
}
.gudang-dashboard-wrap .hero-decor {
    position: absolute;
    right: 0;
    bottom: 0;
    width: min(56%, 700px);
    height: 100%;
    pointer-events: none;
    z-index: -1;
}
.gudang-dashboard-wrap .eyebrow {
    color: #ffcf76;
    display: flex;
    align-items: center;
    gap: 8px;
    text-transform: uppercase;
    letter-spacing: .13em;
    font-weight: 760;
    font-size: 10px;
}
.gudang-dashboard-wrap .eyebrow .ico { width: 16px; height: 16px; }
.gudang-dashboard-wrap .hero h1 {
    font-size: clamp(30px, 3.8vw, 44px);
    letter-spacing: -.055em;
    line-height: 1.05;
    margin: 8px 0 7px;
    font-weight: 780;
    color: #fff;
}
.gudang-dashboard-wrap .hero p {
    margin: 0;
    color: #d0def0;
    font-size: 13px;
    max-width: 520px;
}
.gudang-dashboard-wrap .hero-actions {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
    flex: none;
}
.gudang-dashboard-wrap .hero-date {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #dfeaf6;
    font-size: 12px;
    font-weight: 650;
}
.gudang-dashboard-wrap .hero-date .ico { width: 15px; height: 15px; color: #ffcf76; }

/* Buttons */
.gudang-dashboard-wrap .btn-dash {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    white-space: nowrap;
    border: 1px solid transparent;
    border-radius: 9px;
    min-height: 38px;
    padding: 7px 14px;
    font-weight: 730;
    font-size: 12px;
    background: #fff;
    color: var(--dark);
    transition: background .15s, box-shadow .15s;
    line-height: 1.2;
    cursor: pointer;
    text-decoration: none;
}
.gudang-dashboard-wrap .btn-dash .ico { height: 16px; width: 16px; }
.gudang-dashboard-wrap .btn-dash:hover { background: #e9f1fa; color: var(--dark); }
.gudang-dashboard-wrap .btn-dash.gold { background: #ffcf76; color: #252416; }
.gudang-dashboard-wrap .btn-dash.gold:hover { background: #ffe1a0; color: #252416; }
.gudang-dashboard-wrap .btn-dash.green { background: #087c51; color: #fff; }
.gudang-dashboard-wrap .btn-dash.green:hover { background: #066341; color: #fff; }
.gudang-dashboard-wrap .btn-dash.clear { border-color: var(--line); color: #445a73; background: #fff; }
.gudang-dashboard-wrap .btn-dash.clear:hover { background: #f0f5fa; }
.gudang-dashboard-wrap .btn-dash.icon-only { width: 38px; padding: 0; }
.gudang-dashboard-wrap .hero-actions .btn-dash { min-height: 36px; }

/* Filters Bar */
.gudang-dashboard-wrap .filters {
    margin: 14px 0 18px;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: var(--rad);
    box-shadow: var(--shadow);
    padding: 13px 15px;
    display: grid;
    grid-template-columns: 1fr 1fr minmax(170px, 1.5fr) 1fr auto;
    align-items: end;
    gap: 12px;
}
.gudang-dashboard-wrap .field {
    min-width: 0;
    display: grid;
    gap: 5px;
    font-size: 11px;
    font-weight: 750;
    color: #435771;
    margin: 0;
}
.gudang-dashboard-wrap .input-wrap {
    display: flex;
    align-items: center;
    position: relative;
    min-width: 0;
}
.gudang-dashboard-wrap .input-wrap > .ico {
    pointer-events: none;
    position: absolute;
    left: 11px;
    width: 15px;
    height: 15px;
    color: #59708c;
}
.gudang-dashboard-wrap .input-wrap > select,
.gudang-dashboard-wrap .input-wrap > input {
    width: 100%;
    min-width: 0;
    height: 38px;
    background: #f7f9fc;
    color: #182c48;
    border: 1px solid var(--line);
    border-radius: 8px;
    padding: 0 9px 0 32px;
    font-size: 12px;
    font-weight: 620;
    outline: none;
}
.gudang-dashboard-wrap .input-wrap > select:focus,
.gudang-dashboard-wrap .input-wrap > input:focus {
    border-color: #387bd5;
    background: #fff;
}
.gudang-dashboard-wrap .filters-end { display: flex; gap: 7px; }

/* Section Header */
.gudang-dashboard-wrap .section-head {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 12px;
    margin: 20px 0 10px;
}
.gudang-dashboard-wrap .section-head h2 {
    font-size: 19px;
    letter-spacing: -.035em;
    margin: 0;
    font-weight: 780;
    color: var(--dark);
}
.gudang-dashboard-wrap .section-head p {
    font-size: 12px;
    color: var(--muted);
    margin: 2px 0 0;
}
.gudang-dashboard-wrap .refresh {
    font-size: 11px;
    font-weight: 620;
    color: var(--muted);
    display: flex;
    align-items: center;
    gap: 6px;
    padding-bottom: 2px;
}
.gudang-dashboard-wrap .refresh .ico { width: 14px; height: 14px; }

/* KPI Cards Grid */
.gudang-dashboard-wrap .kpis {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 14px;
}
.gudang-dashboard-wrap .card-dash {
    background: var(--white);
    border: 1px solid var(--line);
    border-radius: var(--rad);
    box-shadow: var(--shadow);
    min-width: 0;
}
.gudang-dashboard-wrap .kpi {
    min-height: 128px;
    padding: 12px 12px 10px;
    position: relative;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.gudang-dashboard-wrap .kpi.accent {
    border-color: #ead7aa;
    background: linear-gradient(145deg, #fff 45%, #fdf8ea);
}
.gudang-dashboard-wrap .kpi-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 5px;
}
.gudang-dashboard-wrap .sq {
    width: 33px;
    height: 33px;
    display: grid;
    place-items: center;
    background: #fff4da;
    color: #9c6710;
    border-radius: 9px;
    flex: none;
}
.gudang-dashboard-wrap .sq .ico { width: 17px; height: 17px; }
.gudang-dashboard-wrap .sq.blue  { background: #ecf2fa; color: #335e91; }
.gudang-dashboard-wrap .sq.green { background: #e9f7ef; color: #087650; }
.gudang-dashboard-wrap .sq.red   { background: #fdefe9; color: #ad4832; }

.gudang-dashboard-wrap .kpi-name {
    color: #4d627c;
    font-size: 11px;
    font-weight: 750;
    margin-top: 8px;
}
.gudang-dashboard-wrap .kpi-value {
    font-size: clamp(15px, 1.6vw, 22px);
    letter-spacing: -.052em;
    font-weight: 790;
    line-height: 1.2;
    margin: 2px 0 2px;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gudang-dashboard-wrap .kpi-note {
    font-size: 10px;
    color: var(--muted);
    line-height: 1.35;
}

/* KPI Sparklines */
.gudang-dashboard-wrap .kpi-sparkline {
    width: 100%;
    height: 24px;
    margin-top: 6px;
    overflow: visible;
}
.gudang-dashboard-wrap .kpi-sparkline polyline {
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.gudang-dashboard-wrap .kpi-sparkline.blue  { color: #335e91; }
.gudang-dashboard-wrap .kpi-sparkline.green { color: #087650; }
.gudang-dashboard-wrap .kpi-sparkline.gold  { color: #9c6710; }
.gudang-dashboard-wrap .kpi-sparkline.red   { color: #ad4832; }

.gudang-dashboard-wrap .pill {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    white-space: nowrap;
    border-radius: 20px;
    background: var(--soft);
    color: #455d79;
    padding: 3px 7px;
    font-size: 10px;
    font-weight: 750;
    line-height: 1.3;
}
.gudang-dashboard-wrap .pill.ok    { background: var(--green-light); color: #086745; }
.gudang-dashboard-wrap .pill.warn  { background: #fff1d8; color: #82540d; }
.gudang-dashboard-wrap .pill.alert { background: #fcece9; color: #9b3a32; }

/* Main Grid (2 Kolom: Tren & Focus) */
.gudang-dashboard-wrap .main-grid {
    display: grid;
    grid-template-columns: minmax(0, 2.04fr) minmax(295px, 1fr);
    gap: 12px;
    margin-top: 12px;
}
.gudang-dashboard-wrap .panel {
    padding: 16px 17px;
}
.gudang-dashboard-wrap .panel-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 9px;
}
.gudang-dashboard-wrap .panel-title {
    display: flex;
    align-items: center;
    gap: 9px;
}
.gudang-dashboard-wrap .panel-title h3,
.gudang-dashboard-wrap .panel-head h3 {
    margin: 0;
    font-size: 15px;
    letter-spacing: -.02em;
    font-weight: 770;
    line-height: 1.25;
    color: var(--dark);
}
.gudang-dashboard-wrap .panel-title p,
.gudang-dashboard-wrap .panel-head p {
    margin: 3px 0 0;
    font-size: 11px;
    color: var(--muted);
}
.gudang-dashboard-wrap .detail {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    white-space: nowrap;
    border: 0;
    background: transparent;
    color: #245984;
    font-size: 11px;
    font-weight: 780;
    padding: 5px 0 5px 5px;
    cursor: pointer;
    text-decoration: none;
}
.gudang-dashboard-wrap .detail:hover { text-decoration: underline; color: #16365c; }
.gudang-dashboard-wrap .detail .ico { width: 13px; height: 13px; }

/* Trend Chart */
.gudang-dashboard-wrap .trend-meta {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    gap: 8px;
    margin: 13px 0 4px;
}
.gudang-dashboard-wrap .trend-meta strong {
    display: block;
    font-size: 24px;
    letter-spacing: -.04em;
    line-height: 1.1;
    color: var(--dark);
}
.gudang-dashboard-wrap .trend-meta small { color: var(--muted); font-size: 10px; }
.gudang-dashboard-wrap .legend {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 11px;
    font-size: 10px;
    color: #4d627a;
    font-weight: 650;
}
.gudang-dashboard-wrap .legend span { display: inline-flex; align-items: center; gap: 5px; }
.gudang-dashboard-wrap .dot {
    width: 8px;
    height: 8px;
    display: inline-block;
    border-radius: 50%;
    background: #078455;
    flex: none;
}
.gudang-dashboard-wrap .dot.gold { background: #c99435; }
.gudang-dashboard-wrap .dot.navy { background: #24496f; }
.gudang-dashboard-wrap .dot.gray { background: #a5b7cd; }
.gudang-dashboard-wrap .dot.red  { background: #ba5144; }

.gudang-dashboard-wrap .chart {
    position: relative;
    margin-top: 4px;
}
.gudang-dashboard-wrap .chart svg {
    display: block;
    width: 100%;
    height: 164px;
    overflow: visible;
}
.gudang-dashboard-wrap .axis {
    font-size: 10px;
    fill: #61738a;
    font-family: inherit;
}
.gudang-dashboard-wrap .bar {
    fill: #244c70;
    transition: opacity .2s;
}
.gudang-dashboard-wrap .bar:hover { fill: #0a8a5e; }
.gudang-dashboard-wrap .chart-empty {
    min-height: 154px;
    display: grid;
    place-content: center;
    text-align: center;
    color: #5e7188;
    background: #f8fafc;
    border: 1px dashed #dae4ef;
    border-radius: 11px;
    font-size: 12px;
    gap: 7px;
    margin-top: 11px;
}
.gudang-dashboard-wrap .chart-empty .ico { margin: auto; color: #6b85a2; }

/* Focus Panel */
.gudang-dashboard-wrap .focus { background: #fff; position: relative; }
.gudang-dashboard-wrap .focus-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
}
.gudang-dashboard-wrap .focus-items { display: grid; gap: 0; margin-top: 9px; }
.gudang-dashboard-wrap .focus-item {
    display: grid;
    width: 100%;
    border: 0;
    border-bottom: 1px solid #e9eef4;
    background: transparent;
    text-align: left;
    align-items: center;
    grid-template-columns: auto minmax(0, 1fr) auto;
    gap: 9px;
    padding: 9px 0;
    color: var(--dark);
    text-decoration: none;
    cursor: pointer;
}
.gudang-dashboard-wrap .focus-item:last-child { border-bottom: 0; }
.gudang-dashboard-wrap .focus-item:hover { background: #f7f9fc; }
.gudang-dashboard-wrap .focus-item strong { display: block; font-size: 11.5px; }
.gudang-dashboard-wrap .focus-item small { display: block; font-size: 10px; color: var(--muted); margin-top: 1px; }
.gudang-dashboard-wrap .focus-item .sq { width: 29px; height: 29px; }
.gudang-dashboard-wrap .focus-foot {
    margin-top: 7px;
    border-radius: 9px;
    background: #f0f5fa;
    padding: 7px 9px;
    font-size: 10px;
    color: #465f7b;
    display: flex;
    align-items: center;
    gap: 7px;
}
.gudang-dashboard-wrap .focus-foot .ico { width: 14px; height: 14px; }

/* Health Component */
.gudang-dashboard-wrap .health {
    margin-top: 12px;
    display: grid;
    grid-template-columns: minmax(215px, 1.1fr) minmax(0, 3.25fr);
    overflow: hidden;
    align-items: stretch;
}
.gudang-dashboard-wrap .health-lead {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #122e50;
    color: #fff;
    padding: 12px 16px;
}
.gudang-dashboard-wrap .health-lead small { display: block; color: #bfd1e7; font-size: 10px; }
.gudang-dashboard-wrap .health-lead strong { display: block; font-size: 12.5px; }
.gudang-dashboard-wrap .score {
    display: grid;
    place-items: center;
    position: relative;
    width: 48px;
    height: 48px;
    flex: none;
    border-radius: 50%;
    background: conic-gradient(#64d4a0 var(--score, 80%), #385472 0);
    isolation: isolate;
    font-size: 16px;
    font-weight: 800;
}
.gudang-dashboard-wrap .score:before {
    content: "";
    position: absolute;
    inset: 4px;
    background: #122e50;
    border-radius: 50%;
    z-index: -1;
}

/* Tombol Info Rumus Skor (Catatan 1) */
.gudang-dashboard-wrap .health-info-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    color: #ffe19c;
    border-radius: 6px;
    padding: 2px 7px;
    font-size: 10px;
    font-weight: 700;
    cursor: pointer;
    transition: background .15s;
    margin-top: 3px;
}
.gudang-dashboard-wrap .health-info-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    color: #fff;
}
.gudang-dashboard-wrap .health-info-btn .ico { width: 12px; height: 12px; }

.gudang-dashboard-wrap .health-metrics {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    padding: 9px 12px;
    align-items: center;
}
.gudang-dashboard-wrap .health-one {
    min-width: 0;
    padding: 0 8px;
    border-right: 1px solid #e8eef4;
}
.gudang-dashboard-wrap .health-one:last-child { border: 0; }
.gudang-dashboard-wrap .health-one small { font-size: 10px; font-weight: 650; color: #506681; display: block; }
.gudang-dashboard-wrap .health-one strong { font-size: 14px; line-height: 1.4; display: block; color: var(--dark); }
.gudang-dashboard-wrap .health-one strong em { font-size: 9px; font-style: normal; color: #5f7186; }
.gudang-dashboard-wrap .progress-health {
    height: 5px;
    border-radius: 8px;
    background: #e9eff6;
    overflow: hidden;
    margin-top: 3px;
}
.gudang-dashboard-wrap .progress-health span {
    display: block;
    height: 100%;
    background: #0b9361;
    border-radius: 8px;
}

/* 6 Modules Grid */
.gudang-dashboard-wrap .modules {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}
.gudang-dashboard-wrap .module {
    display: flex;
    flex-direction: column;
    min-height: 234px;
}
.gudang-dashboard-wrap .module-body { margin-top: 12px; flex: 1; }
.gudang-dashboard-wrap .module-foot {
    margin: 13px -17px -16px;
    padding: 9px 17px;
    background: #f7f9fc;
    border-top: 1px solid #e9eef4;
    border-radius: 0 0 15px 15px;
    color: #49617c;
    font-size: 10px;
    font-weight: 650;
}
.gudang-dashboard-wrap .mini-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 7px;
    margin-bottom: 9px;
}
.gudang-dashboard-wrap .mini {
    padding: 7px 9px;
    border: 1px solid #e7edf4;
    background: #f7f9fc;
    border-radius: 8px;
    min-width: 0;
}
.gudang-dashboard-wrap .mini small { display: block; font-size: 10px; color: var(--muted); margin-bottom: 1px; }
.gudang-dashboard-wrap .mini b { font-size: 14px; font-weight: 780; letter-spacing: -.025em; white-space: nowrap; }

.gudang-dashboard-wrap .channel { margin: 9px 0 0; }
.gudang-dashboard-wrap .channel-label { display: flex; justify-content: space-between; gap: 7px; font-size: 11px; font-weight: 650; }
.gudang-dashboard-wrap .channel-label span { color: #4d627b; }
.gudang-dashboard-wrap .channel .progress-channel {
    height: 6px;
    background: #e9eff6;
    border-radius: 8px;
    overflow: hidden;
    margin-top: 4px;
}
.gudang-dashboard-wrap .channel .progress-channel span {
    display: block;
    height: 100%;
    border-radius: 8px;
    background: var(--bar, #24496f);
}

.gudang-dashboard-wrap .table-wrap { overflow: auto; scrollbar-width: thin; }
.gudang-dashboard-wrap table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
    text-align: left;
    font-size: 11px;
}
.gudang-dashboard-wrap th {
    font-size: 10px;
    color: #52677f;
    background: #f1f5f9;
    font-weight: 750;
    padding: 7px;
    border-bottom: 1px solid #e8eef4;
}
.gudang-dashboard-wrap td {
    padding: 7px;
    border-bottom: 1px solid #e8eef4;
    color: var(--dark);
}
.gudang-dashboard-wrap th:first-child { border-radius: 6px 0 0 0; }
.gudang-dashboard-wrap th:last-child  { border-radius: 0 6px 0 0; }
.gudang-dashboard-wrap td.empty { color: #61768d; text-align: center; }
.gudang-dashboard-wrap .num { text-align: right; }

.gudang-dashboard-wrap .donut-box {
    display: flex;
    gap: 13px;
    align-items: center;
    margin: 0 0 10px;
}
.gudang-dashboard-wrap .donut {
    position: relative;
    flex: none;
    display: grid;
    place-content: center;
    text-align: center;
    width: 75px;
    height: 75px;
    border-radius: 50%;
    background: conic-gradient(var(--ring, #078455) 0 var(--pct, 70%), var(--ring2, #e8eef5) 0);
    isolation: isolate;
}
.gudang-dashboard-wrap .donut:before {
    position: absolute;
    content: "";
    inset: 7px;
    border-radius: 50%;
    background: #fff;
    z-index: -1;
}
.gudang-dashboard-wrap .donut b { font-size: 16px; line-height: 1.1; letter-spacing: -.04em; }
.gudang-dashboard-wrap .donut small { font-size: 9px; color: var(--muted); }
.gudang-dashboard-wrap .legend-stack {
    width: 100%;
    display: grid;
    gap: 5px;
    font-size: 11px;
}
.gudang-dashboard-wrap .legend-stack div {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #4c627c;
}
.gudang-dashboard-wrap .legend-stack b {
    margin-left: auto;
    color: var(--dark);
    text-align: right;
}

/* Modal Rumus Skor (Catatan 1) */
#modalRumusSkorBisnis .modal-content {
    border-radius: 16px;
    border: 1px solid #d9e3ef;
    box-shadow: 0 20px 65px rgba(17, 39, 69, 0.25);
    overflow: hidden;
}
#modalRumusSkorBisnis .modal-header {
    background: linear-gradient(112deg, #102945, #173b62);
    color: #fff;
    padding: 18px 24px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
#modalRumusSkorBisnis .modal-title { color: #fff; font-weight: 750; font-size: 17px; }
#modalRumusSkorBisnis .formula-card {
    background: #f7f9fc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 12px 16px;
    margin-bottom: 12px;
}
#modalRumusSkorBisnis .formula-card h6 {
    font-weight: 750;
    font-size: 13px;
    color: #102a4b;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
#modalRumusSkorBisnis .formula-code {
    background: #edf2f7;
    padding: 3px 8px;
    border-radius: 6px;
    font-family: monospace;
    font-size: 12px;
    color: #086745;
    font-weight: 700;
}
#modalRumusSkorBisnis .formula-desc {
    font-size: 12px;
    color: #4a5568;
    margin: 0;
    line-height: 1.5;
}

/* Responsive Breakpoints */
@media (max-width: 1130px) {
    .gudang-dashboard-wrap .kpis { grid-template-columns: repeat(3, 1fr); }
    .gudang-dashboard-wrap .kpi { min-height: 112px; }
    .gudang-dashboard-wrap .main-grid { grid-template-columns: minmax(0, 1.6fr) minmax(275px, 1fr); }
}
@media (max-width: 860px) {
    .gudang-dashboard-wrap .modules { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .gudang-dashboard-wrap .filters { grid-template-columns: 1fr 1fr 1fr; }
    .gudang-dashboard-wrap .filters-end { grid-column: span 2; justify-content: flex-end; }
    .gudang-dashboard-wrap .health { grid-template-columns: 1fr; }
    .gudang-dashboard-wrap .health-metrics { padding: 10px 5px; }
}
@media (max-width: 640px) {
    .gudang-dashboard-wrap .hero {
        padding: 20px 17px;
        align-items: flex-start;
        flex-direction: column;
        gap: 12px;
    }
    .gudang-dashboard-wrap .hero-actions {
        align-items: flex-start;
        flex-direction: row;
        flex-wrap: wrap;
        justify-content: space-between;
        width: 100%;
    }
    .gudang-dashboard-wrap .hero h1 { font-size: 32px; }
    .gudang-dashboard-wrap .filters {
        grid-template-columns: 1fr;
        gap: 8px;
        padding: 10px;
    }
    .gudang-dashboard-wrap .filters-end { grid-column: 1 / -1; }
    .gudang-dashboard-wrap .main-grid,
    .gudang-dashboard-wrap .modules { grid-template-columns: 1fr; }
    .gudang-dashboard-wrap .kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 7px;
    }
    .gudang-dashboard-wrap .health-metrics {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 9px;
    }
    .gudang-dashboard-wrap .health-one:nth-child(2) { border-right: 0; }
}
</style>
@endonce
