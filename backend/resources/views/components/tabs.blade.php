{{--
    Tabs. Two modes:
    - Link tabs (server-routed): items = [['label' => .., 'url' => .., 'active' => bool]].
    - Client tabs: items = [['label' => .., 'id' => 'panel-id']] and panels rendered by the page
      as <div id="panel-id" data-tab-panel>...</div>. resources/js/ui/tabs.js switches them; without
      JS every panel stays visible.
--}}
@props(['items' => [], 'label' => null])

@php
    $isClient = isset($items[0]['id']);
@endphp

<div {{ $attributes->class('overflow-x-auto overflow-y-hidden shadow-[inset_0_-1px_0_var(--color-border-default)]') }} @if ($isClient) data-tabs @endif>
    <div @if ($isClient) role="tablist" @endif aria-label="{{ $label }}" class="flex min-w-max gap-1">
        @foreach ($items as $index => $item)
            @php
                $active = $isClient ? $index === 0 : (bool) ($item['active'] ?? false);
                $classes = 'inline-flex h-11 items-center gap-2 border-b-2 px-3 text-label-md transition-colors '
                    .($active ? 'border-action-primary-default text-text-link' : 'border-transparent text-text-secondary hover:text-text-primary hover:border-border-strong');
            @endphp
            @if ($isClient)
                <button type="button" role="tab" id="{{ $item['id'] }}-tab" aria-controls="{{ $item['id'] }}"
                    aria-selected="{{ $active ? 'true' : 'false' }}" tabindex="{{ $active ? '0' : '-1' }}" class="{{ $classes }}" data-tab>
                    {{ $item['label'] }}
                </button>
            @else
                <a href="{{ $item['url'] }}" class="{{ $classes }}" @if ($active) aria-current="page" @endif>{{ $item['label'] }}</a>
            @endif
        @endforeach
    </div>
</div>
