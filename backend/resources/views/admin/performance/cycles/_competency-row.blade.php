{{-- One competency row of the cycle form. Variables: $index (int|'__INDEX__'), $row (array), $competencyOptions. --}}
<div class="grid gap-3 rounded-md border border-border-subtle p-3 sm:grid-cols-[1fr_8rem_auto] sm:items-end" data-repeater-row>
    <x-form.select :name="'competencies['.$index.'][competency_id]'" :id="'competency-'.$index.'-id'" :label="__('performance.fields.competency')" :error-keys="['competencies.'.$index.'.competency_id']">
        <option value="">—</option>
        @foreach ($competencyOptions as $competency)
            <option value="{{ $competency->id }}" @selected((string) ($row['competency_id'] ?? '') === (string) $competency->id)>{{ $competency->code }} · {{ $competency->name }} ({{ $competency->category->label() }})</option>
        @endforeach
    </x-form.select>
    <x-form.input type="number" :name="'competencies['.$index.'][weight]'" :id="'competency-'.$index.'-weight'" step="0.01" min="0.01" max="100" dir="ltr"
        :label="__('performance.fields.weight_pct')" :value="isset($row['weight']) ? rtrim(rtrim((string) $row['weight'], '0'), '.') : null" :error-keys="['competencies.'.$index.'.weight']" />
    <div class="flex justify-end">
        <x-icon-button type="button" icon="trash" size="sm" variant="danger" :label="__('performance.cycles.remove_competency')" data-repeater-remove hidden data-js-only />
    </div>
</div>
