@extends('layouts.admin', ['subtitle' => __('training.budgets.subtitle')])

@section('title', __('training.budgets.title'))

@section('content')
    <x-filter-bar :action="route('admin.training-budgets.index')" :reset="route('admin.training-budgets.index')">
        <x-form.select name="year" :label="__('training.fields.year')" hide-label class="md:w-32">
            @foreach ($years as $option)
                <option value="{{ $option }}" @selected($option === $year)>{{ $option }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    @php($company = $rows->first(fn ($row) => $row['budget']->isCompanyWide()))
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        <x-stat-card :label="__('training.budgets.spent_year', ['year' => $year])" :value="App\Support\Money::format($totalSpent)" icon="coins" :hint="$currency" />
        @if ($company)
            <x-stat-card :label="__('training.budgets.company_budget')" :value="App\Support\Money::format($company['budget']->amount)" icon="wallet" :hint="$currency" />
            <x-stat-card :label="__('training.budgets.remaining')" :value="App\Support\Money::format($company['remaining'])" icon="bar-chart" :hint="__('training.budgets.used', ['percent' => $company['used_percent']])" />
        @endif
    </div>

    <x-table :caption="__('training.budgets.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-48">{{ __('training.fields.department') }}</x-table.th>
                <x-table.th>{{ __('training.fields.amount') }}</x-table.th>
                <x-table.th>{{ __('training.budgets.spent') }}</x-table.th>
                <x-table.th>{{ __('training.budgets.committed') }}</x-table.th>
                <x-table.th>{{ __('training.budgets.remaining') }}</x-table.th>
                <x-table.th class="min-w-40">{{ __('training.budgets.usage') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($rows as $row)
            @php($budget = $row['budget'])
            <x-table.row>
                <x-table.td :label="__('training.fields.department')">
                    <span class="text-label-md text-text-primary">{{ $budget->isCompanyWide() ? __('training.budgets.company_wide') : $budget->department?->name }}</span>
                    @if ($budget->notes)<span class="block text-caption-md text-text-tertiary">{{ $budget->notes }}</span>@endif
                </x-table.td>
                <x-table.td :label="__('training.fields.amount')">@include('admin.payroll._money', ['amount' => $budget->amount])</x-table.td>
                <x-table.td :label="__('training.budgets.spent')">@include('admin.payroll._money', ['amount' => $row['spent']])</x-table.td>
                <x-table.td :label="__('training.budgets.committed')">@include('admin.payroll._money', ['amount' => $row['committed']])</x-table.td>
                <x-table.td :label="__('training.budgets.remaining')">
                    <span @class(['text-feedback-danger-text' => $row['over']])>@include('admin.payroll._money', ['amount' => $row['remaining']])</span>
                </x-table.td>
                <x-table.td :label="__('training.budgets.usage')">
                    <div class="h-2 w-full overflow-hidden rounded-full bg-surface-subtle" role="img" aria-label="{{ __('training.budgets.used', ['percent' => $row['used_percent']]) }}">
                        <div @class(['h-full rounded-full', 'bg-feedback-danger-solid' => $row['over'], 'bg-action-primary-default' => ! $row['over']]) style="inline-size: {{ min(100, (float) $row['used_percent']) }}%"></div>
                    </div>
                    <span class="mt-1 block text-caption-md text-text-tertiary" dir="ltr">{{ $row['used_percent'] }}%</span>
                    @if ($row['over'])<x-badge tone="danger">{{ __('training.budgets.over') }}</x-badge>@endif
                </x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        @can('update', $budget)
                            <x-icon-button icon="pencil" size="sm" :href="route('admin.training-budgets.index', ['year' => $year, 'edit' => $budget->id]).'#budget-form'" :label="__('app.actions.edit')" />
                        @endcan
                        @can('delete', $budget)
                            <form method="POST" action="{{ route('admin.training-budgets.destroy', $budget) }}" data-confirm="{{ __('training.budgets.delete_confirm') }}" data-confirm-tone="danger">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="7" icon="coins" :title="__('training.budgets.empty', ['year' => $year])" />
        @endforelse
    </x-table>

    @if ($edit ? auth()->user()->can('update', $edit) : auth()->user()->can('create', App\Models\TrainingBudget::class))
        <x-card :title="$edit ? __('training.budgets.edit') : __('training.budgets.create')" :subtitle="__('training.budgets.help')" id="budget-form">
            <form method="POST" action="{{ $edit ? route('admin.training-budgets.update', $edit) : route('admin.training-budgets.store') }}" class="grid gap-3 md:grid-cols-2 lg:grid-cols-[8rem_2fr_1fr_2fr_auto] lg:items-end" data-validate>
                @csrf
                @if ($edit)
                    @method('PUT')
                @endif
                <x-form.input type="number" name="year" min="2000" max="2100" required :label="__('training.fields.year')" :value="old('year', $edit->year ?? $year)" />
                <x-form.select name="department_id" :label="__('training.fields.department')">
                    <option value="">{{ __('training.budgets.company_wide') }}</option>
                    @foreach ($departments as $department)
                        <option value="{{ $department->id }}" @selected((string) old('department_id', $edit?->department_id) === (string) $department->id)>{{ $department->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input type="number" name="amount" step="0.01" min="0" dir="ltr" required :label="__('training.fields.amount')" :value="old('amount', $edit?->amount)" :help="$currency" />
                <x-form.input name="notes" :label="__('training.fields.notes')" maxlength="1000" :value="old('notes', $edit?->notes)" />
                <x-button icon="check">{{ __('app.actions.save') }}</x-button>
            </form>
        </x-card>
    @endif
@endsection
