{{-- One application on the pipeline board. Variables: $application, $stages, $canMove --}}
<li class="relative rounded-lg border border-border-subtle bg-surface-default p-3 shadow-xs" data-application-card data-application-id="{{ $application->id }}"
    data-move-url="{{ route('admin.applications.move', $application) }}" @if ($canMove && $application->isActive()) draggable="true" @endif
    aria-label="{{ $application->candidate->name }}">
    <div class="flex items-start gap-2">
        <x-avatar :name="$application->candidate->name" size="sm" />
        <div class="min-w-0 flex-1">
            <a href="{{ route('admin.applications.show', $application) }}" class="block truncate text-label-md text-text-primary hover:text-text-link">{{ $application->candidate->name }}</a>
            <p class="truncate text-caption-md text-text-tertiary">{{ $application->candidate->current_job_title ?? $application->source->label() }}</p>
        </div>
        @if ($application->average_score !== null)
            <span class="shrink-0 rounded-sm bg-surface-muted px-1.5 text-label-sm tabular-nums text-text-secondary" title="{{ __('recruitment.fields.average_score') }}">{{ $application->average_score }}</span>
        @endif
    </div>
    <div class="mt-2 flex flex-wrap items-center gap-1.5 text-caption-md text-text-tertiary">
        <span title="{{ __('recruitment.fields.applied_at') }}"><x-icon name="calendar" size="sm" class="inline" /> <span dir="ltr">{{ $application->applied_at->toDateString() }}</span></span>
        @if ($application->interviews_count)
            <span>· {{ trans_choice('recruitment.pipeline.interviews_count', $application->interviews_count, ['count' => $application->interviews_count]) }}</span>
        @endif
        @if ($application->latestOffer)
            @include('admin.recruitment._badge', ['value' => $application->latestOffer->status])
        @endif
        @foreach ($application->candidate->tags->take(3) as $tag)
            <x-badge>{{ $tag->name }}</x-badge>
        @endforeach
    </div>
    @if ($canMove && $application->isActive())
        <div class="mt-2">@include('admin.recruitment.vacancies._move-form')</div>
    @endif
</li>
