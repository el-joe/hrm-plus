{{--
    History tab: every dated change, newest first. Nothing here is ever overwritten; scheduled and
    cancelled entries stay visible. Salary rows appear for financial viewers only (logged).
    Variables: $employee, $entries, $previous (assignment id => previous assignment), $currency.
--}}
<x-card :title="__('employees.tabs.history')" :subtitle="__('employees.history.subtitle')">
    @if ($entries->isEmpty())
        <x-empty-state icon="history" :title="__('employees.history.empty')" />
    @else
        <ol class="relative flex flex-col gap-5 border-s border-border-default ps-6" data-region="timeline">
            @foreach ($entries as $entry)
                @php($row = $entry['record'])
                <li class="relative" data-history-kind="{{ $entry['kind'] }}">
                    <span class="absolute -start-[1.95rem] top-1 inline-flex size-4 items-center justify-center rounded-full border-2 border-surface-default
                        {{ match ($entry['kind']) { 'salary' => 'bg-chart-3', 'contract' => 'bg-chart-2', default => 'bg-action-primary-default' } }}" aria-hidden="true"></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-caption-md text-text-tertiary tabular-nums" dir="ltr">{{ $entry['date']->toDateString() }}</span>
                        @if ($entry['kind'] === 'assignment')
                            <span class="text-label-md text-text-primary">{{ $row->change_type->label() }}</span>
                        @elseif ($entry['kind'] === 'salary')
                            <span class="text-label-md text-text-primary">{{ __('employees.history.salary_change') }} · {{ $row->change_type->label() }}</span>
                        @else
                            <span class="text-label-md text-text-primary">{{ $row->renewal_of_id ? __('employees.history.contract_renewed') : __('employees.history.contract_started') }}</span>
                        @endif
                        @if (($entry['kind'] !== 'contract') && $row->isPending())
                            <x-badge tone="info">{{ __('employees.history.scheduled') }}</x-badge>
                        @elseif (($entry['kind'] !== 'contract') && $row->cancelled_at)
                            <x-badge>{{ __('employees.history.cancelled') }}</x-badge>
                        @endif
                    </div>

                    <div @class(['mt-1 text-body-sm text-text-secondary', 'line-through opacity-70' => $entry['kind'] !== 'contract' && $row->cancelled_at])>
                        @if ($entry['kind'] === 'assignment')
                            @php($before = $previous[$row->id] ?? null)
                            <dl class="grid gap-x-4 gap-y-1 sm:grid-cols-2">
                                @foreach (['branch' => 'branch_id', 'department' => 'department_id', 'position' => 'position_id', 'jobTitle' => 'job_title_id', 'jobGrade' => 'job_grade_id', 'manager' => 'manager_id'] as $relation => $field)
                                    @php($changed = $before !== null && (string) $before->{$field} !== (string) $row->{$field})
                                    @if ($before === null || $changed)
                                        <div class="flex gap-1">
                                            <dt class="text-text-tertiary">{{ __('employees.fields.'.$field) }}:</dt>
                                            <dd>
                                                @if ($changed)
                                                    <span class="text-text-tertiary">{{ $before->{$relation}?->name ?? '—' }} →</span>
                                                @endif
                                                {{ $row->{$relation}?->name ?? '—' }}
                                            </dd>
                                        </div>
                                    @endif
                                @endforeach
                                @if ($before !== null && $before->status !== $row->status)
                                    <div class="flex gap-1">
                                        <dt class="text-text-tertiary">{{ __('employees.fields.status') }}:</dt>
                                        <dd><span class="text-text-tertiary">{{ $before->status->label() }} →</span> {{ $row->status->label() }}</dd>
                                    </div>
                                @endif
                                @if ($row->termination_type)
                                    <div class="flex gap-1">
                                        <dt class="text-text-tertiary">{{ __('employees.fields.termination_type') }}:</dt>
                                        <dd>{{ $row->termination_type->label() }}@if ($row->eligible_for_rehire !== null) · {{ $row->eligible_for_rehire ? __('employees.history.rehire_ok') : __('employees.history.rehire_no') }}@endif</dd>
                                    </div>
                                @endif
                            </dl>
                        @elseif ($entry['kind'] === 'salary')
                            <span dir="ltr" class="tabular-nums">{{ $row->previous_salary !== null ? App\Support\Money::format($row->previous_salary).' → ' : '' }}{{ App\Support\Money::format($row->basic_salary) }} {{ $row->currency }}</span>
                        @else
                            {{ $row->contract_type->label() }} · <span dir="ltr">{{ $row->start_date->toDateString() }} → {{ $row->end_date?->toDateString() ?? __('employees.contracts.open_ended') }}</span>
                        @endif
                        @if ($entry['kind'] !== 'contract' && $row->reason)
                            <p class="mt-1 text-text-primary">{{ $row->reason }}</p>
                        @endif
                    </div>
                    @if ($entry['kind'] !== 'contract' && $row->creator)
                        <p class="mt-1 text-caption-sm text-text-tertiary">{{ __('employees.history.by', ['name' => $row->creator->name, 'at' => $row->created_at->toDateTimeString()]) }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</x-card>
