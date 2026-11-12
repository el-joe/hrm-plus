{{--
    Goal / KPI fields shared by the admin goal form and the portal "add goal" form.
    Variables: $goal (PerformanceGoal), $types, $cycles (Collection<PerformanceCycle>), optional $cycleRequired (bool).
--}}
<x-form.select name="performance_cycle_id" :label="__('performance.fields.cycle')" :required="$cycleRequired ?? false">
    @unless ($cycleRequired ?? false)
        <option value="">{{ __('performance.goals.no_cycle') }}</option>
    @endunless
    @foreach ($cycles as $cycle)
        <option value="{{ $cycle->id }}" @selected((string) old('performance_cycle_id', $goal->performance_cycle_id) === (string) $cycle->id)>{{ $cycle->name }}</option>
    @endforeach
</x-form.select>
<x-form.select name="type" :label="__('performance.fields.type')" required data-switch="goal-type">
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', $goal->type?->value) === $type->value)>{{ $type->label() }}</option>
    @endforeach
</x-form.select>
<div class="md:col-span-2">
    <x-form.input name="title" :label="__('performance.fields.title')" required maxlength="200" :value="old('title', $goal->title)" />
</div>
<div class="md:col-span-2">
    <x-form.textarea name="description" :label="__('performance.fields.description')" rows="3" maxlength="5000">{{ old('description', $goal->description) }}</x-form.textarea>
</div>
<div data-show-for="kpi" data-switch-group="goal-type">
    <x-form.input name="target_value" type="number" step="0.01" min="0.01" dir="ltr" :label="__('performance.fields.target_value')" :help="__('performance.goals.kpi_only')" :value="old('target_value', $goal->target_value)" />
</div>
<div data-show-for="kpi" data-switch-group="goal-type">
    <x-form.input name="unit" maxlength="30" :label="__('performance.fields.unit')" :value="old('unit', $goal->unit)" :placeholder="__('performance.goals.unit_example')" />
</div>
<div data-show-for="kpi" data-switch-group="goal-type">
    <x-form.input name="current_value" type="number" step="0.01" min="0" dir="ltr" :label="__('performance.fields.current_value')" :value="old('current_value', $goal->current_value)" />
</div>
<x-form.input name="weight" type="number" step="0.01" min="0" max="100" dir="ltr" required :label="__('performance.fields.weight_pct')" :help="__('performance.goals.weight_help')" :value="old('weight', $goal->weight)" />
<x-form.input name="start_date" type="date" :label="__('performance.fields.start_date')" :value="old('start_date', $goal->start_date?->toDateString())" />
<x-form.input name="due_date" type="date" :label="__('performance.fields.due_date')" :value="old('due_date', $goal->due_date?->toDateString())" />
