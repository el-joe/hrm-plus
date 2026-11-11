{{--
    Select filter on a related record. Variables: $name (query key), $label, $options (models with id + name), $filters.
--}}
<x-form.select :name="$name" :id="'filter-'.$name" :label="$label" hide-label class="md:w-52">
    <option value="">{{ $label }}: {{ __('app.common.all') }}</option>
    @foreach ($options as $option)
        <option value="{{ $option->id }}" @selected((string) ($filters[$name] ?? '') === (string) $option->id)>{{ $option->code ? $option->code.' · ' : '' }}{{ $option->name }}</option>
    @endforeach
</x-form.select>
