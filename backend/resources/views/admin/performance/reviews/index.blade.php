@extends('layouts.admin', ['subtitle' => $seesAll ? __('performance.reviews.subtitle') : __('performance.reviews.subtitle_team')])

@section('title', __('performance.reviews.title'))

@section('page-actions')
    @can('export', App\Models\PerformanceReview::class)
        <x-button variant="ghost" icon="download" :href="route('admin.performance-reviews.export', request()->query())">{{ __('performance.common.export_xlsx') }}</x-button>
        <x-button variant="ghost" icon="download" :href="route('admin.performance-reviews.export', [...request()->query(), 'format' => 'csv'])">{{ __('performance.common.export_csv') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('performance.enums.review_statuses.self_assessment')" :value="(int) ($counts['self_assessment'] ?? 0)" icon="user" :href="route('admin.performance-reviews.index', ['status' => 'self_assessment'])" />
        <x-stat-card :label="__('performance.enums.review_statuses.manager_assessment')" :value="(int) ($counts['manager_assessment'] ?? 0)" icon="pencil" :href="route('admin.performance-reviews.index', ['status' => 'manager_assessment'])" />
        <x-stat-card :label="__('performance.enums.review_statuses.pending_approval')" :value="(int) ($counts['pending_approval'] ?? 0)" icon="inbox" :href="route('admin.performance-reviews.index', ['status' => 'pending_approval'])" />
        <x-stat-card :label="__('performance.reviews.finalised')" :value="(int) ($counts['acknowledgement'] ?? 0) + (int) ($counts['completed'] ?? 0)" icon="check-circle" :href="route('admin.performance-reviews.index', ['status' => 'completed'])" />
    </div>

    <x-filter-bar :action="route('admin.performance-reviews.index')" :reset="route('admin.performance-reviews.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="cycle" :label="__('performance.fields.cycle')" hide-label class="md:w-52">
            <option value="">{{ __('performance.fields.cycle') }}: {{ __('app.common.all') }}</option>
            @foreach ($cycles as $cycle)
                <option value="{{ $cycle->id }}" @selected((string) ($filters['cycle'] ?? '') === (string) $cycle->id)>{{ $cycle->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="status" :label="__('performance.fields.status')" hide-label class="md:w-52">
            <option value="">{{ __('performance.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department" :label="__('performance.fields.department')" hide-label class="md:w-52">
            <option value="">{{ __('performance.fields.department') }}: {{ __('app.common.all') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </x-form.select>
        @if (auth()->user()->employee_id)
            <x-form.select name="awaiting_me" :label="__('performance.reviews.awaiting_me')" hide-label class="md:w-52">
                <option value="">{{ __('performance.reviews.all_reviews') }}</option>
                <option value="1" @selected(($filters['awaiting_me'] ?? false))>{{ __('performance.reviews.awaiting_me') }}</option>
            </x-form.select>
        @endif
    </x-filter-bar>

    <x-table :caption="__('performance.reviews.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="reference">{{ __('performance.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('performance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.cycle') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.reviewer') }}</x-table.th>
                <x-table.th sort="score">{{ __('performance.fields.overall_score') }}</x-table.th>
                <x-table.th>{{ __('performance.fields.final_rating') }}</x-table.th>
                <x-table.th sort="status">{{ __('performance.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($reviews as $review)
            <x-table.row :data-href="route('admin.performance-reviews.show', $review)">
                <x-table.td :label="__('performance.fields.reference')" class="whitespace-nowrap">
                    <a href="{{ route('admin.performance-reviews.show', $review) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $review->reference }}</a>
                </x-table.td>
                <x-table.td :label="__('performance.fields.employee')">
                    {{ $review->employee?->name }}
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $review->employee?->employee_number }}</span>
                </x-table.td>
                <x-table.td :label="__('performance.fields.cycle')">{{ $review->cycle->name }}</x-table.td>
                <x-table.td :label="__('performance.fields.reviewer')">{{ $review->reviewer?->name ?? '—' }}</x-table.td>
                <x-table.td :label="__('performance.fields.overall_score')">@include('admin.performance._score', ['score' => $review->overall_score, 'max' => null])</x-table.td>
                <x-table.td :label="__('performance.fields.final_rating')">@include('admin.performance._rating', ['level' => $review->status->isFinal() || $review->status->value === 'pending_approval' ? $review->finalRating : null, 'calibrated' => $review->calibrated])</x-table.td>
                <x-table.td :label="__('performance.fields.status')">
                    @include('admin.performance._review-status', ['status' => $review->status])
                    @if ($review->status->value === 'pending_approval' && $review->latestApprovalRequest?->currentStep)
                        <span class="block text-caption-md text-text-tertiary">{{ __('performance.common.at_step', ['step' => $review->latestApprovalRequest->currentStep->name]) }}</span>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="award" :title="__('performance.reviews.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$reviews" />
        </x-slot:footer>
    </x-table>
@endsection
