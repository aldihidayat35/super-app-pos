@props(['title', 'description' => null, 'help' => null])

{{--
    Jembatan metadata untuk pemanggilan <x-metronic.page-title> yang sudah ada.
    Header visual hanya dirender sekali oleh toolbar layout melalui page-header.
--}}
@unless ($__env->hasSection('page_title'))
    @section('page_title', $title)
@endunless

@if (filled($description) && ! $__env->hasSection('page_description'))
    @section('page_description', $description)
@endif

@if (filled($help) && ! $__env->hasSection('page_title_help'))
    @section('page_title_help')
        @include('admin.roles._help-icon', ['text' => $help])
    @endsection
@endif

@php
    $pageTitleActions = isset($actions) && filled(trim((string) $actions))
        ? $actions
        : $slot;
@endphp

@if (filled(trim((string) $pageTitleActions)))
    @section('toolbar_actions')
        {{ $pageTitleActions }}
    @append
@endif
