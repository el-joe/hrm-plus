{{--
    Toast stack (400 wide, top-end, 24 from the edge, below the top bar).
    Server flash: session('status') renders as a success toast and session('error') as a danger toast
    (works without JS; resources/js/ui/toast.js adds auto-dismiss and the toast() API).
--}}
@props(['offset' => 'top-20'])

<div {{ $attributes->class('pointer-events-none fixed inset-x-4 z-[60] flex flex-col items-end gap-3 sm:inset-x-auto sm:end-6 sm:w-100 '.$offset) }}
    data-toast-region aria-live="polite" aria-relevant="additions">
    @if (session('status'))
        <x-toast tone="success">{{ session('status') }}</x-toast>
    @endif
    @if (session('error'))
        <x-toast tone="danger">{{ session('error') }}</x-toast>
    @endif
</div>

<template data-toast-template>
    <x-toast tone="info"></x-toast>
</template>
