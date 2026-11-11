{{--
    Body cell. Props: label (column name, shown before the value when rows stack on mobile).
--}}
@props(['label' => null])

<td @if ($label) data-label="{{ $label }}" @endif {{ $attributes->class('h-14 px-4 py-2 align-middle text-body-md text-text-primary') }}>{{ $slot }}</td>
