{{--
    Header cell. For a server-sortable column pass sort="column": the cell links to
    the current URL with ?sort=column&direction=asc|desc (page reset), so sorting
    works without JavaScript. The controller must whitelist the column.
--}}
@props(['sort' => null])

@php
    $currentSort = request()->query('sort');
    $currentDirection = request()->query('direction') === 'desc' ? 'desc' : 'asc';
    $isSorted = $sort !== null && $currentSort === $sort;
    $nextDirection = $isSorted && $currentDirection === 'asc' ? 'desc' : 'asc';
@endphp

<th scope="col" @if ($isSorted) aria-sort="{{ $currentDirection === 'asc' ? 'ascending' : 'descending' }}" @endif
    {{ $attributes->class('h-11 whitespace-nowrap px-4 text-start text-label-sm text-text-secondary') }}>
    @if ($sort)
        <a href="{{ request()->fullUrlWithQuery(['sort' => $sort, 'direction' => $nextDirection, 'page' => null]) }}"
            class="inline-flex items-center gap-1 hover:text-text-primary" data-sort-link="{{ $sort }}">
            {{ $slot }}
            <x-icon :name="$isSorted ? ($currentDirection === 'asc' ? 'arrow-up' : 'arrow-down') : 'chevrons-up-down'" size="sm" class="text-text-tertiary" />
        </a>
    @else
        {{ $slot }}
    @endif
</th>
