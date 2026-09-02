@php
    $pageTitle = new \Illuminate\Support\HtmlString(trim($__env->yieldContent('page_title', 'Dashboard')));
    $pageDescription = new \Illuminate\Support\HtmlString(trim($__env->yieldContent('page_description')));
    $pageActions = trim($__env->yieldContent('toolbar_actions'));
    $pageHelp = trim($__env->yieldContent('page_title_help').$__env->yieldContent('page_guide'));
@endphp

<div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-4">
    <div id="kt_app_toolbar_container" class="app-container container-fluid">
        <x-metronic.page-header :title="$pageTitle" :description="$pageDescription">
            @if ($pageHelp !== '')
                <x-slot:help>{!! $pageHelp !!}</x-slot:help>
            @endif

            @if ($pageActions !== '')
                <x-slot:actions>{!! $pageActions !!}</x-slot:actions>
            @endif
        </x-metronic.page-header>
    </div>
</div>
