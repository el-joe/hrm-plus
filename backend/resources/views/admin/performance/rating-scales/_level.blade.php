{{-- One level row of the rating scale form. Variables: $index (int|'__INDEX__'), $row (array), $locked (bool). --}}
<div class="grid gap-3 rounded-md border border-border-subtle p-3 sm:grid-cols-2 lg:grid-cols-[6rem_1fr_1fr_7rem_9rem_auto] lg:items-end" data-repeater-row>
    <x-form.input type="number" :name="'levels['.$index.'][value]'" :id="'level-'.$index.'-value'" step="0.01" min="0" max="100" dir="ltr" :label="__('performance.fields.level_value')"
        :value="isset($row['value']) ? rtrim(rtrim((string) $row['value'], '0'), '.') : null" :readonly="$locked && isset($row['value'])" :error-keys="['levels.'.$index.'.value']" />
    <x-form.input :name="'levels['.$index.'][label_ar]'" :id="'level-'.$index.'-ar'" maxlength="100" dir="rtl" lang="ar" :label="__('performance.fields.label_ar')" :value="$row['label_ar'] ?? null" :error-keys="['levels.'.$index.'.label_ar']" />
    <x-form.input :name="'levels['.$index.'][label_en]'" :id="'level-'.$index.'-en'" maxlength="100" dir="ltr" lang="en" :label="__('performance.fields.label_en')" :value="$row['label_en'] ?? null" :error-keys="['levels.'.$index.'.label_en']" />
    <x-form.input type="number" :name="'levels['.$index.'][min_score]'" :id="'level-'.$index.'-min'" step="0.01" min="0" max="100" dir="ltr" :label="__('performance.fields.min_score')"
        :value="$row['min_score'] ?? null" :readonly="$locked && isset($row['value'])" :error-keys="['levels.'.$index.'.min_score']" />
    <x-form.select :name="'levels['.$index.'][color]'" :id="'level-'.$index.'-color'" :label="__('performance.fields.color')">
        @foreach (App\Models\RatingScaleLevel::COLORS as $color)
            <option value="{{ $color }}" @selected(($row['color'] ?? 'chart-1') === $color)>{{ __('performance.colors.'.$color) }}</option>
        @endforeach
    </x-form.select>
    <div class="flex justify-end">
        @unless ($locked)
            <x-icon-button type="button" icon="trash" size="sm" variant="danger" :label="__('performance.scales.remove_level')" data-repeater-remove hidden data-js-only />
        @endunless
    </div>
    <div class="sm:col-span-2 lg:col-span-6">
        <x-form.input :name="'levels['.$index.'][description]'" :id="'level-'.$index.'-description'" maxlength="500" :label="__('performance.fields.description')" :value="$row['description'] ?? null" />
    </div>
</div>
