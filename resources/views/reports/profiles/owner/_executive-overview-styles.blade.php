@once
<style>
/* ══════════════════════════════════════════════════════════════════════════
   LAPORAN OWNER – METRONIC 8 EXECUTIVE SUITE STYLES
   ══════════════════════════════════════════════════════════════════════════ */
.owner-report-suite {
    font-family: Inter, system-ui, -apple-system, sans-serif;
    color: #181c32;
}

/* Executive Hero Header */
.eos-hero {
    background: linear-gradient(135deg, #0d1b2a 0%, #1b263b 50%, #17324d 100%);
    border-radius: 16px;
    padding: 28px 32px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(13, 27, 42, 0.18);
    margin-bottom: 24px;
}
.eos-hero::after {
    content: '';
    position: absolute;
    right: -40px;
    top: -40px;
    width: 240px;
    height: 240px;
    border-radius: 50%;
    background: radial-gradient(circle, rgba(244, 201, 107, 0.15) 0%, rgba(244, 201, 107, 0) 70%);
    pointer-events: none;
}
.eos-eyebrow {
    color: #f4c96b;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.8px;
    text-transform: uppercase;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
}
.eos-title {
    color: #ffffff;
    font-size: 26px;
    font-weight: 800;
    margin: 0 0 6px;
    line-height: 1.2;
}
.eos-subtitle {
    color: rgba(255, 255, 255, 0.75);
    font-size: 13px;
    margin: 0;
}
.eos-period-badge {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.16);
    color: #ffffff;
    border-radius: 10px;
    padding: 6px 14px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Filter Card */
.eos-filter-card {
    background: #ffffff;
    border: 1px solid #ebedf3;
    border-radius: 14px;
    padding: 16px 20px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, 0.03);
    margin-bottom: 24px;
}
.eos-nav-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #f1f3f8;
}
.eos-nav-tabs .btn {
    font-size: 12px;
    font-weight: 600;
    border-radius: 8px;
    padding: 6px 14px;
}

/* 6 KPI Cards with Sparklines */
.eos-kpi-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}
.eos-kpi-card {
    background: #ffffff;
    border: 1px solid #eef1f6;
    border-radius: 14px;
    padding: 18px 16px 14px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 4px 15px rgba(24, 28, 50, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}
.eos-kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(24, 28, 50, 0.08);
}
.eos-kpi-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
}
.eos-kpi-card.tone-green::before { background: linear-gradient(90deg, #10b981, #34d399); }
.eos-kpi-card.tone-blue::before  { background: linear-gradient(90deg, #3b82f6, #60a5fa); }
.eos-kpi-card.tone-gold::before  { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
.eos-kpi-card.tone-red::before   { background: linear-gradient(90deg, #ef4444, #f87171); }

.eos-kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}
.eos-kpi-label {
    font-size: 11px;
    font-weight: 700;
    color: #7e8299;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.eos-kpi-icon {
    width: 28px;
    height: 28px;
    border-radius: 8px;
    display: grid;
    place-items: center;
    font-size: 14px;
}
.eos-kpi-value {
    font-size: 20px;
    font-weight: 800;
    color: #181c32;
    line-height: 1.15;
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.eos-kpi-note {
    font-size: 11px;
    color: #a1a5b7;
    margin-bottom: 12px;
}

/* Sparkline in KPI Card */
.eos-kpi-chart {
    width: 100%;
    height: 44px;
    overflow: visible;
}
.eos-kpi-chart polyline {
    fill: none;
    stroke: currentColor;
    stroke-width: 2.2;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.eos-kpi-chart .area {
    stroke: none;
}
.eos-kpi-chart.green { color: #10b981; }
.eos-kpi-chart.blue  { color: #3b82f6; }
.eos-kpi-chart.gold  { color: #f59e0b; }
.eos-kpi-chart.red   { color: #ef4444; }

/* Panels & Cards */
.eos-card {
    background: #ffffff;
    border: 1px solid #eef1f6;
    border-radius: 14px;
    box-shadow: 0 4px 15px rgba(24, 28, 50, 0.03);
    margin-bottom: 24px;
    overflow: hidden;
}
.eos-card-header {
    padding: 16px 22px;
    border-bottom: 1px solid #f1f3f8;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.eos-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #181c32;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}
.eos-card-body {
    padding: 20px 22px;
}

/* Executive Briefing box */
.eos-briefing-box {
    background: linear-gradient(135deg, #0e2a47 0%, #17385c 100%);
    border-radius: 14px;
    padding: 22px 24px;
    color: #ffffff;
    height: 100%;
    box-shadow: 0 6px 20px rgba(14, 42, 71, 0.15);
}
.eos-briefing-box h3 {
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.eos-briefing-box p {
    color: rgba(255, 255, 255, 0.85);
    font-size: 13px;
    line-height: 1.65;
    margin: 0 0 16px;
}
.eos-briefing-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    border-top: 1px solid rgba(255, 255, 255, 0.15);
    padding-top: 14px;
}
.eos-briefing-stat-label {
    font-size: 10px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.6);
    text-transform: uppercase;
}
.eos-briefing-stat-val {
    font-size: 15px;
    font-weight: 800;
    color: #ffffff;
}

/* Business Health Component */
.eos-health-gauge {
    display: flex;
    align-items: center;
    gap: 20px;
}
.eos-gauge-circle {
    width: 94px;
    height: 78px;
    flex-shrink: 0;
    background: radial-gradient(circle at 50% 68%, #ffffff 46%, transparent 48%),
                conic-gradient(from 270deg at 50% 70%, #10b981 0 var(--score, 180deg), #eef1f6 var(--score, 180deg) 180deg, transparent 180deg);
    border-radius: 50px 50px 0 0;
    display: grid;
    place-content: center;
    text-align: center;
}
.eos-gauge-score {
    font-size: 24px;
    font-weight: 800;
    color: #181c32;
    line-height: 1;
}
.eos-health-metric-row {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex-grow: 1;
}
.eos-health-item {
    display: grid;
    grid-template-columns: 140px 1fr 40px;
    align-items: center;
    gap: 10px;
    font-size: 12px;
}
.eos-health-bar {
    height: 7px;
    background: #edf1f7;
    border-radius: 10px;
    overflow: hidden;
}
.eos-health-fill {
    height: 100%;
    border-radius: 10px;
}

/* Tables in Metronic Style */
.eos-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.eos-table th {
    background: #f8fafc;
    color: #7e8299;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: 10px 14px;
    border-bottom: 1px solid #ebedf3;
    white-space: nowrap;
}
.eos-table td {
    padding: 11px 14px;
    border-bottom: 1px solid #f1f3f8;
    color: #3f4254;
    vertical-align: middle;
}
.eos-table tr:hover td {
    background: #fafbfe;
}
.eos-table tr:last-child td {
    border-bottom: none;
}

/* Action & Anomaly Chips */
.eos-anomaly-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: 10px;
    border: 1px solid;
    margin-bottom: 10px;
}
.eos-anomaly-card:last-child {
    margin-bottom: 0;
}
.eos-anomaly-card.alert-danger {
    background: #fff5f5;
    border-color: #ffd6d6;
    color: #d9214e;
}
.eos-anomaly-card.alert-warning {
    background: #fff8dd;
    border-color: #ffe699;
    color: #9c6c00;
}
.eos-anomaly-card.alert-success {
    background: #e8fff3;
    border-color: #bbf7d0;
    color: #10b981;
}

/* Revenue Area Chart */
.eos-revenue-chart-svg {
    width: 100%;
    height: 100px;
    overflow: visible;
}
.eos-revenue-chart-svg polyline {
    fill: none;
    stroke: #10b981;
    stroke-width: 3;
    stroke-linecap: round;
    stroke-linejoin: round;
}
.eos-revenue-chart-svg circle {
    fill: #ffffff;
    stroke: #10b981;
    stroke-width: 2.5;
    cursor: pointer;
    transition: r 0.2s;
}
.eos-revenue-chart-svg circle:hover {
    r: 6;
    filter: drop-shadow(0 2px 5px rgba(16, 185, 129, 0.4));
}

/* Responsive */
@media (max-width: 1400px) {
    .eos-kpi-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 992px) {
    .eos-kpi-grid { grid-template-columns: repeat(2, 1fr); }
    .eos-hero { padding: 20px; }
    .eos-title { font-size: 20px; }
}
@media (max-width: 576px) {
    .eos-kpi-grid { grid-template-columns: 1fr; }
    .eos-health-item { grid-template-columns: 100px 1fr 30px; }
}
</style>
@endonce
