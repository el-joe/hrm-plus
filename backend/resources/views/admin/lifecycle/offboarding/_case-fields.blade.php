{{-- Fields of an offboarding case. Variables: $case, $types --}}
<x-form.select name="type" :label="__('onboarding.fields.type')" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $case->type?->value) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</x-form.select>
<x-form.input name="notice_date" type="date" :label="__('onboarding.fields.notice_date')" :value="old('notice_date', $case->notice_date?->toDateString())" />
<x-form.input name="last_working_date" type="date" :label="__('onboarding.fields.last_working_date')" :value="old('last_working_date', $case->last_working_date?->toDateString())" required :help="__('onboarding.offboarding.last_day_help')" />
<div class="flex items-end pb-2">
    <input type="hidden" name="eligible_for_rehire" value="0">
    <x-form.switch name="eligible_for_rehire" :label="__('onboarding.fields.eligible_for_rehire')" :checked="(bool) old('eligible_for_rehire', $case->eligible_for_rehire)" />
</div>
<div class="md:col-span-2">
    <x-form.textarea name="reason" :label="__('onboarding.fields.reason')" rows="3" maxlength="1000">{{ old('reason', $case->reason) }}</x-form.textarea>
</div>
