{{-- Fields of a template task. Variables: $task (nullable), $assignees --}}
<x-form.input name="title_ar" :label="__('onboarding.fields.title_ar')" :value="old('title_ar', $task?->title_ar)" required maxlength="200" dir="rtl" />
<x-form.input name="title_en" :label="__('onboarding.fields.title_en')" :value="old('title_en', $task?->title_en)" required maxlength="200" dir="ltr" />
<x-form.select name="assignee" :label="__('onboarding.fields.assignee')" required>
    @foreach ($assignees as $assignee)
        <option value="{{ $assignee->value }}" @selected(old('assignee', $task?->assignee?->value) === $assignee->value)>{{ $assignee->label() }}</option>
    @endforeach
</x-form.select>
<x-form.input name="due_offset_days" type="number" min="-60" max="365" :label="__('onboarding.fields.due_offset_days')" :value="old('due_offset_days', $task?->due_offset_days ?? 0)" required :help="__('onboarding.templates.offset_help')" />
<div class="md:col-span-2">
    <x-form.input name="description" :label="__('app.common.description')" :value="old('description', $task?->description)" maxlength="1000" />
</div>
