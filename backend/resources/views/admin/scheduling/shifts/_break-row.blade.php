{{-- One break row of the shift form. Variables: $index, $row (array), $hideWhenEmpty (bool). --}}
@php
    $isEmpty = blank($row['start_time'] ?? null) && blank($row['duration_minutes'] ?? null) && blank($row['name'] ?? null);
@endphp
<div class="grid gap-3 rounded-lg border border-border-subtle p-3 sm:grid-cols-2 lg:grid-cols-6 lg:items-end" data-break-row
    @if ($hideWhenEmpty && $isEmpty) data-empty-row @endif>
    <x-form.input name="breaks[{{ $index }}][name]" :label="__('schedules.fields.break_name')" :value="$row['name'] ?? ''" maxlength="100" class="lg:col-span-1" />
    <x-form.select name="breaks[{{ $index }}][type]" :label="__('schedules.fields.break_type')">
        @foreach (App\Enums\BreakType::cases() as $breakType)
            <option value="{{ $breakType->value }}" @selected(($row['type'] ?? 'fixed') === $breakType->value)>{{ $breakType->label() }}</option>
        @endforeach
    </x-form.select>
    <x-form.input type="time" name="breaks[{{ $index }}][start_time]" :label="__('schedules.fields.break_start')" :value="$row['start_time'] ?? ''" dir="ltr" />
    <x-form.input type="time" name="breaks[{{ $index }}][end_time]" :label="__('schedules.fields.break_end')" :value="$row['end_time'] ?? ''" dir="ltr" />
    <x-form.input type="number" name="breaks[{{ $index }}][duration_minutes]" :label="__('schedules.fields.duration_minutes')" :value="$row['duration_minutes'] ?? ''" min="1" max="480" />
    <div class="flex items-center justify-between gap-2 pb-2">
        <input type="hidden" name="breaks[{{ $index }}][is_paid]" value="0">
        <x-form.checkbox name="breaks[{{ $index }}][is_paid]" :id="'break-paid-'.$index" :label="__('schedules.fields.is_paid')" :checked="(bool) ($row['is_paid'] ?? false)" />
        <x-icon-button type="button" icon="trash" size="sm" variant="danger" :label="__('schedules.common.remove')" hidden data-js-only data-remove-break />
    </div>
</div>
