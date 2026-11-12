@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('performance.cycles.title'), 'url' => route('admin.performance-cycles.index')]],
    'subtitle' => $cycle->type->label().' · '.$cycle->period_start->toDateString().' → '.$cycle->period_end->toDateString(),
])

@section('title', $cycle->name)

@section('page-actions')
    @if (! $cycle->isClosed())
        @can('update', $cycle)
            <x-button variant="secondary" icon="pencil" :href="route('admin.performance-cycles.edit', $cycle)">{{ __('app.actions.edit') }}</x-button>
        @endcan
    @endif
    @if ($cycle->isDraft())
        @can('delete', $cycle)
            <form method="POST" action="{{ route('admin.performance-cycles.destroy', $cycle) }}" data-confirm="{{ __('performance.cycles.delete_confirm') }}" data-confirm-tone="danger">
                @csrf
                @method('DELETE')
                <x-button variant="ghost" icon="trash">{{ __('app.actions.delete') }}</x-button>
            </form>
        @endcan
        @can('update', $cycle)
            <form method="POST" action="{{ route('admin.performance-cycles.launch', $cycle) }}" data-confirm="{{ __('performance.cycles.launch_confirm', ['count' => $participants]) }}" data-confirm-tone="info">
                @csrf
                <x-button icon="arrow-right">{{ __('performance.cycles.launch') }}</x-button>
            </form>
        @endcan
    @elseif ($cycle->isActive())
        @can('update', $cycle)
            <form method="POST" action="{{ route('admin.performance-cycles.close', $cycle) }}" data-confirm="{{ __('performance.cycles.close_confirm') }}" data-confirm-tone="warning">
                @csrf
                <x-button variant="secondary" icon="lock">{{ __('performance.cycles.close') }}</x-button>
            </form>
        @endcan
    @endif
@endsection

@section('content')
    @if ($errors->any())
        <x-alert tone="danger">{{ $errors->first() }}</x-alert>
    @endif

    @if ($cycle->isDraft())
        <x-alert tone="info" :title="__('performance.cycles.draft_title')">{{ __('performance.cycles.draft_help', ['count' => $participants]) }}</x-alert>
    @endif

    @php($finalised = (int) ($statusCounts['acknowledgement'] ?? 0) + (int) ($statusCounts['completed'] ?? 0))
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('performance.cycles.reviews')" :value="$reviewTotal" icon="users" :href="$cycle->isDraft() ? null : route('admin.performance-reviews.index', ['cycle' => $cycle->id])" />
        <x-stat-card :label="__('performance.enums.review_statuses.self_assessment')" :value="(int) ($statusCounts['self_assessment'] ?? 0)" icon="user" :href="$cycle->isDraft() ? null : route('admin.performance-reviews.index', ['cycle' => $cycle->id, 'status' => 'self_assessment'])" />
        <x-stat-card :label="__('performance.enums.review_statuses.manager_assessment')" :value="(int) ($statusCounts['manager_assessment'] ?? 0) + (int) ($statusCounts['pending_approval'] ?? 0)" icon="pencil" :hint="__('performance.cycles.incl_approval', ['count' => (int) ($statusCounts['pending_approval'] ?? 0)])" />
        <x-stat-card :label="__('performance.reviews.finalised')" :value="$finalised" icon="check-circle" :hint="__('performance.cycles.acknowledged_count', ['count' => (int) ($statusCounts['completed'] ?? 0)])" />
    </div>

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('performance.cycles.by_status')" data-region="cycle-status">
                <ul class="flex flex-col gap-3">
                    @foreach ($reviewStatuses as $status)
                        @php($count = (int) ($statusCounts[$status->value] ?? 0))
                        <li class="grid grid-cols-[minmax(0,12rem)_1fr] items-center gap-3">
                            @include('admin.performance._review-status', ['status' => $status])
                            @include('admin.performance._progress', ['value' => $reviewTotal > 0 ? bcdiv(bcmul((string) $count, '100', 4), (string) max($reviewTotal, 1), 2) : '0', 'label' => $status->label()])
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card :title="__('performance.cycles.rating_distribution')" :subtitle="__('performance.cycles.rating_distribution_help')" data-region="cycle-ratings">
                <ul class="flex flex-col gap-3">
                    @foreach ($cycle->ratingScale->levels->reverse() as $level)
                        @php($count = (int) ($ratingCounts[$level->id] ?? 0))
                        <li class="grid grid-cols-[minmax(0,12rem)_1fr_3rem] items-center gap-3">
                            @include('admin.performance._rating', ['level' => $level])
                            @include('admin.performance._progress', ['value' => $finalised > 0 ? bcdiv(bcmul((string) $count, '100', 4), (string) $finalised, 2) : '0', 'label' => $level->label])
                            <span class="text-end text-label-md tabular-nums">{{ $count }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('performance.cycles.settings')">
                <x-slot:actions><x-badge :tone="$cycle->status->tone()" dot>{{ $cycle->status->label() }}</x-badge></x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('performance.fields.code')"><span dir="ltr">{{ $cycle->code }}</span></x-detail-item>
                    <x-detail-item :label="__('performance.fields.rating_scale')">{{ $cycle->ratingScale->name }}</x-detail-item>
                    <x-detail-item :label="__('performance.cycles.split')">{{ __('performance.cycles.split_value', ['goals' => rtrim(rtrim((string) $cycle->goals_weight, '0'), '.'), 'competencies' => rtrim(rtrim((string) $cycle->competencies_weight, '0'), '.')]) }}</x-detail-item>
                    <x-detail-item :label="__('performance.fields.self_assessment_enabled')">{{ $cycle->self_assessment_enabled ? __('app.common.yes') : __('app.common.no') }}</x-detail-item>
                    <x-detail-item :label="__('performance.fields.self_assessment_due')"><span dir="ltr">{{ $cycle->self_assessment_due?->toDateString() ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('performance.fields.manager_assessment_due')"><span dir="ltr">{{ $cycle->manager_assessment_due?->toDateString() ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('performance.cycles.participants')">{{ collect([$cycle->branch?->name, $cycle->department?->name])->filter()->implode(' · ') ?: __('performance.cycles.everyone') }}</x-detail-item>
                    @if ($cycle->launched_at)
                        <x-detail-item :label="__('performance.fields.launched_at')"><span dir="ltr">{{ $cycle->launched_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                    @if ($cycle->closed_at)
                        <x-detail-item :label="__('performance.fields.closed_at')"><span dir="ltr">{{ $cycle->closed_at->format('Y-m-d H:i') }}</span></x-detail-item>
                    @endif
                </x-detail-list>
                @if ($cycle->description)
                    <p class="mt-4 whitespace-pre-line text-body-sm text-text-secondary">{{ $cycle->description }}</p>
                @endif
            </x-card>

            <x-card :title="__('performance.cycles.competencies')" padding="none">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($cycle->competencies as $row)
                        <li class="flex items-center justify-between gap-3 px-5 py-3">
                            <span class="text-body-md text-text-primary">{{ $row->competency->name }} <span class="block text-caption-md text-text-tertiary">{{ $row->competency->category->label() }}</span></span>
                            <span class="text-label-md tabular-nums" dir="ltr">{{ rtrim(rtrim((string) $row->weight, '0'), '.') }}%</span>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-body-sm text-text-tertiary">{{ __('performance.cycles.no_competencies') }}</li>
                    @endforelse
                </ul>
            </x-card>
        </div>
    </div>
@endsection
