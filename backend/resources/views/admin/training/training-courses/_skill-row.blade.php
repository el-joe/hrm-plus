{{-- One "skill taught" row. Variables: $index (int|'__INDEX__'), $row (array), $skills. --}}
<div class="grid gap-3 rounded-md border border-border-subtle p-3 sm:grid-cols-[2fr_1fr_auto] sm:items-end" data-repeater-row>
    <x-form.select :name="'skills['.$index.'][skill_id]'" :id="'skill-'.$index" :label="__('training.fields.skill')" :error-keys="['skills.'.$index.'.skill_id']">
        <option value="">—</option>
        @foreach ($skills as $skill)
            <option value="{{ $skill->id }}" @selected((string) ($row['skill_id'] ?? '') === (string) $skill->id)>{{ $skill->name }}</option>
        @endforeach
    </x-form.select>
    <x-form.select :name="'skills['.$index.'][level]'" :id="'skill-level-'.$index" :label="__('training.fields.level')" :error-keys="['skills.'.$index.'.level']">
        <option value="">—</option>
        @for ($level = 1; $level <= App\Models\Skill::MAX_LEVEL; $level++)
            <option value="{{ $level }}" @selected((string) ($row['level'] ?? '') === (string) $level)>{{ $level }} — {{ __('training.levels.'.$level) }}</option>
        @endfor
    </x-form.select>
    <div class="flex justify-end">
        <x-icon-button type="button" icon="trash" size="sm" variant="danger" :label="__('training.courses.remove_skill')" data-repeater-remove hidden data-js-only />
    </div>
</div>
