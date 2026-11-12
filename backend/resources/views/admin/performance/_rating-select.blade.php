{{-- Rating select of one item. Variables: $name, $id, $label, $levels, $selected (?string), $required (bool). --}}
<x-form.select :name="$name" :id="$id" :label="$label" hide-label :required="$required ?? false" class="min-w-44">
    <option value="">{{ __('performance.reviews.choose_rating') }}</option>
    @foreach ($levels as $level)
        <option value="{{ $level->value }}" @selected($selected !== null && bccomp((string) $selected, (string) $level->value, 2) === 0)>{{ rtrim(rtrim((string) $level->value, '0'), '.') }} — {{ $level->label }}</option>
    @endforeach
</x-form.select>
