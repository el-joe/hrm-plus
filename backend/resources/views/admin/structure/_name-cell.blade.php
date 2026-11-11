{{-- Name + code cell. Variables: $record; optional $url, $sub (secondary line). --}}
<div class="min-w-0">
    @if (! empty($url))
        <a href="{{ $url }}" class="block truncate text-label-md text-text-primary hover:text-text-link">{{ $record->name }}</a>
    @else
        <span class="block truncate text-label-md text-text-primary">{{ $record->name }}</span>
    @endif
    <p class="truncate text-caption-md text-text-tertiary"><span dir="ltr">{{ $record->code }}</span>@isset($sub) · {{ $sub }}@endisset</p>
</div>
