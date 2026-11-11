@extends('layouts.admin', ['subtitle' => __('structure.org_chart.subtitle')])

@section('title', __('structure.org_chart.title'))

@section('content')
    @if ($canViewPositions || $canViewPeople)
        <x-tabs :label="__('structure.org_chart.views')" :items="array_values(array_filter([
            ['label' => __('structure.org_chart.view_departments'), 'url' => route('admin.org-chart'), 'active' => $view === 'departments'],
            $canViewPositions ? ['label' => __('structure.org_chart.view_positions'), 'url' => route('admin.org-chart', ['view' => 'positions']), 'active' => $view === 'positions'] : null,
            $canViewPeople ? ['label' => __('structure.org_chart.view_people'), 'url' => route('admin.org-chart', ['view' => 'people']), 'active' => $view === 'people'] : null,
        ]))" />
    @endif

    <x-card padding="none" data-org-chart>
        <div class="flex flex-col gap-3 border-b border-border-subtle p-4 md:flex-row md:items-center md:justify-between">
            <p class="text-body-sm text-text-secondary">
                {{ trans_choice('structure.org_chart.summary', $summary['nodes'], ['count' => $summary['nodes']]) }}
                @if ($summary['depth'] > 0)
                    · {{ trans_choice('structure.org_chart.levels', $summary['depth'], ['count' => $summary['depth']]) }}
                @endif
            </p>

            @if ($nodes !== [])
                <div class="flex flex-wrap items-center gap-2" hidden data-js-only>
                    <x-form.input name="org_search" id="org-search" type="search" icon="search" :label="__('structure.org_chart.search')" hide-label
                        :placeholder="__('structure.org_chart.search_placeholder')" class="md:w-56" data-org-search autocomplete="off" />
                    <x-button type="button" variant="ghost" size="sm" data-org-expand>{{ __('structure.org_chart.expand_all') }}</x-button>
                    <x-button type="button" variant="ghost" size="sm" data-org-collapse>{{ __('structure.org_chart.collapse_all') }}</x-button>
                    <div class="flex items-center gap-1 border-s border-border-subtle ps-2">
                        <x-icon-button icon="zoom-out" size="sm" :label="__('structure.org_chart.zoom_out')" data-org-zoom="out" />
                        <x-icon-button icon="maximize" size="sm" :label="__('structure.org_chart.reset_zoom')" data-org-zoom="reset" />
                        <x-icon-button icon="zoom-in" size="sm" :label="__('structure.org_chart.zoom_in')" data-org-zoom="in" />
                    </div>
                </div>
            @endif
        </div>

        @if ($nodes === [])
            <x-empty-state icon="sitemap" :title="__('structure.org_chart.empty_title')"
                :description="__('structure.org_chart.empty_'.$view)" />
        @else
            <div class="overflow-auto p-6" data-org-viewport>
                <div class="org-tree" data-org-canvas role="region" aria-label="{{ __('structure.org_chart.chart_label') }}">
                    <ul>
                        @foreach ($nodes as $node)
                            @include('admin.structure.org-chart._node', ['node' => $node])
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </x-card>
@endsection
