{{-- Source badges of a day / event. Variables: $sources (list of source values). --}}
@foreach ($sources as $source)
    @php $source = $source instanceof \App\Enums\RawEventSource ? $source : \App\Enums\RawEventSource::from($source); @endphp
    <x-badge :tone="in_array($source->value, ['manual', 'import'], true) ? 'info' : 'neutral'">{{ $source->label() }}</x-badge>
@endforeach
