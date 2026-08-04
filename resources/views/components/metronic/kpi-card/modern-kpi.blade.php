{{--
  Modern KPI Cards for B2B Order
  @param string $title - Card title
  @param string|mixed $value - Value to display
  @param string $icon - Icon class
  @param string $color - Color (primary, success, info, warning, danger)
  @param string|null $subtitle - Optional subtitle
--}}
<div class="kpi-card">
    <div class="kpi-card-body">
        <div class="d-flex align-items-center justify-content-between">
            <div class="kpi-icon bg-{{ $color }}-subtle">
                <i class="ki-outline {{ $icon }} fs-3 text-{{ $color }}"></i>
            </div>
            @if(isset($progress))
            <div class="kpi-progress">
                <div class="progress" style="height: 4px; width: 60px;">
                    <div class="progress-bar bg-{{ $color }}" style="width: {{ $progress }}%"></div>
                </div>
            </div>
            @endif
        </div>
        <div class="kpi-value {{ $valueSize ?? 'fs-2' }} text-gray-900 fw-bold mb-1">{{ $value }}</div>
        <div class="kpi-title text-muted fs-7">{{ $title }}</div>
        @if(isset($subtitle))
        <div class="kpi-subtitle text-muted fs-7">{{ $subtitle }}</div>
        @endif
    </div>
</div>

<style>
.kpi-card {
    background: white;
    border: 1px solid var(--bs-gray-200);
    border-radius: var(--bs-border-radius-lg);
    transition: all 0.2s ease;
    height: 100%;
}
.kpi-card:hover {
    border-color: var(--bs-gray-300);
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transform: translateY(-2px);
}
.kpi-card-body {
    padding: 1.25rem;
}
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.bg-primary-subtle { background: var(--bs-primary-subtle) !important; }
.bg-success-subtle { background: var(--bs-success-subtle) !important; }
.bg-info-subtle { background: var(--bs-info-subtle) !important; }
.bg-warning-subtle { background: var(--bs-warning-subtle) !important; }
.bg-danger-subtle { background: var(--bs-danger-subtle) !important; }
</style>
