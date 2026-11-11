@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('payroll.statutory.title'), 'url' => route('admin.statutory-rules.index')]],
    'subtitle' => $record->type->label().' · '.$record->country_code,
])

@section('title', $record->name)

@section('page-actions')
    @can('update', $record)
        <x-button variant="secondary" icon="pencil" :href="route('admin.statutory-rules.edit', $record)">{{ __('app.actions.edit') }}</x-button>
        <x-button icon="plus" :href="route('admin.statutory-rules.versions.create', $record)">{{ __('payroll.statutory.new_version') }}</x-button>
    @endcan
@endsection

@section('content')
    @if ($record->is_demo)
        <x-alert tone="warning" :title="__('payroll.common.demo_badge')">{{ __('payroll.common.demo_help') }}</x-alert>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @include('admin.structure._status-badge', ['active' => $record->is_active])
        @if ($record->description)
            <p class="w-full text-body-sm text-text-secondary">{{ $record->description }}</p>
        @endif
    </div>

    <section class="flex flex-col gap-4" aria-labelledby="versions-title">
        <h2 id="versions-title" class="text-h4 text-text-primary">{{ __('payroll.statutory.versions') }}</h2>
        @forelse ($versions as $version)
            <x-card :title="__('payroll.common.version', ['version' => $version->version])"
                :subtitle="$version->effective_from->toDateString().' → '.($version->effective_to?->toDateString() ?? __('payroll.common.open_ended'))">
                <x-slot:actions>
                    @if ($current?->id === $version->id)
                        <x-badge tone="success" dot>{{ __('payroll.common.current') }}</x-badge>
                    @endif
                    @if ($record->is_demo) @include('admin.payroll._demo') @endif
                </x-slot:actions>
                <p class="mb-3 text-body-sm text-text-secondary">
                    {{ __('payroll_runs.statutory_base.reduces') }}:
                    {{ collect($version->base_reductions ?? [])->map(fn ($value) => App\Enums\PayComponentCategory::tryFrom($value)?->label() ?? $value)->implode(', ') ?: __('payroll.common.none') }}
                    @if ($version->deduct_employee_insurance) · {{ __('payroll_runs.statutory_base.deduct_insurance') }} @endif
                </p>
                @if ($record->type === App\Enums\StatutoryType::SocialInsurance)
                    <x-detail-list>
                        <x-detail-item :label="__('payroll.fields.employee_rate')"><span dir="ltr">{{ App\Services\Payroll\PayrollMath::trim((string) $version->employee_rate) }}%</span></x-detail-item>
                        <x-detail-item :label="__('payroll.fields.employer_rate')"><span dir="ltr">{{ $version->employer_rate !== null ? App\Services\Payroll\PayrollMath::trim((string) $version->employer_rate).'%' : '—' }}</span></x-detail-item>
                        <x-detail-item :label="__('payroll.fields.salary_floor')"><span dir="ltr">{{ App\Support\Money::format($version->salary_floor) }}</span></x-detail-item>
                        <x-detail-item :label="__('payroll.fields.salary_ceiling')"><span dir="ltr">{{ App\Support\Money::format($version->salary_ceiling) }}</span></x-detail-item>
                    </x-detail-list>
                @else
                    <p class="mb-3 text-body-sm text-text-secondary">{{ __('payroll.fields.annual_exemption') }}: <span dir="ltr">{{ App\Support\Money::format($version->annual_exemption) }}</span></p>
                    <x-table :caption="__('payroll.fields.brackets')">
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('payroll.fields.lower_bound') }}</x-table.th>
                                <x-table.th>{{ __('payroll.fields.upper_bound') }}</x-table.th>
                                <x-table.th>{{ __('payroll.fields.rate') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @foreach ($version->brackets as $bracket)
                            <x-table.row>
                                <x-table.td :label="__('payroll.fields.lower_bound')" class="tabular-nums"><span dir="ltr">{{ App\Support\Money::format($bracket->lower_bound) }}</span></x-table.td>
                                <x-table.td :label="__('payroll.fields.upper_bound')" class="tabular-nums"><span dir="ltr">{{ $bracket->upper_bound !== null ? App\Support\Money::format($bracket->upper_bound) : '∞' }}</span></x-table.td>
                                <x-table.td :label="__('payroll.fields.rate')" class="tabular-nums"><span dir="ltr">{{ App\Services\Payroll\PayrollMath::trim((string) $bracket->rate) }}%</span></x-table.td>
                            </x-table.row>
                        @endforeach
                    </x-table>
                @endif
                @if ($version->notes || $version->creator)
                    <p class="mt-3 text-caption-md text-text-tertiary">{{ $version->notes }} @if ($version->creator) · {{ $version->creator->name }} @endif</p>
                @endif
            </x-card>
        @empty
            <x-empty-state icon="shield-check" :title="__('payroll.statutory.no_version')" />
        @endforelse
    </section>
@endsection
