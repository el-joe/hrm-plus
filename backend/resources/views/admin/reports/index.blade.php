@extends('layouts.admin', ['subtitle' => __('reports.index.subtitle')])

@section('title', __('reports.index.title'))

@section('page-actions')
    @can('reports.export')
        <x-button variant="secondary" icon="download" :href="route('admin.reports.exports.index')">{{ __('reports.exports.title') }}</x-button>
    @endcan
@endsection

@php
    $areaIcons = ['employees' => 'users', 'attendance' => 'clock', 'leave' => 'calendar', 'payroll' => 'coins', 'recruitment' => 'briefcase',
        'performance' => 'award', 'requests' => 'inbox', 'assets' => 'laptop', 'disciplinary' => 'shield'];
@endphp

@section('content')
    @if ($catalogue->isEmpty())
        <x-empty-state icon="file-text" :title="__('reports.index.empty')" />
    @endif

    @foreach ($catalogue as $area => $reports)
        <section aria-labelledby="area-{{ $area }}" class="flex flex-col gap-3" data-report-area="{{ $area }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="area-{{ $area }}" class="flex items-center gap-2 text-h4 text-text-primary">
                    <x-icon :name="$areaIcons[$area] ?? 'file-text'" class="text-text-link" />
                    {{ __('reports.areas.'.$area) }}
                    <x-badge>{{ \App\Support\Formatter::number(count($reports)) }}</x-badge>
                </h2>
                @if (($legacy[$area] ?? null) !== null)
                    <x-button variant="ghost" size="sm" :href="$legacy[$area]" icon-end="chevron-right">{{ __('reports.index.module_reports') }}</x-button>
                @endif
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($reports as $report)
                    <a href="{{ route('admin.reports.show', $report->key()) }}" data-report="{{ $report->key() }}"
                        class="flex flex-col gap-1.5 rounded-xl border border-border-subtle bg-surface-default p-4 shadow-xs transition-shadow hover:shadow-sm focus-visible:shadow-focus">
                        <span class="flex items-center gap-2 text-label-lg text-text-primary"><x-icon name="file-text" size="sm" class="text-text-link" /> {{ $report->title() }}</span>
                        <span class="text-body-sm text-text-secondary">{{ $report->description() }}</span>
                        @if ($report->requires() !== [])
                            <span><x-badge tone="warning" dot>{{ __('reports.index.restricted') }}</x-badge></span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endforeach
@endsection
