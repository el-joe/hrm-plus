{{--
    Pagination with a results summary (items 36x36, gap 6).
    Props: paginator (LengthAwarePaginator; call ->withQueryString() in the controller so filters stay).
--}}
@props(['paginator'])

@if ($paginator->total() > 0)
    <nav {{ $attributes->class('flex flex-col-reverse items-center justify-between gap-3 sm:flex-row') }} aria-label="{{ __('app.ui.pagination') }}" data-pagination>
        <p class="text-body-sm text-text-tertiary">
            {{ __('app.ui.showing_results', ['from' => $paginator->firstItem(), 'to' => $paginator->lastItem(), 'total' => $paginator->total()]) }}
        </p>

        @if ($paginator->hasPages())
            @php
                $links = array_slice($paginator->linkCollection()->all(), 1, -1);
                $item = 'inline-flex h-9 min-w-9 items-center justify-center rounded-md px-2 text-label-md';
            @endphp
            <ul class="flex flex-wrap items-center gap-1.5">
                <li>
                    @if ($paginator->onFirstPage())
                        <span class="{{ $item }} border border-border-subtle text-text-disabled" aria-disabled="true"><x-icon name="chevron-left" size="sm" flip /><span class="sr-only">{{ __('app.ui.previous') }}</span></span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="{{ $item }} border border-border-default text-text-secondary hover:bg-action-neutral-hover"><x-icon name="chevron-left" size="sm" flip /><span class="sr-only">{{ __('app.ui.previous') }}</span></a>
                    @endif
                </li>
                @foreach ($links as $link)
                    <li>
                        @if ($link['url'] === null)
                            <span class="{{ $item }} text-text-tertiary">{{ $link['label'] }}</span>
                        @elseif ($link['active'])
                            <span class="{{ $item }} bg-action-primary-default text-text-on-primary" aria-current="page">{{ $link['label'] }}</span>
                        @else
                            <a href="{{ $link['url'] }}" class="{{ $item }} border border-border-default text-text-secondary hover:bg-action-neutral-hover">{{ $link['label'] }}</a>
                        @endif
                    </li>
                @endforeach
                <li>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="{{ $item }} border border-border-default text-text-secondary hover:bg-action-neutral-hover"><x-icon name="chevron-right" size="sm" flip /><span class="sr-only">{{ __('app.ui.next') }}</span></a>
                    @else
                        <span class="{{ $item }} border border-border-subtle text-text-disabled" aria-disabled="true"><x-icon name="chevron-right" size="sm" flip /><span class="sr-only">{{ __('app.ui.next') }}</span></span>
                    @endif
                </li>
            </ul>
        @endif
    </nav>
@endif
