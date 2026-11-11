{{-- Document metadata fields (upload and edit modals). Variables: $document (EmployeeDocument|null), $prefix (id prefix). --}}
<x-form.select name="category" :id="$prefix.'-category'" :label="__('employees.fields.category')" required>
    @foreach (App\Enums\DocumentCategory::cases() as $category)
        <option value="{{ $category->value }}" @selected(old('category', $document?->category?->value) === $category->value)>{{ $category->label() }}</option>
    @endforeach
</x-form.select>
<x-form.input name="title" :id="$prefix.'-title'" :label="__('employees.fields.title')" :value="old('title', $document?->title)" required maxlength="150" />
<x-form.input name="issue_date" :id="$prefix.'-issue'" type="date" :label="__('employees.fields.issue_date')" :value="old('issue_date', $document?->issue_date?->toDateString())" />
<x-form.input name="expiry_date" :id="$prefix.'-expiry'" type="date" :label="__('employees.fields.expiry_date')" :value="old('expiry_date', $document?->expiry_date?->toDateString())" />
<x-form.textarea name="notes" :id="$prefix.'-notes'" :label="__('employees.fields.notes')" maxlength="1000" rows="2">{{ old('notes', $document?->notes) }}</x-form.textarea>
<div>
    <input type="hidden" name="visible_to_employee" value="0">
    <x-form.switch name="visible_to_employee" :id="$prefix.'-visible'" :label="__('employees.fields.visible_to_employee')"
        :description="__('employees.documents.visible_help')" :checked="(bool) old('visible_to_employee', $document?->visible_to_employee)" />
</div>
