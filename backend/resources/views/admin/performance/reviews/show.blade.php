@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('performance.reviews.title'), 'url' => route('admin.performance-reviews.index')]],
    'subtitle' => $review->employee->employee_number.' · '.$review->cycle->name,
])

@section('title', __('performance.reviews.of', ['name' => $review->employee->name]))

@section('page-actions')
    @can('view', $review->employee)
        <x-button variant="secondary" icon="user" :href="route('admin.employees.tab', [$review->employee, 'performance'])">{{ __('performance.common.employee_profile') }}</x-button>
    @endcan
@endsection

@section('content')
    @php($max = $levels->last()?->value)
    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('performance.fields.self_score')" :value="$review->self_overall_score ?? '—'" icon="user" :hint="$max ? __('performance.common.out_of', ['max' => rtrim(rtrim((string) $max, '0'), '.')]) : null" />
        <x-stat-card :label="__('performance.fields.goals_score')" :value="$review->goals_score ?? '—'" icon="target" :hint="__('performance.common.weight_pct', ['weight' => rtrim(rtrim((string) $review->cycle->goals_weight, '0'), '.')])" />
        <x-stat-card :label="__('performance.fields.competencies_score')" :value="$review->competencies_score ?? '—'" icon="layers" :hint="__('performance.common.weight_pct', ['weight' => rtrim(rtrim((string) $review->cycle->competencies_weight, '0'), '.')])" />
        <x-stat-card :label="__('performance.fields.overall_score')" :value="$review->overall_score ?? '—'" icon="award" :hint="$review->finalRating?->label" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            @if ($canAssess)
                <form method="POST" action="{{ route('admin.performance-reviews.assess', $review) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
                    @csrf
            @endif

            <x-card :title="__('performance.reviews.goals')" :subtitle="__('performance.reviews.goals_help')" padding="none" data-region="review-goals">
                @if (in_array($review->status->value, ['self_assessment', 'manager_assessment'], true))
                    @can('createFor', [App\Models\PerformanceGoal::class, $review->employee])
                        <x-slot:actions>
                            <x-button size="sm" variant="secondary" icon="plus" :href="route('admin.performance-goals.create', ['employee' => $review->employee_id, 'cycle' => $review->performance_cycle_id, 'return' => 'review'])">{{ __('performance.goals.create') }}</x-button>
                        </x-slot:actions>
                    @endcan
                @endif
                <ul class="divide-y divide-border-subtle">
                    @forelse ($goals as $goal)
                        <li @class(['flex flex-col gap-3 px-5 py-4', 'opacity-60' => ! $goal->status->counts()])>
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    @can('view', $goal)
                                        <a href="{{ route('admin.performance-goals.edit', $goal) }}" class="text-label-md text-text-link hover:underline">{{ $goal->title }}</a>
                                    @else
                                        <span class="text-label-md text-text-primary">{{ $goal->title }}</span>
                                    @endcan
                                    <p class="text-caption-md text-text-tertiary">
                                        {{ $goal->type->label() }} · {{ __('performance.common.weight_pct', ['weight' => rtrim(rtrim((string) $goal->weight, '0'), '.')]) }}
                                        @if ($goal->isKpi() && $goal->target_value !== null)
                                            · {{ __('performance.goals.kpi_line', ['current' => $goal->current_value ?? '0', 'target' => $goal->target_value, 'unit' => $goal->unit]) }}
                                        @endif
                                    </p>
                                </div>
                                <x-badge :tone="$goal->status->tone()">{{ $goal->status->label() }}</x-badge>
                            </div>
                            <div class="max-w-sm">@include('admin.performance._progress', ['value' => $goal->progress, 'label' => $goal->title])</div>
                            @if ($showSelf)
                                <p class="text-body-sm text-text-secondary">
                                    <span class="text-text-tertiary">{{ __('performance.reviews.self_rating') }}:</span>
                                    @include('admin.performance._rating-value', ['value' => $goal->self_rating])
                                    @if ($goal->self_comment) — {{ $goal->self_comment }} @endif
                                </p>
                            @endif
                            @if ($goal->status->counts())
                                @if ($canAssess)
                                    <div class="grid gap-2 md:grid-cols-[14rem_1fr]">
                                        @include('admin.performance._rating-select', ['name' => 'goals['.$goal->id.'][rating]', 'id' => 'goal-rating-'.$goal->id, 'label' => __('performance.reviews.manager_rating').': '.$goal->title, 'selected' => old('goals.'.$goal->id.'.rating', $goal->manager_rating)])
                                        <x-form.input :name="'goals['.$goal->id.'][comment]'" :id="'goal-comment-'.$goal->id" :label="__('performance.reviews.comment')" hide-label maxlength="2000" :placeholder="__('performance.reviews.comment')" :value="old('goals.'.$goal->id.'.comment', $goal->manager_comment)" />
                                    </div>
                                @elseif ($goal->manager_rating !== null)
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

            <x-card :title="__('performance.reviews.competencies')" padding="none" data-region="review-competencies">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($review->competencies as $row)
                        <li class="flex flex-col gap-3 px-5 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <span class="text-label-md text-text-primary">{{ $row->competency->name }}</span>
                                    <p class="text-caption-md text-text-tertiary">{{ $row->competency->category->label() }} · {{ __('performance.common.weight_pct', ['weight' => rtrim(rtrim((string) $row->weight, '0'), '.')]) }}</p>
                                </div>
                            </div>
                            @if ($showSelf)
                                <p class="text-body-sm text-text-secondary">
                                    <span class="text-text-tertiary">{{ __('performance.reviews.self_rating') }}:</span>
                                    @include('admin.performance._rating-value', ['value' => $row->self_rating])
                                    @if ($row->self_comment) — {{ $row->self_comment }} @endif
                                </p>
                            @endif
                            @if ($canAssess)
                                <div class="grid gap-2 md:grid-cols-[14rem_1fr]">
                                    @include('admin.performance._rating-select', ['name' => 'competencies['.$row->id.'][rating]', 'id' => 'competency-rating-'.$row->id, 'label' => __('performance.reviews.manager_rating').': '.$row->competency->name, 'selected' => old('competencies.'.$row->id.'.rating', $row->manager_rating)])
                                    <x-form.input :name="'competencies['.$row->id.'][comment]'" :id="'competency-comment-'.$row->id" :label="__('performance.reviews.comment')" hide-label maxlength="2000" :placeholder="__('performance.reviews.comment')" :value="old('competencies.'.$row->id.'.comment', $row->manager_comment)" />
                                </div>
                            @elseif ($row->manager_rating !== null)
                                <p class="text-body-sm text-text-secondary">
                                    <span class="text-text-tertiary">{{ __('performance.reviews.manager_rating') }}:</span>
                                    @include('admin.performance._rating-value', ['value' => $row->manager_rating])
                                    @if ($row->manager_comment) — {{ $row->manager_comment }} @endif
                                </p>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-6 text-body-sm text-text-tertiary">{{ __('performance.reviews.no_competencies') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('performance.reviews.comments')" data-region="review-comments">
                @if ($showSelf && $review->self_comments)
                    <div class="mb-4">
                        <p class="text-label-sm text-text-tertiary">{{ __('performance.fields.self_comments') }}</p>
                        <p class="whitespace-pre-line text-body-md text-text-secondary">{{ $review->self_comments }}</p>
                    </div>
                @endif
                @if ($canAssess)
                    <div class="grid gap-4">
                        <x-form.textarea name="strengths" :label="__('performance.fields.strengths')" rows="3" maxlength="5000">{{ old('strengths', $review->manager_strengths) }}</x-form.textarea>
                        <x-form.textarea name="improvements" :label="__('performance.fields.improvements')" rows="3" maxlength="5000">{{ old('improvements', $review->manager_improvements) }}</x-form.textarea>
                        <x-form.textarea name="comments" :label="__('performance.fields.manager_comments')" rows="3" maxlength="5000">{{ old('comments', $review->manager_comments) }}</x-form.textarea>
                    </div>
                @else
                    <x-detail-list>
                        <x-detail-item :label="__('performance.fields.strengths')"><span class="whitespace-pre-line">{{ $review->manager_strengths ?? '—' }}</span></x-detail-item>
                        <x-detail-item :label="__('performance.fields.improvements')"><span class="whitespace-pre-line">{{ $review->manager_improvements ?? '—' }}</span></x-detail-item>
                        <x-detail-item :label="__('performance.fields.manager_comments')"><span class="whitespace-pre-line">{{ $review->manager_comments ?? '—' }}</span></x-detail-item>
                        @if ($review->employee_comment)
                            <x-detail-item :label="__('performance.fields.employee_comment')"><span class="whitespace-pre-line">{{ $review->employee_comment }}</span></x-detail-item>
                        @endif
                    </x-detail-list>
                @endif
            </x-card>

            @if ($canAssess)
                    <x-form.actions>
                        <x-button variant="secondary" name="submit" value="0">{{ __('performance.reviews.save_draft') }}</x-button>
                        <x-button name="submit" value="1" icon="check" data-confirm="{{ __('performance.reviews.submit_confirm') }}" data-confirm-tone="info">{{ __('performance.reviews.submit') }}</x-button>
                    </x-form.actions>
                </form>
            @endif
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('performance.reviews.details')">
                <x-slot:actions>@include('admin.performance._review-status', ['status' => $review->status])</x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('performance.fields.reference')"><span dir="ltr">{{ $review->reference }}</span></x-detail-item>
                    <x-detail-item :label="__('performance.fields.employee')">{{ $review->employee->name }} · {{ $review->employee->jobTitle?->name }}</x-detail-item>
                    <x-detail-item :label="__('performance.fields.department')">{{ $review->employee->department?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('performance.fields.reviewer')">{{ $review->reviewer?->name ?? __('performance.reviews.no_reviewer') }}</x-detail-item>
                    <x-detail-item :label="__('performance.fields.cycle')">
                        @can('view', $review->cycle)
                            <a class="text-text-link hover:underline" href="{{ route('admin.performance-cycles.show', $review->cycle) }}">{{ $review->cycle->name }}</a>
                        @else
                            {{ $review->cycle->name }}
                        @endcan
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $review->cycle->period_start->toDateString() }} → {{ $review->cycle->period_end->toDateString() }}</span>
                    </x-detail-item>
                    <x-detail-item :label="__('performance.fields.final_rating')">@include('admin.performance._rating', ['level' => $review->finalRating, 'calibrated' => $review->calibrated])</x-detail-item>
                    @if ($review->calibration_note)
                        <x-detail-item :label="__('performance.fields.calibration_note')">{{ $review->calibration_note }}</x-detail-item>
                    @endif
                    @if ($review->self_submitted_at)
                        <x-detail-item :label="__('performance.fields.self_submitted_at')"><span dir="ltr">{{ $review->self_submitted_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                    @if ($review->manager_submitted_at)
                        <x-detail-item :label="__('performance.fields.manager_submitted_at')"><span dir="ltr">{{ $review->manager_submitted_at->format('Y-m-d H:i') }}</span> · {{ $review->managerSubmitter?->name }}</x-detail-item>
                    @endif
                    @if ($review->approved_at)
                        <x-detail-item :label="__('performance.fields.approved_at')"><span dir="ltr">{{ $review->approved_at->format('Y-m-d H:i') }}</span> · {{ $review->approver?->name }}</x-detail-item>
                    @endif
                    @if ($review->acknowledged_at)
                        <x-detail-item :label="__('performance.fields.acknowledged_at')"><span dir="ltr">{{ $review->acknowledged_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                </x-detail-list>
            </x-card>

            @if ($canDecide)
                <x-card :title="__('performance.reviews.calibration')" :subtitle="__('performance.reviews.calibration_help')" data-region="decision">
                    <form method="POST" action="{{ route('admin.performance-reviews.decide', [$review, 'approve']) }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.select name="calibrated_level_id" :label="__('performance.fields.final_rating')">
                            @foreach ($levels as $level)
                                <option value="{{ $level->id }}" @selected((int) old('calibrated_level_id', $review->final_rating_level_id) === $level->id)>{{ rtrim(rtrim((string) $level->value, '0'), '.') }} — {{ $level->label }}@if ($level->id === $review->final_rating_level_id) ({{ __('performance.reviews.from_score') }})@endif</option>
                            @endforeach
                        </x-form.select>
                        <x-form.textarea name="reason" id="approve-reason" :label="__('performance.reviews.approve_note')" :help="__('performance.reviews.approve_note_help')" rows="2" maxlength="2000">{{ old('reason') }}</x-form.textarea>
                        <x-button icon="check">{{ __('performance.common.approve') }}</x-button>
                    </form>
                    <form method="POST" action="{{ route('admin.performance-reviews.decide', [$review, 'reject']) }}" class="mt-4 flex flex-col gap-2" data-validate>
                        @csrf
                        <x-form.textarea name="reason" id="reject-reason" :label="__('performance.reviews.return_reason')" required maxlength="2000" rows="2" />
                        <x-button variant="danger" icon="x-circle">{{ __('performance.reviews.return') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if ($canSkipSelf)
                <x-card :title="__('performance.reviews.skip_self')" :subtitle="__('performance.reviews.skip_self_help')">
                    <form method="POST" action="{{ route('admin.performance-reviews.skip-self', $review) }}" data-confirm="{{ __('performance.reviews.skip_self_confirm') }}" data-confirm-tone="warning">
                        @csrf
                        <x-button variant="secondary" icon-end="arrow-right">{{ __('performance.reviews.skip_self') }}</x-button>
                    </form>
                </x-card>
            @endif

            <x-card :title="__('performance.reviews.approval_history')">
                @include('admin.payroll._approval-history', ['requests' => $review->approvalRequests])
            </x-card>

            @if ($canCancel)
                <x-card :title="__('performance.reviews.cancel')">
                    <form method="POST" action="{{ route('admin.performance-reviews.cancel', $review) }}" class="flex flex-col gap-2" data-validate data-confirm="{{ __('performance.reviews.cancel_confirm') }}" data-confirm-tone="danger">
                        @csrf
                        <x-form.input name="reason" id="cancel-reason" required maxlength="1000" :label="__('performance.fields.reason')" />
                        <x-button variant="danger" icon="x">{{ __('performance.reviews.cancel') }}</x-button>
                    </form>
                </x-card>
            @endif
        </div>
    </div>
@endsection
