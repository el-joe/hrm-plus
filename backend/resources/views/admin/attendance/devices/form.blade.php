@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('devices.index.title'), 'url' => route('admin.attendance-devices.index')]],
    'subtitle' => __('devices.form.subtitle'),
])

@section('title', $device->exists ? __('devices.form.edit', ['name' => $device->name]) : __('devices.index.create'))

@section('content')
    <form method="POST" action="{{ $device->exists ? route('admin.attendance-devices.update', $device) : route('admin.attendance-devices.store') }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @if ($device->exists) @method('PUT') @endif

        <x-form.section :title="__('devices.form.identity')" :description="__('devices.form.identity_help')">
            <x-form.input name="name" :label="__('devices.fields.name')" :value="old('name', $device->name)" required maxlength="150" />
            <x-form.input name="serial_number" :label="__('devices.fields.serial_number')" :value="old('serial_number', $device->serial_number)" required maxlength="100" dir="ltr" pattern="[A-Za-z0-9._\-]+" :help="__('devices.form.serial_help')" />
            <x-form.select name="branch_id" :label="__('attendance.fields.branch')" :required="auth()->user()->hasDataRestrictions()">
                <option value="">—</option>
                @foreach ($branches as $branch)
                    <option value="{{ $branch->id }}" @selected((string) old('branch_id', $device->branch_id) === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="branch_location_id" :label="__('devices.fields.location')" :help="__('devices.form.location_help')">
                <option value="">—</option>
                @foreach ($locations as $location)
                    <option value="{{ $location->id }}" @selected((string) old('branch_location_id', $device->branch_location_id) === (string) $location->id)>{{ $location->branch?->code }} · {{ $location->name }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="timezone" :label="__('attendance.fields.timezone')" :help="__('devices.form.timezone_help')">
                <option value="">{{ __('devices.form.branch_timezone') }}</option>
                @foreach ($timezones as $zone)
                    <option value="{{ $zone }}" @selected(old('timezone', $device->timezone) === $zone)>{{ $zone }}</option>
                @endforeach
            </x-form.select>
            <div>
                <input type="hidden" name="is_active" value="0">
                <x-form.switch name="is_active" :label="__('devices.fields.is_active')" :checked="(bool) old('is_active', $device->is_active)" />
            </div>
        </x-form.section>

        <x-form.section :title="__('devices.form.connection')" :description="__('devices.form.connection_help')">
            <x-form.select name="adapter" :label="__('devices.fields.adapter')" required :help="__('devices.form.adapter_help')">
                @foreach ($adapters as $adapter)
                    <option value="{{ $adapter['key'] }}" @selected(old('adapter', $device->adapter) === $adapter['key'])>{{ __('devices.adapters.'.$adapter['key']) }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="connection_mode" :label="__('devices.fields.connection_mode')" required :help="__('devices.form.mode_help')">
                @foreach ($modes as $mode)
                    <option value="{{ $mode->value }}" @selected(old('connection_mode', $device->connection_mode?->value) === $mode->value)>{{ $mode->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.input name="host" :label="__('devices.fields.host')" :value="old('host', $device->host)" maxlength="255" dir="ltr" />
            <x-form.input name="port" type="number" :label="__('devices.fields.port')" :value="old('port', $device->port)" min="1" max="65535" />
            <x-form.input name="credentials" type="password" :label="__('devices.fields.credentials')" autocomplete="new-password" maxlength="500"
                :help="$device->exists && $device->getRawOriginal('credentials') ? __('devices.form.credentials_kept') : __('devices.form.credentials_help')" />
            <x-form.input name="pull_interval_minutes" type="number" :label="__('devices.fields.pull_interval_minutes')" :value="old('pull_interval_minutes', $device->pull_interval_minutes)" required min="1" max="1440" />
        </x-form.section>

        <x-form.section :title="__('devices.form.simulation')" :description="__('devices.form.simulation_help')">
            <div>
                <input type="hidden" name="simulate_failure" value="0">
                <x-form.switch name="simulate_failure" :label="__('devices.fields.simulate_failure')" :checked="(bool) old('simulate_failure', $device->option('simulate_failure'))" />
            </div>
            <x-form.input name="unknown_user_id" :label="__('devices.fields.unknown_user_id')" :value="old('unknown_user_id', $device->option('unknown_user_id'))" maxlength="64" dir="ltr" :help="__('devices.form.unknown_user_help')" />
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$device->exists ? route('admin.attendance-devices.show', $device) : route('admin.attendance-devices.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
