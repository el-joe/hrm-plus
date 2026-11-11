{{--
    Kanban board. Drag a card to another column, or use the stage select on the card
    (keyboard / no-JS). The server decides: on error the card returns to where it was.
--}}
<p class="sr-only" id="board-help">{{ __('recruitment.pipeline.board_help') }}</p>
<div class="relative -mx-4 overflow-x-auto px-4 pb-2 md:mx-0 md:px-0" data-pipeline-board aria-describedby="board-help">
    <div class="flex min-w-max gap-3">
        @foreach ($columns as $column)
            @php($stage = $column['stage'])
            <section class="flex w-72 shrink-0 flex-col rounded-xl border border-border-subtle bg-surface-subtle" data-stage-column data-stage-id="{{ $stage->id }}"
                data-stage-category="{{ $stage->category->value }}" aria-labelledby="stage-{{ $stage->id }}-title">
                <header class="flex items-center justify-between gap-2 border-b border-border-subtle px-3 py-2.5">
                    <h2 id="stage-{{ $stage->id }}-title" class="truncate text-label-md text-text-primary">{{ $stage->name }}</h2>
                    <span class="rounded-full bg-surface-muted px-2 text-label-sm tabular-nums text-text-secondary" data-stage-count>{{ $column['applications']->count() }}</span>
                </header>
                <ol class="flex min-h-28 flex-1 flex-col gap-2 p-2" data-stage-list data-stage-id="{{ $stage->id }}">
                    @foreach ($column['applications'] as $application)
                        @include('admin.recruitment.vacancies._card')
                    @endforeach
                </ol>
            </section>
        @endforeach
    </div>
</div>
