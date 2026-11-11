<?php

namespace App\Http\Controllers\Admin\Employees;

use App\Actions\Structure\DeleteStructureRecord;
use App\Enums\CustomFieldType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Employees\CustomFieldRequest;
use App\Models\EmployeeCustomField;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Admin-defined employee fields (employees.manage, enforced on the routes).
 */
class EmployeeCustomFieldController extends Controller
{
    public function index(): View
    {
        return view('admin.employees.custom-fields.index', [
            'fields' => EmployeeCustomField::query()->withCount('values')->with('options')->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new EmployeeCustomField(['is_active' => true, 'type' => CustomFieldType::Text]));
    }

    public function store(CustomFieldRequest $request): RedirectResponse
    {
        $this->save(new EmployeeCustomField, $request);

        return redirect()->route('admin.employee-custom-fields.index')->with('status', __('employees.custom_fields.saved'));
    }

    public function edit(EmployeeCustomField $customField): View
    {
        return $this->form($customField->load('options'));
    }

    public function update(CustomFieldRequest $request, EmployeeCustomField $customField): RedirectResponse
    {
        $this->save($customField, $request);

        return redirect()->route('admin.employee-custom-fields.index')->with('status', __('employees.custom_fields.saved'));
    }

    public function destroy(EmployeeCustomField $customField, DeleteStructureRecord $delete): RedirectResponse
    {
        $delete->handle($customField);

        return redirect()->route('admin.employee-custom-fields.index')->with('status', __('structure.messages.deleted', ['name' => $customField->name]));
    }

    private function save(EmployeeCustomField $field, CustomFieldRequest $request): void
    {
        DB::transaction(function () use ($field, $request): void {
            $field->fill($request->fieldData())->save();
            $options = $request->options();
            $field->options()->whereNotIn('value', array_column($options, 'value'))->delete();

            foreach ($options as $index => $option) {
                $field->options()->updateOrCreate(['value' => $option['value']], [...$option, 'sort_order' => $index]);
            }
        });
    }

    private function form(EmployeeCustomField $field): View
    {
        return view('admin.employees.custom-fields.form', [
            'field' => $field,
            'types' => CustomFieldType::cases(),
            'locked' => $field->exists && $field->values()->exists(),
            'optionsText' => $field->exists
                ? $field->options->map(fn ($option) => $option->value.'|'.$option->label_ar.'|'.$option->label_en)->implode("\n")
                : '',
        ]);
    }
}
