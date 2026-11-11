{{--
    Data table wrapper: radius 12, 1px border/default, header 44, rows 56, cell padding 16.
    Rows stack into cards below md (cells need :label, see x-table.td).
    Slots: head (the <tr> of x-table.th), default (the <tr> rows of the body), footer (pagination).
    Props: stack (default true), caption (screen-reader caption).
--}}
@props(['stack' => true, 'caption' => null])

<div {{ $attributes->class('overflow-hidden rounded-lg border border-border-default bg-surface-default') }} data-table>
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-start" @if ($stack) data-table-stack @endif>
            @if ($caption)
                <caption class="sr-only">{{ $caption }}</caption>
            @endif
            @isset($head)
                <thead class="bg-surface-subtle">
                    {{ $head }}
                </thead>
            @endisset
            <tbody class="divide-y divide-border-subtle">
                {{ $slot }}
            </tbody>
        </table>
    </div>
    @isset($footer)
        @if (! $footer->isEmpty())
            <div class="border-t border-border-default px-4 py-3">{{ $footer }}</div>
        @endif
    @endisset
</div>
