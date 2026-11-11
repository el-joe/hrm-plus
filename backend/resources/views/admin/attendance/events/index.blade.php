@extends('layouts.admin', ['subtitle' => __('devices.events.subtitle')])

@section('title', __('devices.events.title'))

@section('content')
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4" data-region="event-counts">
        <x-stat-card :label="__('devices.events.states.unmatched')" :value="(string) $counts['unmatched']" icon="users" :href="route('admin.attendance.events.index', ['state' => 'unmatched'])" />
        <x-stat-card :label="__('devices.events.states.pending')" :value="(string) $counts['pending']" icon="clock" :href="route('admin.attendance.events.index', ['state' => 'pending'])" />
    </div>

    <x-filter-bar :action="route('admin.attendance.events.index')" :reset="route('admin.attendance.events.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('devices.events.search')" :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="state" :label="__('attendance.fields.event_status')" hide-label class="md:w-40">
            <option value="">{{ __('attendance.fields.event_status') }}: {{ __('app.common.all') }}</option>
            @foreach ($states as $state)
                <option value="{{ $state }}" @selected(($filters['state'] ?? '') === $state)>{{ __('devices.events.states.'.$state) }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="source" :label="__('attendance.fields.source')" hide-label class="md:w-40">
            <option value="">{{ __('attendance.fields.source') }}: {{ __('app.common.all') }}</option>
            @foreach ($sources as $source)
                <option value="{{ $source->value }}" @selected(($filters['source'] ?? '') === $source->value)>{{ $source->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="device_id" :label="__('devices.fields.device')" hide-label class="md:w-48">
            <option value="">{{ __('devices.fields.device') }}: {{ __('app.common.all') }}</option>
            @foreach ($devices as $device)
                <option value="{{ $device->id }}" @selected((string) ($filters['device_id'] ?? '') === (string) $device->id)>{{ $device->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.input name="from" type="date" :label="__('attendance.fields.from')" hide-label :value="$filters['from'] ?? ''" class="md:w-40" />
        <x-form.input name="to" type="date" :label="__('attendance.fields.to')" hide-label :value="$filters['to'] ?? ''" class="md:w-40" />
    </x-filter-bar>

    <x-table :caption="__('devices.events.title')">
        <x-slot:head>
            <tr>
                <x-table.th>#</x-table.th>
                <x-table.th>{{ __('attendance.fields.time') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('attendance.fields.employee') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.event') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.source') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.event_status') }}</x-table.th>
                <x-table.th class="min-w-64">{{ __('app.common.actions') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($events as $event)
            @php $deviceEvent = $deviceEvents->get($event->id); @endphp
            <x-table.row @class(['opacity-60' => $event->isVoided()])>
                <x-table.td label="#" class="tabular-nums text-text-tertiary">{{ $event->id }}</x-table.td>
                <x-table.td :label="__('attendance.fields.time')" class="tabular-nums">
                    <span dir="ltr">{{ $event->kind->value === 'punch' ? $event->occurred_at->setTimezone($event->timezone)->format('Y-m-d H:i:s') : $event->work_date?->format('Y-m-d') }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $event->timezone }}</span>
                </x-table.td>
                <x-table.td :label="__('attendance.fields.employee')">
                    @if ($event->employee)
                        <span class="block text-label-md">{{ $event->employee->name }}</span>
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $event->employee->employee_number }}</span>
                    @else
                        <x-badge tone="warning">{{ __('devices.events.states.unmatched') }}</x-badge>
                    @endif
                    @if ($deviceEvent)
                        <span class="block text-caption-md text-text-tertiary">{{ __('devices.events.device_user', ['id' => $deviceEvent->device_user_id]) }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('attendance.fields.event')">
                    {{ $event->kind->label() }}{{ $event->direction ? ': '.$event->direction->label() : '' }}
                    @if ($event->punch?->is_duplicate)<x-badge tone="warning">{{ __('attendance.days.duplicate') }}</x-badge>@endif
                </x-table.td>
                <x-table.td :label="__('attendance.fields.source')">
                    @include('admin.attendance._sources', ['sources' => [$event->source]])
                    @if ($deviceEvent?->device)
                        <span class="block text-caption-md text-text-tertiary">{{ $deviceEvent->device->name }}</span>
                        @if ($deviceEvent->device->isSimulated()) @include('admin.attendance._simulated') @endif
                    @elseif ($event->source_reference)
                        <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $event->source_reference }}</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('attendance.fields.event_status')">
                    <x-badge :tone="match ($event->status->value) { 'processed' => 'success', 'unmatched' => 'warning', 'void' => 'danger', default => 'neutral' }">{{ $event->status->label() }}</x-badge>
                    @if ($event->void_reason)<span class="block text-caption-md text-text-tertiary">{{ $event->void_reason }}</span>@endif
                </x-table.td>
                <x-table.td :label="__('app.common.actions')">
                    @if ($event->status->value === 'unmatched' && auth()->user()->can('attendance.update'))
                        <form method="POST" action="{{ route('admin.attendance.events.match', $event) }}" class="flex flex-wrap items-center gap-2">
                            @csrf
                            <x-form.select name="employee_id" :id="'match-'.$event->id" :label="__('devices.fields.employee')" hide-label required class="w-52">
                                <option value="">{{ __('devices.events.pick_employee') }}</option>
                                @foreach ($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->label() }}</option>
                                @endforeach
                            </x-form.select>
                            @if ($deviceEvent && auth()->user()->can('attendance_devices.manage'))
                                <x-form.checkbox name="remember" value="1" :id="'remember-'.$event->id" :label="__('devices.events.remember')" :checked="true" />
                            @endif
                            <x-button size="sm" icon="check">{{ __('devices.events.match') }}</x-button>
                        </form>
                    @endif
                    @if (! $event->isVoided() && auth()->user()->can('attendance.update'))
                        <form method="POST" action="{{ route('admin.attendance.events.void', $event) }}" class="mt-2 flex items-center gap-2"
                            data-confirm="{{ __('attendance.days.confirm_void') }}" data-confirm-tone="warning" data-confirm-label="{{ __('attendance.days.void') }}">
                            @csrf
                            <x-form.input name="reason" :id="'void-'.$event->id" :label="__('attendance.fields.reason')" hide-label required maxlength="500" :placeholder="__('attendance.fields.reason')" class="w-40" />
                            <x-icon-button type="submit" icon="x-circle" size="sm" variant="danger" :label="__('attendance.days.void')" />
                        </form>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="inbox" :title="__('devices.events.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$events" />
        </x-slot:footer>
    </x-table>
@endsection
