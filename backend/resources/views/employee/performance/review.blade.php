@extends('layouts.employee', ['subtitle' => $review->cycle->type->label().' · '.$review->cycle->period_start->toDateString().' → '.$review->cycle->period_end->toDateString()])

@section('title', $review->cycle->name)

@section('page-actions')
    <x-button variant="secondary" icon="arrow-left" :href="route('portal.performance')">{{ __('performance.portal.back') }}</x-button>
@endsection

@section('content')
    @php
        $selfOpen = $review->status === App\Enums\PerformanceReviewStatus::SelfAssessment;
        $max = $levels->last()?->value;
    @endphp
    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <x-card>
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div class="flex flex-col gap-1">
                <div class="flex flex-wrap items-center gap-2">
                    @include('admin.performance._review-status', ['status' => $review->status])
                    <span class="text-caption-md text-text-tertiary" dir="ltr">{{ $review->reference }}</span>
                </div>
                <p class="text-body-sm text-text-secondary">{{ __('performance.portal.status_help.'.$review->status->value) }}</p>
                <p class="text-caption-md text-text-tertiary">{{ __('performance.fields.reviewer') }}: {{ $review->reviewer?->name ?? __('performance.reviews.no_reviewer') }}</p>
            </div>
            @if ($showManager)
                <div class="flex flex-col items-start gap-1 md:items-end">
                    <span class="text-caption-md text-text-tertiary">{{ __('performance.fields.final_rating') }}</span>
                    @include('admin.performance._rating', ['level' => $review->finalRating])
                    <span class="text-body-sm text-text-secondary">{{ __('performance.fields.overall_score') }}: @include('admin.performance._score', ['score' => $review->overall_score, 'max' => $max])</span>
                </div>
            @elseif ($review->self_overall_score !== null)
                <div class="flex flex-col items-start gap-1 md:items-end">
                    <span class="text-caption-md text-text-tertiary">{{ __('performance.fields.self_score') }}</span>
                    @include('admin.performance._score', ['score' => $review->self_overall_score, 'max' => $max])
                </div>
            @endif
        </div>
    </x-card>

    @if ($selfOpen)
        <form method="POST" action="{{ route('portal.performance.reviews.self', $review->id) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
            @csrf
            <x-alert tone="info">{{ __('performance.portal.self_instructions') }}</x-alert>
    @endif

    <x-card :title="__('performance.reviews.goals')" padding="none" data-region="portal-review-goals">
        <ul class="divide-y divide-border-subtle">
            @forelse ($goals->filter(fn ($goal) => $goal->status->counts()) as $goal)
                <li class="flex flex-col gap-3 px-5 py-4">
                    <div>
                        <p class="text-label-md text-text-primary">{{ $goal->title }}</p>
                        <p class="text-caption-md text-text-tertiary">{{ $goal->type->label() }} · {{ __('performance.common.weight_pct', ['weight' => rtrim(rtrim((string) $goal->weight, '0'), '.')]) }}</p>
                    </div>
                    <div class="max-w-md">@include('admin.performance._progress', ['value' => $goal->progress, 'label' => $goal->title])</div>
                    @if ($selfOpen)
                        <div class="grid gap-2 md:grid-cols-[14rem_1fr]">
                            @include('admin.performance._rating-select', ['name' => 'goals['.$goal->id.'][rating]', 'id' => 'goal-rating-'.$goal->id, 'label' => __('performance.reviews.self_rating').': '.$goal->title, 'selected' => old('goals.'.$goal->id.'.rating', $goal->self_rating), 'required' => true])
                            <x-form.input :name="'goals['.$goal->id.'][comment]'" :id="'goal-comment-'.$goal->id" :label="__('performance.reviews.comment')" hide-label maxlength="2000" :placeholder="__('performance.reviews.comment')" :value="old('goals.'.$goal->id.'.comment', $goal->self_comment)" />
                        </div>
                    @else
                        <p class="text-body-sm text-text-secondary">
                            <span class="text-text-tertiary">{{ __('performance.reviews.self_rating') }}:</span>
                            @include('admin.performance._rating-value', ['value' => $goal->self_rating])
                            @if ($goal->self_comment) — {{ $goal->self_comment }} @endif
                        </p>
                        @if ($showManager)
                            <p class="text-body-sm text-text-secondary">
                                <span class="text-text-tertiary">{{ __('performance.reviews.manager_rating') }}:</span>
                                @include('admin.performance._rating-value', ['value' => $goal->manager_rating])
                                @if ($goal->manager_comment) — {{ $goal->manager_comment }} @endif
                            </p>
                        @endif
                    @endif
                </li>
            @empty
                <li class="px-5 py-6"><x-empty-state icon="target" :title="__('performance.reviews.no_goals')" /></li>
            @endforelse
        </ul>
    </x-card>

    <x-card :title="__('performance.reviews.competencies')" padding="none" data-region="portal-review-competencies">
        <ul class="divide-y divide-border-subtle">
            @forelse ($review->competencies as $row)
                <li class="flex flex-col gap-3 px-5 py-4">
                    <div>
                        <p class="text-label-md text-text-primary">{{ $row->competency->name }}</p>
                        <p class="text-caption-md text-text-tertiary">{{ $row->competency->category->label() }} · {{ __('performance.common.weight_pct', ['weight' => rtrim(rtrim((string) $row->weight, '0'), '.')]) }}</p>
                        @if ($row->competency->description)
                            <p class="text-body-sm text-text-secondary">{{ $row->competency->description }}</p>
                        @endif
                    </div>
                    @if ($selfOpen)
                        <div class="grid gap-2 md:grid-cols-[14rem_1fr]">
                            @include('admin.performance._rating-select', ['name' => 'competencies['.$row->id.'][rating]', 'id' => 'competency-rating-'.$row->id, 'label' => __('performance.reviews.self_rating').': '.$row->competency->name, 'selected' => old('competencies.'.$row->id.'.rating', $row->self_rating), 'required' => true])
                            <x-form.input :name="'competencies['.$row->id.'][comment]'" :id="'competency-comment-'.$row->id" :label="__('performance.reviews.comment')" hide-label maxlength="2000" :placeholder="__('performance.reviews.comment')" :value="old('competencies.'.$row->id.'.comment', $row->self_comment)" />
                        </div>
                    @else
                        <p class="text-body-sm text-text-secondary">
                            <span class="text-text-tertiary">{{ __('performance.reviews.self_rating') }}:</span>
                            @include('admin.performance._rating-value', ['value' => $row->self_rating])
                            @if ($row->self_comment) — {{ $row->self_comment }} @endif
                        </p>
                        @if ($showManager)
                            <p class="text-body-sm text-text-secondary">
                                <span class="text-text-tertiary">{{ __('performance.reviews.manager_rating') }}:</span>
                                @include('admin.performance._rating-value', ['value' => $row->manager_rating])
                                @if ($row->manager_comment) — {{ $row->manager_comment }} @endif
                            </p>
                        @endif
                    @endif
                </li>
            @empty
                <li class="px-5 py-6 text-body-sm text-text-tertiary">{{ __('performance.reviews.no_competencies') }}</li>
            @endforelse
        </ul>
    </x-card>

    @if ($selfOpen)
            <x-card :title="__('performance.fields.self_comments')">
                <x-form.textarea name="comments" :label="__('performance.fields.self_comments')" hide-label rows="4" maxlength="5000">{{ old('comments', $review->self_comments) }}</x-form.textarea>
            </x-card>
            <x-form.actions>
                <x-button icon="check" data-confirm="{{ __('performance.portal.self_confirm') }}" data-confirm-tone="info">{{ __('performance.portal.submit_self') }}</x-button>
            </x-form.actions>
        </form>
    @else
        <x-card :title="__('performance.reviews.comments')">
            <x-detail-list>
                <x-detail-item :label="__('performance.fields.self_comments')"><span class="whitespace-pre-line">{{ $review->self_comments ?? '—' }}</span></x-detail-item>
                @if ($showManager)
                    <x-detail-item :label="__('performance.fields.strengths')"><span class="whitespace-pre-line">{{ $review->manager_strengths ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('performance.fields.improvements')"><span class="whitespace-pre-line">{{ $review->manager_improvements ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('performance.fields.manager_comments')"><span class="whitespace-pre-line">{{ $review->manager_comments ?? '—' }}</span></x-detail-item>
                @endif
                @if ($review->employee_comment)
                    <x-detail-item :label="__('performance.fields.employee_comment')"><span class="whitespace-pre-line">{{ $review->employee_comment }}</span></x-detail-item>
                @endif
                @if ($review->acknowledged_at)
                    <x-detail-item :label="__('performance.fields.acknowledged_at')"><span dir="ltr">{{ $review->acknowledged_at->format('Y-m-d H:i') }}</span></x-detail-item>
                @endif
            </x-detail-list>
        </x-card>
    @endif

    @if ($review->status === App\Enums\PerformanceReviewStatus::Acknowledgement)
        <x-card :title="__('performance.portal.acknowledge')" :subtitle="__('performance.portal.acknowledge_help')" data-region="acknowledge">
            <form method="POST" action="{{ route('portal.performance.reviews.acknowledge', $review->id) }}" class="flex flex-col gap-3" data-validate>
                @csrf
                <x-form.textarea name="comment" :label="__('performance.fields.employee_comment')" rows="3" maxlength="2000">{{ old('comment') }}</x-form.textarea>
                <div><x-button icon="check">{{ __('performance.portal.acknowledge') }}</x-button></div>
            </form>
        </x-card>
    @endif
@endsection
