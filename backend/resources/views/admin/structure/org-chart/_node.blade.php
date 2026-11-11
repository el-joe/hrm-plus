{{--
    One node of the organisation chart and, recursively, its children.
    $node: see App\Services\Structure\OrgChartBuilder (key, title, code, subtitle, url, active,
    lead, meta, people, children). `people` is filled by Phase 3b (position holders).
--}}
@php($hasChildren = $node['children'] !== [])
<li data-org-node data-org-search="{{ mb_strtolower($node['code'].' '.$node['title'].' '.($node['subtitle'] ?? '')) }}">
    <div @class([
        'org-node relative flex w-60 flex-col gap-2 rounded-lg border bg-surface-default p-3 text-start shadow-xs transition-shadow',
        'border-border-subtle' => $node['active'],
        'border-dashed border-border-strong opacity-70' => ! $node['active'],
    ]) data-org-card>
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                @if ($node['url'])
                    <a href="{{ $node['url'] }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $node['title'] }}</a>
                @else
                    <span class="block text-label-md text-text-primary">{{ $node['title'] }}</span>
                @endif
                <span class="block truncate text-caption-sm text-text-tertiary"><span dir="ltr">{{ $node['code'] }}</span>@if ($node['subtitle']) · {{ $node['subtitle'] }}@endif</span>
            </div>
            @unless ($node['active'])
                <x-badge class="shrink-0">{{ __('structure.org_chart.inactive') }}</x-badge>
            @endunless
        </div>

        @if ($node['lead'])
            <p class="flex items-center gap-1.5 text-caption-md text-text-secondary">
                <x-icon name="user" size="sm" class="shrink-0 text-text-tertiary" />
                <span class="truncate">{{ __('structure.org_chart.head') }}: {{ $node['lead'] }}</span>
            </p>
        @endif

        @if ($node['people'] !== [])
            <ul class="flex flex-col gap-1.5" aria-label="{{ $node['title'] }}">
                @foreach ($node['people'] as $person)
                    <li class="flex items-center gap-2">
                        <x-avatar :name="$person['name']" :src="$person['avatar'] ?? null" size="sm" />
                        <span class="min-w-0">
                            @if (! empty($person['url']))
                                <a href="{{ $person['url'] }}" class="block truncate text-label-sm text-text-primary hover:text-text-link">{{ $person['name'] }}</a>
                            @else
                                <span class="block truncate text-label-sm text-text-primary">{{ $person['name'] }}</span>
                            @endif
                            @if (! empty($person['subtitle']))
                                <span class="block truncate text-caption-sm text-text-tertiary">{{ $person['subtitle'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($node['meta'] !== [])
            <dl class="flex flex-wrap gap-1.5">
                @foreach ($node['meta'] as $meta)
                    <div class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2 py-0.5 text-caption-sm text-text-secondary">
                        <dt>{{ $meta['label'] }}</dt>
                        <dd class="text-label-sm text-text-primary tabular-nums">{{ $meta['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        @endif

        @if ($hasChildren)
            <button type="button" hidden data-js-only data-org-toggle aria-expanded="true"
                aria-label="{{ __('structure.org_chart.toggle', ['name' => $node['title']]) }}" title="{{ __('structure.org_chart.toggle', ['name' => $node['title']]) }}"
                class="absolute -bottom-3 start-1/2 inline-flex h-6 min-w-6 -translate-x-1/2 items-center justify-center gap-0.5 rounded-full border border-border-default bg-surface-default px-1.5 text-caption-sm text-text-secondary shadow-xs hover:text-text-primary rtl:translate-x-1/2">
                <x-icon name="chevron-up" size="sm" data-org-toggle-icon />
                <span class="tabular-nums">{{ count($node['children']) }}</span>
            </button>
        @endif
    </div>

    @if ($hasChildren)
        <ul data-org-children>
            @foreach ($node['children'] as $child)
                @include('admin.structure.org-chart._node', ['node' => $child])
            @endforeach
        </ul>
    @endif
</li>
