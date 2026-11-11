{{--
    Settlement scope fields (wizard step 1 and "change scope"). Variables: $branches, $departments, $teams,
    $positions, $groups, $employees, $selected (['scope' => [...]]).
--}}
@php
    $scope = $selected['scope'] ?? [];
    $ids = fn (string $key): array => array_map('intval', (array) ($scope[$key] ?? []));
    $lists = [
        'branch_ids' => $branches, 'department_ids' => $departments, 'team_ids' => $teams,
        'position_ids' => $positions, 'employee_group_ids' => $groups,
    ];
@endphp
<x-form.section :title="__('payroll_runs.scope.title')" :description="__('payroll_runs.scope.help')">
    <div class="md:col-span-2">
        <input type="hidden" name="scope[all]" value="0">
        <x-form.switch name="scope[all]" id="scope-all" value="1" :label="__('payroll_runs.scope.whole_company')" :description="__('payroll_runs.scope.whole_company_help')" :checked="(bool) ($scope['all'] ?? false)" />
    </div>
    @error('scope')
        <x-alert tone="danger" class="md:col-span-2">{{ $message }}</x-alert>
    @enderror
    @foreach ($lists as $key => $records)
        <fieldset class="flex flex-col gap-2 rounded-md border border-border-subtle p-3" data-scope-dimension>
            <legend class="px-1 text-label-md text-text-primary">{{ __('payroll_runs.scope.dimensions.'.$key) }}</legend>
            @if ($records->isEmpty())
                <p class="text-caption-md text-text-tertiary">{{ __('payroll_runs.scope.none_available') }}</p>
            @else
                <div class="flex max-h-48 flex-col gap-2 overflow-y-auto pe-1">
                    @foreach ($records as $record)
                        <x-form.checkbox :name="'scope['.$key.'][]'" :id="'scope-'.$key.'-'.$record->id" :value="$record->id" :checked="in_array($record->id, $ids($key), true)"
                            :label="($record->code ?? '').' · '.$record->name" />
                    @endforeach
                </div>
            @endif
        </fieldset>
    @endforeach
    @foreach (['employee_ids' => 'employee_ids_help', 'exclude_employee_ids' => 'exclude_help'] as $key => $help)
        <x-form.field :label="__('payroll_runs.scope.dimensions.'.$key)" :for="'scope-'.$key" :help="__('payroll_runs.scope.'.$help)" :message="$errors->first('scope.'.$key)">
            <select id="scope-{{ $key }}" name="scope[{{ $key }}][]" multiple size="8" aria-describedby="scope-{{ $key }}-help"
                class="w-full rounded-md border border-border-default bg-surface-default px-2 py-2 text-body-md text-text-primary shadow-xs focus:border-border-focus focus:shadow-focus focus:outline-none">
                @foreach ($employees as $employee)
                    <option value="{{ $employee->id }}" @selected(in_array($employee->id, $ids($key), true))>{{ $employee->label() }}</option>
                @endforeach
            </select>
        </x-form.field>
    @endforeach
</x-form.section>
