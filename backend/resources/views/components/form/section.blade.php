{{--
    Form section card: intro column (260, title + description) and a 2-column field grid
    (column gap 24, row gap 20). Props: title, description, columns (1 | 2).
--}}
@props(['title' => null, 'description' => null, 'columns' => 2])

<section {{ $attributes->class('rounded-xl border border-border-subtle bg-surface-default p-4 shadow-xs sm:p-6') }}>
    <div class="flex flex-col gap-4 lg:flex-row lg:gap-8">
        @if ($title || $description)
            <div class="shrink-0 lg:w-65">
                @if ($title)
                    <h2 class="text-h5 text-text-primary">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-1 text-body-sm text-text-tertiary">{{ $description }}</p>
                @endif
            </div>
        @endif
        <div @class(['grid min-w-0 flex-1 gap-x-6 gap-y-5', 'md:grid-cols-2' => (int) $columns === 2])>
            {{ $slot }}
        </div>
    </div>
</section>
