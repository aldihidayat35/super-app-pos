@extends('layouts.metronic.app')

@section('title', $guide['title'])
@section('page_title', $guide['title'])

@section('toolbar_actions')
    <a href="{{ route('guides.index') }}" class="btn btn-light-primary"><i class="ki-outline ki-arrow-left"></i> Semua Panduan</a>
    <button type="button" class="btn btn-light" onclick="window.print()"><i class="ki-outline ki-printer"></i> Cetak</button>
@endsection

@push('styles')
<style>
    .guide-content { color: var(--bs-gray-800); font-size: 1rem; line-height: 1.75; }
    .guide-content h1 { display: none; }
    .guide-content h2 { margin-top: 2.75rem; padding-bottom: .75rem; border-bottom: 1px solid var(--bs-gray-300); font-size: 1.55rem; color: var(--bs-gray-900); scroll-margin-top: 100px; }
    .guide-content h3 { margin-top: 2rem; font-size: 1.25rem; color: var(--bs-gray-900); scroll-margin-top: 100px; }
    .guide-content h4 { margin-top: 1.5rem; font-size: 1.1rem; scroll-margin-top: 100px; }
    .guide-content p, .guide-content ul, .guide-content ol { margin-bottom: 1rem; }
    .guide-content li { margin-bottom: .4rem; }
    .guide-content table { width: 100%; margin: 1.25rem 0 1.75rem; border-collapse: collapse; }
    .guide-content th, .guide-content td { padding: .8rem 1rem; border: 1px solid var(--bs-gray-300); vertical-align: top; }
    .guide-content th { background: var(--bs-gray-100); color: var(--bs-gray-900); font-weight: 700; }
    .guide-content code { padding: .15rem .35rem; border-radius: .35rem; background: var(--bs-gray-100); color: var(--bs-danger); }
    .guide-content pre { padding: 1rem; border-radius: .65rem; background: var(--bs-gray-900); color: var(--bs-gray-100); overflow-x: auto; }
    .guide-content pre code { padding: 0; background: transparent; color: inherit; }
    .guide-content blockquote { padding: 1rem 1.25rem; border-left: 4px solid var(--bs-primary); background: var(--bs-primary-light); border-radius: .35rem; }
    .guide-diagram { margin: 1.5rem 0 2rem; padding: 1.25rem; border: 1px solid var(--bs-gray-300); border-radius: .85rem; background: var(--bs-body-bg); overflow-x: auto; }
    .guide-stock-flow { min-width: 920px; color: var(--bs-gray-700); }
    .guide-stock-flow svg { display: block; width: 100%; max-width: 1180px; height: auto; margin: 0 auto; }
    .guide-stock-flow .flow-line { fill: none; stroke: currentColor; stroke-width: 2.5; }
    .guide-stock-flow .flow-node rect { fill: var(--bs-body-bg); stroke: var(--bs-gray-400); stroke-width: 2; }
    .guide-stock-flow .flow-node text { fill: var(--bs-gray-800); font-size: 15px; font-weight: 600; }
    .guide-stock-flow .flow-node-primary rect { fill: var(--bs-primary-light); stroke: var(--bs-primary); }
    .guide-stock-flow .flow-node-success rect { fill: var(--bs-success-light); stroke: var(--bs-success); }
    .guide-stock-flow .flow-node-warning rect { fill: var(--bs-warning-light); stroke: var(--bs-warning); }
    .guide-stock-flow .flow-label { fill: var(--bs-gray-600); font-size: 13px; font-weight: 600; }
    .guide-workflow { padding: 0; }
    .guide-workflow-header { display: flex; flex-direction: column; gap: .3rem; padding: 1rem 1.2rem; border-bottom: 1px solid var(--bs-gray-300); background: var(--bs-gray-100); }
    .guide-workflow-header strong { color: var(--bs-gray-900); font-size: 1.05rem; }
    .guide-workflow-header > span:last-child { color: var(--bs-gray-700); font-size: .9rem; }
    .guide-workflow-kicker { color: var(--bs-primary); font-size: .75rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; }
    .guide-workflow-body { padding: 1.15rem; }
    .guide-workflow-lane + .guide-workflow-lane { margin-top: 1.25rem; padding-top: 1.25rem; border-top: 1px dashed var(--bs-gray-300); }
    .guide-workflow-lane-label { display: inline-block; margin-bottom: .7rem; font-size: .78rem; font-weight: 700; letter-spacing: .03em; text-transform: uppercase; }
    .guide-workflow-track { display: flex; align-items: stretch; gap: 2.25rem; width: max-content; min-width: 100%; margin: 0; padding: 0; list-style: none; }
    .guide-workflow-step { position: relative; display: flex; width: 178px; min-width: 178px; margin: 0; }
    .guide-workflow-step:not(:last-child)::after { content: "\2192"; position: absolute; top: 50%; right: -1.65rem; color: var(--bs-gray-500); font-size: 1.3rem; font-weight: 700; transform: translateY(-50%); }
    .guide-workflow-node { display: flex; flex: 1; flex-direction: column; gap: .35rem; min-height: 116px; padding: .85rem; border: 1px solid; border-radius: .7rem; background: var(--bs-body-bg); box-shadow: 0 .2rem .65rem rgba(15, 23, 42, .06); }
    .guide-workflow-node strong { color: var(--bs-gray-900); font-size: .9rem; line-height: 1.35; }
    .guide-workflow-node small { color: var(--bs-gray-700); font-size: .78rem; line-height: 1.45; }
    .guide-workflow-number { display: inline-flex; align-items: center; justify-content: center; width: 1.7rem; height: 1.7rem; border-radius: 50%; font-size: .75rem; font-weight: 800; }
    .guide-workflow-step-decision .guide-workflow-node { border-style: dashed; }
    .guide-workflow-step-result .guide-workflow-node { border-width: 2px; }
    .guide-toc { max-height: calc(100vh - 160px); overflow-y: auto; }
    .guide-toc a { display: block; padding: .4rem .65rem; border-radius: .4rem; color: var(--bs-gray-700); }
    .guide-toc a:hover { color: var(--bs-primary); background: var(--bs-primary-light); }
    .guide-toc .toc-level-3 { padding-left: 1.45rem; font-size: .9rem; }
    .guide-toc .toc-level-4 { padding-left: 2.2rem; font-size: .85rem; }
    @media (max-width: 767.98px) { .guide-diagram:not(.guide-workflow) { padding: .75rem; } .guide-workflow-body { padding: .85rem; } }
    @media print { .app-header, .app-sidebar, .app-toolbar, .guide-sidebar, .btn { display: none !important; } .app-main { margin: 0 !important; } .guide-content h1 { display: block; } .guide-diagram { overflow: visible; break-inside: avoid; } .guide-stock-flow { min-width: 0; } .guide-workflow-track { flex-wrap: wrap; width: 100%; } .guide-workflow-step:not(:last-child)::after { display: none; } }
</style>
@endpush

@section('content')
    <x-metronic.page-title :title="$guide['title']" :description="$guide['description']" />

    <div class="alert alert-light-{{ $guide['color'] }} d-flex align-items-start gap-4 mb-6">
        <i class="{{ $guide['icon'] }} fs-1 text-{{ $guide['color'] }}"></i>
        <div>
            <div class="fw-bold mb-1">Panduan sesuai akses akun</div>
            <div class="text-muted">Role aktif: {{ collect($userRoles)->pluck('label')->implode(', ') ?: 'Belum ada role' }}. Estimasi membaca lengkap: {{ $rendered['reading_minutes'] }} menit.</div>
        </div>
    </div>

    <div class="row g-6">
        <div class="col-xl-3 guide-sidebar">
            <div class="card card-flush position-sticky" style="top: 90px;">
                <div class="card-header"><h3 class="card-title fs-5">Daftar Isi</h3></div>
                <div class="card-body pt-0 guide-toc">
                    @foreach ($rendered['toc'] as $section)
                        <a href="#{{ $section['id'] }}" class="toc-level-{{ $section['level'] }}">{{ $section['title'] }}</a>
                    @endforeach
                </div>
            </div>

            <div class="card card-flush mt-5">
                <div class="card-header"><h3 class="card-title fs-6">Panduan Tersedia</h3></div>
                <div class="card-body pt-0">
                    @foreach ($availableGuides as $availableGuide)
                        <a href="{{ route('guides.show', $availableGuide['slug']) }}" class="d-flex align-items-center gap-2 py-2 {{ $availableGuide['slug'] === $guide['slug'] ? 'text-primary fw-bold' : 'text-gray-700' }}">
                            <i class="{{ $availableGuide['icon'] }}"></i> {{ $availableGuide['short_title'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="col-xl-9">
            <x-metronic.card>
                <article class="guide-content">{!! $rendered['html'] !!}</article>
            </x-metronic.card>
        </div>
    </div>
@endsection
