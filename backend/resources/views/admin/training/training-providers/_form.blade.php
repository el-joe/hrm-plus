<x-form.section :title="__('training.providers.identity')">
    @include('admin.structure._identity-fields')
    <x-form.select name="type" :label="__('training.fields.type')" required>
        @foreach ($types as $type)
            <option value="{{ $type->value }}" @selected(old('type', $record->type?->value ?? 'external') === $type->value)>{{ $type->label() }}</option>
        @endforeach
    </x-form.select>
    <x-form.input name="website" type="url" dir="ltr" :label="__('training.fields.website')" :value="old('website', $record->website)" maxlength="255" />
</x-form.section>

<x-form.section :title="__('training.providers.contact')">
    <x-form.input name="contact_name" :label="__('training.fields.contact_name')" :value="old('contact_name', $record->contact_name)" maxlength="150" />
    <x-form.input name="email" type="email" dir="ltr" :label="__('training.fields.email')" :value="old('email', $record->email)" maxlength="150" />
    <x-form.input name="phone" dir="ltr" :label="__('training.fields.phone')" :value="old('phone', $record->phone)" maxlength="40" />
    @include('admin.structure._active-switch')
</x-form.section>
