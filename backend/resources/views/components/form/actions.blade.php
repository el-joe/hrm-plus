{{--
    Sticky form action bar (72 high, horizontal padding 32, top border, shadow lg).
    Put it as the last child of the <form>. Slot: buttons (primary last = end side).
--}}
<div {{ $attributes->class('sticky bottom-0 z-20 -mx-4 mt-2 flex min-h-18 items-center justify-end gap-3 border-t border-border-default bg-surface-default px-4 py-3 shadow-lg lg:-mx-8 lg:px-8') }} data-form-actions>
    {{ $slot }}
</div>
