{{--
    Breadcrumb trail. Props: items — list of ['label' => string, 'url' => ?string]; the last item is the current page.
--}}
@props(['items' => []])

@if (count($items) > 0)
    <nav aria-label="{{ __('app.ui.breadcrumbs') }}" {{ $attributes }}>
        <ol class="flex flex-wrap items-center gap-1.5 text-body-sm text-text-tertiary">
            @foreach ($items as $item)
                <li class="flex items-center gap-1.5">
                    @if (! $loop->first)
                        <x-icon name="chevron-right" size="sm" flip class="text-text-disabled" />
                    @endif
                    @if (! $loop->last && ! empty($item['url']))
                        <a href="{{ $item['url'] }}" class="inline-flex items-center gap-1 hover:text-text-primary">
                            @if ($loop->first) <x-icon name="home" size="sm" /> @endif
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span @if ($loop->last) aria-current="page" class="text-text-primary" @endif>{{ $item['label'] }}</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </nav>
@endif
