{{--
    One dashboard widget (App\Services\Dashboard\DashboardWidgets data). Sizes: sm = one cell,
    md = two cells, lg = full row on wide screens. Every figure comes from the controller.
--}}
@php
    $data = $widget['data'];
    $span = ['sm' => '', 'md' => 'sm:col-span-2', 'lg' => 'sm:col-span-2 xl:col-span-4'][$widget['size']] ?? '';
    $scopeHint = in_array('date', $widget['filters'], true) ? null : __('dashboard.hints.as_of_today');
@endphp

@if ($data['type'] === 'stat')
    <x-stat-card :label="$widget['title']" :value="$data['value']" :icon="$widget['icon']" :href="$data['url'] ?? null" :hint="$data['hint'] ?? null"
        data-widget="{{ $widget['key'] }}" data-stat="{{ $widget['key'] }}" />
@else
    <x-card :title="$widget['title']" :subtitle="$data['hint'] ?? null" @class([$span, 'min-w-0']) data-widget="{{ $widget['key'] }}">
        @if ($data['url'] ?? null)
            <x-slot:actions>
                <x-button variant="ghost" size="sm" :href="$data['url']" icon-end="chevron-right">{{ __('dashboard.actions.details') }}</x-button>
            </x-slot:actions>
        @endif

        @if ($data['type'] === 'list')
            <p class="mb-3 text-numeric-lg text-text-primary" data-widget-count>{{ $data['count'] }}</p>
            @if ($data['items'] === [])
                <p class="text-body-sm text-text-tertiary">{{ __('dashboard.empty.none') }}</p>
            @else
                <ul class="divide-y divide-border-subtle">
                    @foreach ($data['items'] as $item)
                        <li class="flex items-center gap-3 py-2">
                            <x-avatar :name="$item['label']" size="sm" />
                            <div class="min-w-0 flex-1">
                                @if ($item['url'] ?? null)
                                    <a href="{{ $item['url'] }}" class="block truncate text-label-md text-text-primary hover:text-text-link">{{ $item['label'] }}</a>
                                @else
                                    <p class="truncate text-label-md text-text-primary">{{ $item['label'] }}</p>
                                @endif
                                <p class="truncate text-caption-md text-text-tertiary" dir="auto">{{ $item['meta'] }}</p>
                            </div>
                            @if (filled($item['value'] ?? null))
                                <span class="shrink-0 text-caption-md tabular-nums text-text-secondary" dir="ltr">{{ $item['value'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        @elseif ($data['type'] === 'kpis')
            @if ($data['items'] === [])
                <p class="text-body-sm text-text-tertiary">{{ $data['hint'] ?? __('dashboard.empty.none') }}</p>
            @else
                <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($data['items'] as $item)
                        <div class="rounded-lg bg-surface-subtle p-3">
                            <dt class="text-caption-md text-text-secondary">{{ $item['label'] }}</dt>
                            <dd class="mt-1 text-numeric-md text-text-primary tabular-nums" dir="ltr">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        @elseif ($data['type'] === 'chart')
            @if (! empty($data['summary']))
                <dl class="mb-3 flex flex-wrap gap-x-6 gap-y-2">
                    @foreach ($data['summary'] as $item)
                        <div>
                            <dt class="text-caption-md text-text-secondary">{{ $item['label'] }}</dt>
                            <dd class="text-label-lg tabular-nums text-text-primary" dir="ltr">{{ $item['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
            @if (\App\Support\Charts\ChartData::isEmpty($data['chart']))
                <p class="text-body-sm text-text-tertiary">{{ __('dashboard.empty.no_data') }}</p>
            @else
                <x-chart :chart="$data['chart']" :label="$widget['title']" :height="$widget['size'] === 'lg' ? '16rem' : '14rem'" />
            @endif
        @endif

        @if ($scopeHint && $data['type'] !== 'list')
            <x-slot:footer><span class="text-caption-md text-text-tertiary">{{ $scopeHint }}</span></x-slot:footer>
        @endif
    </x-card>
@endif
