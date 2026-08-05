@if($activities->isNotEmpty())
    <div class="timeline-label">
        @foreach($activities as $activity)
            @php
                $action = App\Support\ProductAuditPresenter::action($activity);
                $changes = App\Support\ProductAuditPresenter::changes($activity, $auditRelations, auth()->user()?->can('margins.view_sensitive') ?? false);
                $causer = $activity->causer instanceof App\Models\User ? $activity->causer : null;
            @endphp
            <div class="timeline-item pb-8">
                <div class="timeline-label fw-semibold text-muted fs-8">{{ $activity->created_at?->format('H:i') }}</div>
                <div class="timeline-badge"><i class="ki-outline {{ $action['icon'] }} text-{{ $action['badge'] }} fs-2"></i></div>
                <div class="timeline-content ps-4 w-100">
                    <div class="card border border-gray-300 shadow-none">
                        <div class="card-header min-h-60px px-5">
                            <div class="card-title gap-3">
                                <span class="badge badge-light-{{ $action['badge'] }}">{{ $action['label'] }}</span>
                                <span class="text-muted fs-8">{{ $activity->created_at?->translatedFormat('d F Y, H.i') }} · {{ $activity->created_at?->diffForHumans() }}</span>
                            </div>
                            <div class="card-toolbar">
                                <div class="d-flex align-items-center gap-2">
                                    @if($causer?->avatar_path)
                                        <img src="{{ asset('storage/'.$causer->avatar_path) }}" class="rounded-circle" width="28" height="28" alt="{{ $causer->name }}">
                                    @else
                                        <span class="symbol symbol-30px"><span class="symbol-label bg-light-primary text-primary fw-bold">{{ mb_substr($causer?->name ?? 'S', 0, 1) }}</span></span>
                                    @endif
                                    <div><div class="fw-semibold fs-8">{{ $causer?->name ?? 'System' }}</div>@if($causer)<div class="text-muted fs-9">{{ $causer->username ?: $causer->email }}</div>@endif</div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-5">
                            @if($changes !== [])
                                <div class="table-responsive">
                                    <table class="table table-sm table-row-bordered align-middle mb-0">
                                        <thead><tr class="text-muted fw-bold fs-8"><th>Bagian</th><th>Nilai Sebelumnya</th><th>Nilai Baru</th></tr></thead>
                                        <tbody>
                                        @foreach($changes as $change)
                                            <tr>
                                                <td class="fw-semibold">{{ $change['label'] }}</td>
                                                <td class="text-muted text-break">@if($change['image'] && $change['old'] !== '-')<img src="{{ asset('storage/'.$change['old']) }}" class="rounded me-2" width="42" height="42" style="object-fit:cover" onerror="this.style.display='none'">{{ basename($change['old']) }}@else{{ $change['old'] }}@endif</td>
                                                <td class="text-break">@if($change['image'] && $change['new'] !== '-')<img src="{{ asset('storage/'.$change['new']) }}" class="rounded me-2" width="42" height="42" style="object-fit:cover" onerror="this.style.display='none'">{{ basename($change['new']) }}@else{{ $change['new'] }}@endif</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="text-muted fs-8"><i class="ki-outline ki-information-5 me-1"></i>Aktivitas tercatat tanpa rincian perubahan field.</div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="mt-5">{{ $activities->links() }}</div>
@else
    <x-metronic.empty-state title="Belum ada riwayat perubahan" description="Belum ada riwayat perubahan untuk produk ini." icon="ki-outline ki-time" />
@endif
