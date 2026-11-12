@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('performance.goals.title'), 'url' => route('admin.performance-goals.index')]],
    'subtitle' => $goal->exists ? $goal->employee?->name : null,
])

@section('title', $goal->exists ? $goal->title : __('performance.goals.create'))

@section('content')
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div @class(['flex flex-col gap-4 lg:gap-6', 'lg:col-span-2' => $goal->exists, 'lg:col-span-3' => ! $goal->exists])>
            @if ($canUpdate)
                <form method="POST" action="{{ $goal->exists ? route('admin.performance-goals.update', $goal) : route('admin.performance-goals.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
                    @csrf
                    @if ($goal->exists)
                        @method('PUT')
                    @endif
                    @if ($returnTo)
                        <input type="hidden" name="return" value="{{ $returnTo }}">
                    @endif

                    <x-form.section :title="__('performance.goals.details')" :description="__('performance.goals.details_help')">
                        @unless ($goal->exists)
                            <div class="md:col-span-2">
                                <x-form.select name="employee_id" :label="__('performance.fields.employee')" required>
                                    <option value="">{{ __('performance.goals.choose_employee') }}</option>
                                    @foreach ($employees as $employee)
                                        <option value="{{ $employee->id }}" @selected((string) old('employee_id', $goal->employee_id) === (string) $employee->id)>{{ $employee->label() }}</option>
                                    @endforeach
                                </x-form.select>
                            </div>
                        @endunless
                        @include('admin.performance._goal-fields')
                    </x-form.section>

                    <x-form.actions>
                        <x-button variant="secondary" :href="route('admin.performance-goals.index')">{{ __('app.actions.cancel') }}</x-button>
                        <x-button>{{ __('app.actions.save') }}</x-button>
                    </x-form.actions>
                </form>
            @else
                <x-card :title="__('performance.goals.details')">
                    <x-detail-list>
                        <x-detail-item :label="__('performance.fields.type')">{{ $goal->type->label() }}</x-detail-item>
                        <x-detail-item :label="__('performance.fields.cycle')">{{ $goal->cycle?->name ?? '—' }}</x-detail-item>
                        <x-detail-item :label="__('performance.fields.weight')"><span dir="ltr">{{ $goal->weight }}%</span></x-detail-item>
                        <x-detail-item :label="__('performance.fields.due_date')"><span dir="ltr">{{ $goal->due_date?->toDateString() ?? '—' }}</span></x-detail-item>
                        <x-detail-item :label="__('performance.fields.description')">{{ $goal->description ?? '—' }}</x-detail-item>
                    </x-detail-list>
                </x-card>
            @endif
        </div>

        @if ($goal->exists)
            <div class="flex flex-col gap-4 lg:gap-6">
                <x-card :title="__('performance.fields.progress')">
                    <x-slot:actions><x-badge :tone="$goal->status->tone()">{{ $goal->status->label() }}</x-badge></x-slot:actions>
                    @include('admin.performance._progress', ['value' => $goal->progress, 'label' => $goal->title])
                    @if ($goal->isKpi() && $goal->target_value !== null)
                        <p class="mt-2 text-body-sm text-text-secondary">{{ __('performance.goals.kpi_line', ['current' => $goal->current_value ?? '0', 'target' => $goal->target_value, 'unit' => $goal->unit]) }}</p>
                    @endif
                    @if ($canUpdate && $goal->status->value !== 'cancelled')
                        <form method="POST" action="{{ route('admin.performance-goals.progress', $goal) }}" class="mt-4 flex flex-col gap-3" data-validate>
                            @csrf
                            @if ($goal->isKpi())
                                <x-form.input name="current_value" type="number" step="0.01" min="0" dir="ltr" :label="__('performance.fields.current_value')" :value="old('current_value', $goal->current_value)" />
                            @else
                                <x-form.input name="progress" type="number" step="1" min="0" max="100" dir="ltr" required :label="__('performance.fields.progress_pct')" :value="old('progress', rtrim(rtrim((string) $goal->progress, '0'), '.'))" />
                            @endif
                            <x-form.input name="note" maxlength="1000" :label="__('performance.fields.note')" />
                            <x-button variant="secondary" icon="check">{{ __('performance.goals.record_progress') }}</x-button>
                        </form>
                    @endif
                </x-card>

                @if ($canUpdate)
                    <x-card :title="__('performance.goals.change_status')">
                        <div class="flex flex-wrap gap-2">
                            @foreach (App\Enums\GoalStatus::cases() as $status)
                                @continue($status === $goal->status)
                                <form method="POST" action="{{ route('admin.performance-goals.status', $goal) }}" data-confirm="{{ __('performance.goals.status_confirm', ['status' => $status->label()]) }}" data-confirm-tone="{{ $status->value === 'cancelled' ? 'danger' : 'info' }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $status->value }}">
                                    <x-button size="sm" :variant="$status->value === 'cancelled' ? 'danger' : 'secondary'">{{ __('performance.goals.mark_as', ['status' => $status->label()]) }}</x-button>
                                </form>
                            @endforeach
                        </div>
                    </x-card>
                @endif

                <x-card :title="__('performance.goals.history')">
                    <ol class="flex flex-col gap-3">
                        @forelse ($goal->updates as $update)
                            <li class="flex flex-col gap-0.5 border-s-2 border-border-subtle ps-3">
                                <span class="text-label-md text-text-primary tabular-nums" dir="ltr">{{ rtrim(rtrim((string) $update->progress, '0'), '.') }}%@if ($update->current_value !== null) · {{ $update->current_value }} {{ $goal->unit }}@endif</span>
                                <span class="text-caption-md text-text-tertiary">{{ $update->creator?->name ?? '—' }} · <span dir="ltr">{{ $update->created_at?->format('Y-m-d H:i') }}</span></span>
                                @if ($update->note)
                                    <span class="text-body-sm text-text-secondary">{{ $update->note }}</span>
                                @endif
                            </li>
                        @empty
                            <li class="text-body-sm text-text-tertiary">{{ __('performance.goals.no_updates') }}</li>
                        @endforelse
                    </ol>
                </x-card>
            </div>
        @endif
    </div>
@endsection
