@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('payroll.statutory.title'), 'url' => route('admin.statutory-rules.index')],
    ['label' => $record->name, 'url' => route('admin.statutory-rules.show', $record)],
]])

@section('title', __('payroll.statutory.new_version_title', ['name' => $record->name]))

@section('content')
    @if ($record->is_demo)
        <x-alert tone="warning" :title="__('payroll.common.demo_badge')">{{ __('payroll.common.demo_help') }}</x-alert>
    @endif

    <form method="POST" action="{{ route('admin.statutory-rules.versions.store', $record) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('payroll.statutory.rates')" :description="__('payroll.help.statutory_version', ['version' => ($latest?->version ?? 0) + 1])">
            <x-form.input type="date" name="effective_from" required :label="__('payroll.fields.effective_from')" :value="old('effective_from', $effectiveFrom)" />
            <x-form.input name="notes" maxlength="1000" :label="__('payroll.fields.notes')" :value="old('notes')" />
            @if ($record->type === App\Enums\StatutoryType::SocialInsurance)
                <x-form.input type="number" name="employee_rate" step="0.0001" min="0" max="100" dir="ltr" required :label="__('payroll.fields.employee_rate')" :value="old('employee_rate', App\Services\Payroll\PayrollMath::trim($latest?->employee_rate))" />
                <x-form.input type="number" name="employer_rate" step="0.0001" min="0" max="100" dir="ltr" :label="__('payroll.fields.employer_rate')" :value="old('employer_rate', App\Services\Payroll\PayrollMath::trim($latest?->employer_rate))" />
                <x-form.input type="number" name="salary_floor" step="0.01" min="0" dir="ltr" :label="__('payroll.fields.salary_floor')" :value="old('salary_floor', $latest?->salary_floor)" />
                <x-form.input type="number" name="salary_ceiling" step="0.01" min="0" dir="ltr" :label="__('payroll.fields.salary_ceiling')" :value="old('salary_ceiling', $latest?->salary_ceiling)" />
            @else
                <x-form.input type="number" name="annual_exemption" step="0.01" min="0" dir="ltr" :label="__('payroll.fields.annual_exemption')" :value="old('annual_exemption', $latest?->annual_exemption)" />
                <div class="md:col-span-2 flex flex-col gap-3" data-repeater="brackets">
                    <p class="text-caption-md text-text-tertiary">{{ __('payroll.help.brackets') }}</p>
                    @error('brackets')
                        <x-alert tone="danger">{{ $message }}</x-alert>
                    @enderror
                    <div class="flex flex-col gap-3" data-repeater-rows>
                        @foreach ($brackets as $index => $bracket)
                            @include('admin.payroll.statutory._bracket', ['index' => $index, 'bracket' => $bracket])
                        @endforeach
                    </div>
                    <template data-repeater-template>
                        @include('admin.payroll.statutory._bracket', ['index' => '__INDEX__', 'bracket' => []])
                    </template>
                    <div>
                        <x-button type="button" variant="secondary" size="sm" icon="plus" data-repeater-add hidden data-js-only>{{ __('payroll.structures.add_line') }}</x-button>
                    </div>
                </div>
            @endif
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.statutory-rules.show', $record)">{{ __('app.actions.cancel') }}</x-button>
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
