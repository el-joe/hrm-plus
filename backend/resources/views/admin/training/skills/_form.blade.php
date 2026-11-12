<x-form.section :title="__('training.skills.identity')">
    @include('admin.structure._identity-fields')
    <x-form.input name="category" :label="__('training.fields.category')" :value="old('category', $record->category)" maxlength="50" list="skill-categories" :help="__('training.skills.category_help')" />
    <datalist id="skill-categories">
        @foreach ($categories as $category)
            <option value="{{ $category }}"></option>
        @endforeach
    </datalist>
    @include('admin.structure._active-switch')
</x-form.section>
