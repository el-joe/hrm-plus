{{--
    "Applies to" fields of an assignment: the scope type plus one select per type
    (resources/js/pages/schedule-forms.js shows only the chosen one; without JS all are shown
    and the server reads the one of the chosen type).
    Variables: $types (list<AttendanceScope>), $scopeOptions, $currentType (?string), $currentId (?int).
--}}
<x-form.select name="scope_type" :label="__('schedules.fields.scope_type')" required data-scope-type>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('scope_type', $currentType) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</x-form.select>
@foreach ($types as $type)
    @continue($type === App\Enums\AttendanceScope::Company)
    <div data-scope-target="{{ $type->value }}">
        <x-form.select name="scope_ids[{{ $type->value }}]" :id="'scope-'.$type->value" :label="$type->label()">
            <option value="">—</option>
            @foreach ($scopeOptions[$type->value] ?? [] as $option)
                <option value="{{ $option['id'] }}" @selected((string) old('scope_ids.'.$type->value, $currentType === $type->value ? $currentId : '') === (string) $option['id'])>{{ $option['label'] }}</option>
            @endforeach
        </x-form.select>
    </div>
@endforeach
