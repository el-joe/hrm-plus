{{--
    Rule fields of a policy version, by section. Variables: $rules (AttendancePolicyVersion with the values to show),
    $sections, $booleans, $decimals, $enumOptions (field => enum cases).
--}}
@foreach ($sections as $section => $fields)
    <x-form.section :title="__('schedules.policies.sections.'.$section)">
        @foreach ($fields as $field)
            @php
                $label = __('schedules.policies.rule_labels.'.$field);
                $help = trans()->has('schedules.policies.rule_help.'.$field) ? __('schedules.policies.rule_help.'.$field) : null;
                $value = old($field, $rules->getAttribute($field));
                $value = $value instanceof BackedEnum ? $value->value : $value;
            @endphp
            @if (in_array($field, $booleans, true))
                <div>
                    <input type="hidden" name="{{ $field }}" value="0">
                    <x-form.switch :name="$field" :label="$label" :description="$help" :checked="(bool) $value" />
                </div>
            @elseif (isset($enumOptions[$field]))
                <x-form.select :name="$field" :label="$label" :help="$help" required>
                    @foreach ($enumOptions[$field] as $option)
                        <option value="{{ $option->value }}" @selected($value === $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </x-form.select>
            @elseif (in_array($field, $decimals, true))
                <x-form.input type="number" :name="$field" :label="$label" :help="$help" :value="$value" required min="1" max="10" step="0.01" dir="ltr" />
            @else
                <x-form.input type="number" :name="$field" :label="$label" :help="$help" :value="$value" min="0" step="1"
                    :required="in_array($field, ['late_grace_minutes', 'late_rounding_minutes', 'early_leave_grace_minutes', 'early_leave_rounding_minutes', 'punch_dedupe_seconds', 'overtime_min_minutes', 'overtime_rounding_minutes', ...App\Models\AttendancePolicyVersion::MOBILE_RULES], true)" />
            @endif
        @endforeach
    </x-form.section>
@endforeach
