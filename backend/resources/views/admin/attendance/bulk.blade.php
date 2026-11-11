@php use App\Services\Attendance\AttendanceClock; @endphp
@extends('layouts.admin', ['subtitle' => __('attendance.bulk.subtitle')])

@section('title', __('attendance.bulk.title'))

@section('page-actions')
    <x-button variant="secondary" icon="upload" :href="route('admin.attendance.import')">{{ __('attendance.import.title') }}</x-button>
@endsection

@section('content')
    <x-card :title="__('attendance.bulk.step_filters')" :subtitle="__('attendance.bulk.step_filters_help')">
        <form method="GET" action="{{ route('admin.attendance.bulk.create') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 xl:items-end" data-region="bulk-filters">
            <x-form.input name="date" type="date" :label="__('attendance.fields.date')" :value="$filters['date'] ?? $today" required />
            <x-form.select name="branch_id" :label="__('attendance.fields.branch')">
                <option value="">{{ __('app.common.all') }}</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="department_id" :label="__('attendance.fields.department')">
                <option value="">{{ __('app.common.all') }}</option>
                @foreach ($departments as $department)
                    <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->code }} · {{ $department->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="team_id" :label="__('attendance.fields.team')">
                <option value="">{{ __('app.common.all') }}</option>
                @foreach ($teams as $team)
                    <option value="{{ $team->id }}" @selected((string) ($filters['team_id'] ?? '') === (string) $team->id)>{{ $team->code }} · {{ $team->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="shift_id" :label="__('attendance.fields.shift')">
                <option value="">{{ __('app.common.all') }}</option>
                @foreach ($shifts as $shift)
                    <option value="{{ $shift->id }}" @selected((string) ($filters['shift_id'] ?? '') === (string) $shift->id)>{{ $shift->code }} · {{ $shift->timeRange() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="group_id" :label="__('attendance.fields.group')">
                <option value="">{{ __('app.common.all') }}</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}" @selected((string) ($filters['group_id'] ?? '') === (string) $group->id)>{{ $group->code }} · {{ $group->name }}</option>
                @endforeach
            </x-form.select>
            <div class="sm:col-span-2 lg:col-span-3 xl:col-span-6">
                <x-button icon="users">{{ __('attendance.bulk.load') }}</x-button>
            </div>
        </form>
    </x-card>

    @if ($loaded)
        <form method="POST" action="{{ route('admin.attendance.bulk.store') }}" class="flex flex-col gap-4" data-bulk-attendance data-region="bulk-form">
            @csrf
            @foreach ($filters as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <x-card padding="none">
                <x-slot:actions>
                    <span class="text-label-md text-text-secondary" hidden data-js-only data-check-count="bulk-selected" data-template="{{ __('attendance.bulk.selected_count') }}"></span>
                </x-slot:actions>

                <div class="flex flex-col gap-3 border-b border-border-subtle p-4 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <h2 class="text-h5 text-text-primary">{{ __('attendance.bulk.step_list') }}</h2>
                        <p class="text-body-sm text-text-tertiary">{{ trans_choice('attendance.bulk.step_list_help', count($rows), ['count' => count($rows)]) }}</p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end" hidden data-js-only>
                        <x-form.input name="bulk_search" type="search" icon="search" :label="__('attendance.common.search_employee')" hide-label :placeholder="__('attendance.common.search_employee')" class="sm:w-56" data-bulk-search />
                        <x-button type="button" variant="ghost" size="sm" data-bulk-select="all">{{ __('attendance.bulk.select_all') }}</x-button>
                        <x-button type="button" variant="ghost" size="sm" data-bulk-select="none">{{ __('attendance.bulk.unselect_all') }}</x-button>
                        <div class="flex items-end gap-2">
                            <x-form.select name="bulk_action" :label="__('attendance.bulk.apply_to_selected')" class="sm:w-48" data-bulk-apply-action>
                                @foreach ($actions as $option)
                                    <option value="{{ $option['value'] }}">{{ $option['label'] }}</option>
                                @endforeach
                            </x-form.select>
                            <x-button type="button" variant="secondary" size="sm" data-bulk-apply>{{ __('attendance.bulk.apply') }}</x-button>
                        </div>
                    </div>
                </div>

                <x-table :caption="__('attendance.bulk.step_list')" class="rounded-none border-0 shadow-none">
                    <x-slot:head>
                        <tr>
                            <x-table.th class="w-10">
                                <input type="checkbox" class="size-4 accent-action-primary-default" aria-label="{{ __('attendance.bulk.select_all') }}" hidden data-js-only data-check-all="bulk-selected">
                            </x-table.th>
                            <x-table.th class="min-w-48">{{ __('attendance.fields.employee') }}</x-table.th>
                            <x-table.th>{{ __('attendance.fields.shift') }}</x-table.th>
                            <x-table.th>{{ __('attendance.bulk.recorded') }}</x-table.th>
                            <x-table.th class="min-w-44">{{ __('attendance.bulk.mark_as') }}</x-table.th>
                            <x-table.th>{{ __('attendance.bulk.times') }}</x-table.th>
                            <x-table.th class="min-w-40">{{ __('attendance.fields.note') }}</x-table.th>
                        </tr>
                    </x-slot:head>

                    @forelse ($rows as $row)
                        @php
                            $employee = $row['employee'];
                            $key = 'employees.'.$employee->id;
                            $selected = old($key.'.selected', '1') === '1';
                        @endphp
                        <tr class="border-b border-border-subtle align-top" data-bulk-row data-search="{{ mb_strtolower($employee->employee_number.' '.$employee->name_ar.' '.$employee->name_en) }}">
                            <x-table.td>
                                <input type="hidden" name="employees[{{ $employee->id }}][selected]" value="0">
                                <input type="checkbox" name="employees[{{ $employee->id }}][selected]" value="1" @checked($selected)
                                    class="size-4 accent-action-primary-default" aria-label="{{ $employee->name }}"
                                    data-check-group="bulk-selected" data-check-counted="bulk-selected">
                            </x-table.td>
                            <x-table.td :label="__('attendance.fields.employee')">
                                <span class="block text-label-md text-text-primary">{{ $employee->name }}</span>
                                <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $employee->employee_number }} · {{ $employee->department?->code }}</span>
                            </x-table.td>
                            <x-table.td :label="__('attendance.fields.shift')">
                                @foreach ($row['shifts'] as $shift)
                                    <span class="block text-label-md">{{ $shift->shift->code }} <span class="text-caption-md text-text-tertiary tabular-nums" dir="ltr">{{ $shift->shift->timeRange() }}</span></span>
                                @endforeach
                            </x-table.td>
                            <x-table.td :label="__('attendance.bulk.recorded')">
                                @forelse ($row['days'] as $day)
                                    @include('admin.attendance._day-status')
                                @empty
                                    <span class="text-caption-md text-text-tertiary">{{ __('attendance.bulk.nothing_recorded') }}</span>
                                @endforelse
                                @if ($row['has_recorded'])
                                    <span class="mt-1 block text-caption-sm text-text-tertiary">{{ __('attendance.bulk.kept_help') }}</span>
                                @endif
                            </x-table.td>
                            <x-table.td :label="__('attendance.bulk.mark_as')">
                                <x-form.select :name="'employees['.$employee->id.'][action]'" :id="'action-'.$employee->id" :label="__('attendance.bulk.mark_as')" hide-label :error-keys="[$key.'.action']" data-bulk-action>
                                    @foreach ($actions as $option)
                                        <option value="{{ $option['value'] }}" @selected(old($key.'.action', 'present') === $option['value'])>{{ $option['label'] }}</option>
                                    @endforeach
                                </x-form.select>
                            </x-table.td>
                            <x-table.td :label="__('attendance.bulk.times')">
                                <div class="flex gap-2" data-bulk-times>
                                    <x-form.input type="time" :name="'employees['.$employee->id.'][check_in]'" :id="'in-'.$employee->id" :label="__('attendance.fields.check_in')" hide-label
                                        :value="old($key.'.check_in')" :error-keys="[$key.'.check_in']" class="w-28" data-bulk-in />
                                    <x-form.input type="time" :name="'employees['.$employee->id.'][check_out]'" :id="'out-'.$employee->id" :label="__('attendance.fields.check_out')" hide-label
                                        :value="old($key.'.check_out')" :error-keys="[$key.'.check_out']" class="w-28" data-bulk-out />
                                </div>
                            </x-table.td>
                            <x-table.td :label="__('attendance.fields.note')">
                                <x-form.input :name="'employees['.$employee->id.'][note]'" :id="'note-'.$employee->id" :label="__('attendance.fields.note')" hide-label :value="old($key.'.note')" maxlength="500" />
                            </x-table.td>
                        </tr>
                    @empty
                        <x-table.empty :colspan="7" icon="users" :title="__('attendance.bulk.no_eligible')" :description="__('attendance.bulk.no_eligible_help')" />
                    @endforelse
                </x-table>
            </x-card>

            @if ($rows !== [])
                <x-card>
                    <x-form.textarea name="note" :label="__('attendance.bulk.batch_note')" rows="2" maxlength="1000">{{ old('note') }}</x-form.textarea>
                    <x-slot:footer>
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-body-sm text-text-tertiary">{{ __('attendance.bulk.review_help') }}</p>
                            <x-button icon="arrow-right">{{ __('attendance.bulk.review') }}</x-button>
                        </div>
                    </x-slot:footer>
                </x-card>
            @endif
        </form>
    @endif
@endsection
