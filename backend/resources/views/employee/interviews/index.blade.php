@extends('layouts.employee')

@section('title', __('recruitment.portal.title'))

@section('content')
    <x-card padding="none">
        <ul class="divide-y divide-border-subtle">
            @forelse ($interviews as $interview)
                <li>
                    <a href="{{ route('portal.interviews.show', $interview) }}" class="flex flex-col gap-1 px-5 py-4 hover:bg-surface-subtle sm:flex-row sm:items-center sm:gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="text-label-md">{{ $interview->application->candidate->name }} — {{ $interview->type->label() }}</p>
                            <p class="text-caption-md text-text-tertiary">{{ $interview->application->vacancy->title }} · <span dir="ltr">{{ $interview->localStart()->format('Y-m-d H:i') }} ({{ $interview->timezone }})</span></p>
                        </div>
                        @include('admin.recruitment._badge', ['value' => $interview->status])
                        @if ($interview->scorecards->isNotEmpty())
                            <x-badge tone="success">{{ __('recruitment.portal.scored') }}</x-badge>
                        @endif
                    </a>
                </li>
            @empty
                <li><x-empty-state icon="calendar" :title="__('recruitment.portal.none')" /></li>
            @endforelse
        </ul>
    </x-card>
    <x-pagination :paginator="$interviews" />
@endsection
