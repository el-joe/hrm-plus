{{--
    Financial tab (employee_financials.view; opening it is logged). Variables: $employee, $salaries, $bankAccounts, $currency.
    Structure, allowances, deductions and loans are on the Compensation tab (Phase 7a).
--}}
@php($canEdit = auth()->user()->can('updateFinancial', $employee))
<div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
    <x-card :title="__('employees.financial.salary')" data-region="salary">
        <p class="text-numeric-xl text-text-primary" dir="ltr">{{ App\Support\Money::format($employee->basic_salary) }} <span class="text-body-md text-text-tertiary">{{ $currency }}</span></p>
        @if ($employee->pay_basis)
            <p class="mt-1"><x-badge tone="info">{{ __('employees.financial.pay_basis') }}: {{ $employee->pay_basis->label() }}</x-badge></p>
        @endif
        <p class="mt-1 text-caption-md text-text-tertiary">{{ __('employees.financial.basic_salary_help') }}</p>
        @if ($canEdit)
            <x-button type="button" variant="secondary" size="sm" icon="pencil" class="mt-4" data-modal-open="change-salary" hidden data-js-only>{{ __('employees.financial.change_salary') }}</x-button>
        @endif
        <p class="mt-4 flex items-center gap-1.5 text-caption-md text-text-tertiary"><x-icon name="lock" size="sm" /> {{ __('employees.profile.access_logged') }}</p>
    </x-card>

    <x-card :title="__('employees.financial.salary_history')" padding="none" class="lg:col-span-2">
        <x-table :caption="__('employees.financial.salary_history')">
            <x-slot:head>
                <tr>
                    <x-table.th>{{ __('employees.fields.effective_date') }}</x-table.th>
                    <x-table.th>{{ __('employees.fields.basic_salary') }}</x-table.th>
                    <x-table.th>{{ __('employees.fields.change_type') }}</x-table.th>
                    <x-table.th>{{ __('employees.fields.reason') }}</x-table.th>
                    <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                </tr>
            </x-slot:head>
            @forelse ($salaries as $salary)
                <x-table.row>
                    <x-table.td :label="__('employees.fields.effective_date')" class="whitespace-nowrap tabular-nums"><span dir="ltr">{{ $salary->effective_date->toDateString() }}</span></x-table.td>
                    <x-table.td :label="__('employees.fields.basic_salary')" class="whitespace-nowrap tabular-nums">
                        <span dir="ltr" @class(['line-through text-text-tertiary' => $salary->cancelled_at])>{{ App\Support\Money::format($salary->basic_salary) }} {{ $salary->currency }}</span>
                        @if ($salary->previous_salary !== null)
                            <span class="block text-caption-md text-text-tertiary" dir="ltr">← {{ App\Support\Money::format($salary->previous_salary) }}</span>
                        @endif
                    </x-table.td>
                    <x-table.td :label="__('employees.fields.change_type')">
                        {{ $salary->change_type->label() }}
                        <span class="block text-caption-md text-text-tertiary">{{ $salary->pay_basis->label() }}</span>
                        @if ($salary->isPending())
                            <x-badge tone="info">{{ __('employees.history.scheduled') }}</x-badge>
                        @elseif ($salary->cancelled_at)
                            <x-badge>{{ __('employees.history.cancelled') }}</x-badge>
                        @endif
                    </x-table.td>
                    <x-table.td :label="__('employees.fields.reason')" class="text-text-secondary">
                        {{ $salary->reason ?? '—' }}
                        <span class="block text-caption-md text-text-tertiary">{{ $salary->creator?->name }}</span>
                    </x-table.td>
                    <x-table.td>
                        @if ($canEdit && $salary->isPending())
                            <form method="POST" action="{{ route('admin.employees.salaries.destroy', [$employee, $salary]) }}" data-confirm="{{ __('employees.profile.confirm_cancel_change') }}" class="flex justify-end">
                                @csrf
                                @method('DELETE')
                                <x-button variant="ghost" size="sm" icon="x">{{ __('employees.profile.cancel_change') }}</x-button>
                            </form>
                        @endif
                    </x-table.td>
                </x-table.row>
            @empty
                <x-table.empty :colspan="5" icon="coins" :title="__('employees.financial.no_salary')" />
            @endforelse
        </x-table>
    </x-card>
</div>

<x-card :title="__('employees.financial.bank_accounts')" padding="none" data-region="bank-accounts">
    @if ($canEdit)
        <x-slot:actions>
            <x-button type="button" size="sm" icon="plus" data-modal-open="bank-account-new" hidden data-js-only>{{ __('employees.financial.add_account') }}</x-button>
        </x-slot:actions>
    @endif
    <x-table :caption="__('employees.financial.bank_accounts')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('employees.fields.bank_name') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.account_holder') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.iban') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.account_number') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($bankAccounts as $account)
            <x-table.row>
                <x-table.td :label="__('employees.fields.bank_name')">
                    {{ $account->bank_name }}
                    @if ($account->is_primary) <x-badge tone="primary">{{ __('employees.financial.primary') }}</x-badge> @endif
                    @if ($account->swift_code) <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $account->swift_code }}</span> @endif
                </x-table.td>
                <x-table.td :label="__('employees.fields.account_holder')">{{ $account->account_holder }}</x-table.td>
                <x-table.td :label="__('employees.fields.iban')" class="tabular-nums"><span dir="ltr">{{ $account->iban ?? '—' }}</span></x-table.td>
                <x-table.td :label="__('employees.fields.account_number')" class="tabular-nums"><span dir="ltr">{{ $account->account_number ?? '—' }}</span></x-table.td>
                <x-table.td>
                    @if ($canEdit)
                        <div class="flex items-center justify-end gap-1">
                            <x-icon-button type="button" icon="pencil" size="sm" :label="__('app.actions.edit')" data-modal-open="bank-account-{{ $account->id }}" hidden data-js-only />
                            <form method="POST" action="{{ route('admin.employees.bank-accounts.destroy', [$employee, $account]) }}" data-confirm="{{ __('employees.financial.confirm_delete_account') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        </div>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="coins" :title="__('employees.financial.no_accounts')" />
        @endforelse
    </x-table>
</x-card>

@if ($errors->any())
    <x-alert tone="danger" :title="__('employees.financial.save_failed')">
        <ul class="mt-1 list-disc ps-5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif

@if ($canEdit)
    <x-modal id="change-salary" :title="__('employees.financial.change_salary')">
        <form method="POST" action="{{ route('admin.employees.salaries.store', $employee) }}" id="change-salary-form" class="flex flex-col gap-4" data-validate>
            @csrf
            <x-form.input name="basic_salary" type="number" min="0" step="0.01" inputmode="decimal" dir="ltr" required
                :label="__('employees.fields.basic_salary').' ('.$currency.')'" :value="old('basic_salary', $employee->basic_salary)" />
            <x-form.input name="effective_date" type="date" required :label="__('employees.fields.effective_date')" :value="old('effective_date', now()->toDateString())"
                :help="__('employees.form.effective_help')" />
            <x-form.select name="pay_basis" :label="__('employees.financial.pay_basis')">
                <option value="">{{ __('employees.financial.pay_basis_keep') }}{{ $employee->pay_basis ? ' ('.$employee->pay_basis->label().')' : '' }}</option>
                @foreach (App\Enums\PayBasis::cases() as $basis)
                    <option value="{{ $basis->value }}" @selected(old('pay_basis') === $basis->value)>{{ $basis->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.select name="change_type" :label="__('employees.fields.change_type')">
                <option value="">{{ __('employees.financial.auto_type') }}</option>
                @foreach ([App\Enums\SalaryChangeType::Promotion, App\Enums\SalaryChangeType::Adjustment] as $type)
                    <option value="{{ $type->value }}">{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <x-form.textarea name="reason" :label="__('employees.fields.reason')" required maxlength="1000" rows="2">{{ old('reason') }}</x-form.textarea>
        </form>
        <x-slot:footer>
            <x-button type="button" variant="secondary" data-modal-close>{{ __('app.actions.cancel') }}</x-button>
            <x-button form="change-salary-form">{{ __('app.actions.save') }}</x-button>
        </x-slot:footer>
    </x-modal>

    @foreach ([null, ...$bankAccounts] as $account)
        @php($modalId = 'bank-account-'.($account?->id ?? 'new'))
        <x-modal :id="$modalId" :title="$account ? __('app.actions.edit').': '.$account->bank_name : __('employees.financial.add_account')">
            <form method="POST" action="{{ $account ? route('admin.employees.bank-accounts.update', [$employee, $account]) : route('admin.employees.bank-accounts.store', $employee) }}"
                id="{{ $modalId }}-form" class="flex flex-col gap-4" data-validate>
                @csrf
                @if ($account) @method('PUT') @endif
                <x-form.input name="bank_name" :id="$modalId.'-bank'" :label="__('employees.fields.bank_name')" :value="$account?->bank_name" required maxlength="150" />
                <x-form.input name="account_holder" :id="$modalId.'-holder'" :label="__('employees.fields.account_holder')" :value="$account?->account_holder ?? $employee->name" required maxlength="150" />
                <x-form.input name="iban" :id="$modalId.'-iban'" :label="__('employees.fields.iban')" :value="$account?->iban" maxlength="34" dir="ltr" autocomplete="off" />
                <x-form.input name="account_number" :id="$modalId.'-number'" :label="__('employees.fields.account_number')" :value="$account?->account_number" maxlength="34" dir="ltr" autocomplete="off" />
                <x-form.input name="swift_code" :id="$modalId.'-swift'" :label="__('employees.fields.swift_code')" :value="$account?->swift_code" maxlength="11" dir="ltr" />
                <div>
                    <input type="hidden" name="is_primary" value="0">
                    <x-form.switch name="is_primary" :id="$modalId.'-primary'" :label="__('employees.financial.primary')" :checked="(bool) ($account?->is_primary)" />
                </div>
            </form>
            <x-slot:footer>
                <x-button type="button" variant="secondary" data-modal-close>{{ __('app.actions.cancel') }}</x-button>
                <x-button :form="$modalId.'-form'">{{ __('app.actions.save') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endforeach
@endif
