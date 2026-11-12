@extends('layouts.employee')

@section('title', __('performance.portal.title'))

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('performance.portal.active_goals')" :value="$summary['active_goals']" icon="target" />
        <x-stat-card :label="__('performance.portal.average_progress')" :value="(rtrim(rtrim($summary['average_progress'], '0'), '.') ?: '0').'%'" icon="trend-up" />
        <x-stat-card :label="__('performance.portal.open_reviews')" :value="$summary['open_reviews']" icon="pencil" :hint="$summary['self_assessment_due'] ? __('performance.portal.self_due', ['count' => $summary['self_assessment_due']]) : null" />
        <x-stat-card :label="__('performance.portal.latest_rating')" :value="$summary['latest_rating']['label'] ?? '—'" icon="award" :hint="$summary['latest_rating']['cycle'] ?? null" />
    </div>

    @foreach ($reviews->where('status', App\Enums\PerformanceReviewStatus::SelfAssessment) as $review)
        <x-alert tone="info" :title="__('performance.portal.self_open_title', ['cycle' => $review->cycle->name])">
            {{ $review->cycle->self_assessment_due ? __('performance.portal.self_open_due', ['date' => $review->cycle->self_assessment_due->toDateString()]) : __('performance.portal.self_open') }}
            <a class="ms-1 text-text-link hover:underline" href="{{ route('portal.performance.reviews.show', $review->id) }}">{{ __('performance.portal.start_self') }}</a>
        </x-alert>
    @endforeach
    @foreach ($reviews->where('status', App\Enums\PerformanceReviewStatus::Acknowledgement) as $review)
        <x-alert tone="warning" :title="__('performance.portal.ack_title', ['cycle' => $review->cycle->name])">
            {{ __('performance.portal.ack_help') }}
            <a class="ms-1 text-text-link hover:underline" href="{{ route('portal.performance.reviews.show', $review->id) }}">{{ __('performance.portal.open_review') }}</a>
        </x-alert>
    @endforeach

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('performance.portal.my_goals')" padding="none" data-region="my-goals">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($goals as $goal)
                        <li class="flex flex-col gap-3 px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-label-md text-text-primary">{{ $goal->title }}</p>
                                    <p class="text-caption-md text-text-tertiary">
                                        {{ $goal->type->label() }}@if ($goal->cycle) · {{ $goal->cycle->name }}@endif
                                        · {{ __('performance.common.weight_pct', ['weight' => rtrim(rtrim((string) $goal->weight, '0'), '.')]) }}
                                        @if ($goal->due_date) · {{ __('performance.portal.due', ['date' => $goal->due_date->toDateString()]) }}@endif
                                    </p>
                                    @if ($goal->isKpi() && $goal->target_value !== null)
                                        <p class="text-body-sm text-text-secondary">{{ __('performance.goals.kpi_line', ['current' => $goal->current_value ?? '0', 'target' => $goal->target_value, 'unit' => $goal->unit]) }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    @if ($goal->isOverdue()) <x-badge tone="danger">{{ __('performance.goals.overdue') }}</x-badge> @endif
                                    <x-badge :tone="$goal->status->tone()">{{ $goal->status->label() }}</x-badge>
                                </div>
                            </div>
                            <div class="max-w-md">@include('admin.performance._progress', ['value' => $goal->progress, 'label' => $goal->title])</div>
                            @if ($goal->status->value === 'active' && $portal->canEditGoal($goal))
                                <form method="POST" action="{{ route('portal.performance.goals.progress', $goal->id) }}" class="grid gap-2 sm:grid-cols-[10rem_1fr_auto] sm:items-end" data-validate>
                                    @csrf
                                    @if ($goal->isKpi())
                                        <x-form.input name="current_value" :id="'goal-current-'.$goal->id" type="number" step="0.01" min="0" dir="ltr" required :label="__('performance.fields.current_value')" :value="$goal->current_value" />
                                    @else
                                        <x-form.input name="progress" :id="'goal-progress-'.$goal->id" type="number" step="1" min="0" max="100" dir="ltr" required :label="__('performance.fields.progress_pct')" :value="rtrim(rtrim((string) $goal->progress, '0'), '.')" />
                                    @endif
                                    <x-form.input name="note" :id="'goal-note-'.$goal->id" maxlength="1000" :label="__('performance.fields.note')" />
                                    <x-button variant="secondary" size="md" icon="check">{{ __('performance.goals.record_progress') }}</x-button>
                                </form>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-6"><x-empty-state icon="target" :title="__('performance.portal.no_goals')" /></li>
                    @endforelse
                </ul>
                <x-slot:footer>
                    <x-pagination :paginator="$goals" />
                </x-slot:footer>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('performance.portal.my_reviews')" padding="none" data-region="my-reviews">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($reviews as $review)
                        <li class="flex flex-col gap-1 px-5 py-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <a href="{{ route('portal.performance.reviews.show', $review->id) }}" class="text-label-md text-text-link hover:underline">{{ $review->cycle->name }}</a>
                                @include('admin.performance._review-status', ['status' => $review->status])
                            </div>
                            <p class="text-caption-md text-text-tertiary">{{ $review->cycle->type->label() }} · <span dir="ltr">{{ $review->cycle->period_start->toDateString() }} → {{ $review->cycle->period_end->toDateString() }}</span></p>
                            @if ($review->status->isFinal())
                                <div class="text-body-sm">@include('admin.performance._rating', ['level' => $review->finalRating])</div>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-6"><x-empty-state icon="award" :title="__('performance.portal.no_reviews')" /></li>
                    @endforelse
                </ul>
            </x-card>

            @if ($canAddGoals && $openCycles->isNotEmpty())
                <x-card :title="__('performance.portal.add_goal')" :subtitle="__('performance.portal.add_goal_help')" data-region="add-goal">
                    <form method="POST" action="{{ route('portal.performance.goals.store') }}" class="grid gap-3" data-validate>
                        @csrf
                        @include('admin.performance._goal-fields', ['goal' => new App\Models\PerformanceGoal(['type' => App\Enums\GoalType::Goal, 'weight' => '0', 'performance_cycle_id' => $openCycles->first()->id]), 'cycles' => $openCycles, 'cycleRequired' => true])
                        <x-button icon="plus">{{ __('performance.portal.add_goal') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
@endsection
