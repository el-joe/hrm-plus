{{--
    Fields of a leave request (admin "on behalf" form and portal "My leaves"); the form needs
    data-leave-form and enctype multipart. Variables: $types (LeaveType), $parts (LeaveDayPart cases),
    $allowHalfDay (bool), optional $today (CarbonImmutable).
--}}
@php
    $unitsFor = fn ($part) => match ($part->value) {
        'full' => 'day,half_day,hour',
        'first_half', 'second_half' => $allowHalfDay ? 'half_day,hour' : '',
        'hours' => 'hour',
    };
@endphp
<x-form.select name="leave_type_id" :label="__('leave.fields.leave_type_id')" required>
    <option value="">—</option>
    @foreach ($types as $type)
        <option value="{{ $type->id }}" data-unit="{{ $type->unit->value }}"
            data-attachment-hint="{{ $type->requires_attachment ? ($type->attachment_after_days !== null ? __('leave.types.attachment_after_short', ['days' => $type->attachment_after_days]) : __('leave.types.attachment_always_short')) : '' }}"
            @selected((string) old('leave_type_id') === (string) $type->id)>{{ $type->name }}</option>
    @endforeach
</x-form.select>
<x-form.select name="day_part" :label="__('leave.fields.day_part')">
    @foreach ($parts as $part)
        @continue($part->isHalf() && ! $allowHalfDay)
        <option value="{{ $part->value }}" data-units="{{ $unitsFor($part) }}" @selected(old('day_part', 'full') === $part->value)>{{ $part->label() }}</option>
    @endforeach
</x-form.select>
<x-form.input type="date" name="start_date" :label="__('leave.fields.start_date')" :value="old('start_date', isset($today) ? $today->format('Y-m-d') : null)" required />
<div data-leave-end>
    <x-form.input type="date" name="end_date" :label="__('leave.fields.end_date')" :value="old('end_date')" :help="__('leave.help.end_date')" />
</div>
<div data-leave-hours class="grid grid-cols-2 gap-3 md:col-span-2">
    <x-form.input type="number" name="hours" step="0.25" min="0.25" max="24" :label="__('leave.fields.hours')" :value="old('hours')" />
    <x-form.input type="time" name="start_time" :label="__('leave.fields.start_time')" :value="old('start_time')" />
</div>
<div class="md:col-span-2">
    <x-form.textarea name="reason" :label="__('leave.fields.reason')" rows="2" maxlength="1000">{{ old('reason') }}</x-form.textarea>
</div>
<div class="md:col-span-2">
    <x-form.file-input name="attachments[]" :label="__('leave.fields.attachments')" multiple :error-keys="['attachments', 'attachments.0', 'attachments.1', 'attachments.2']"
        :help="__('leave.help.attachments', ['types' => implode(', ', (array) setting('documents.allowed_extensions')), 'size' => (int) setting('documents.max_upload_size_kb')])" />
    <p class="mt-1 text-caption-md text-text-secondary" data-leave-attachment-hint aria-live="polite"></p>
</div>
