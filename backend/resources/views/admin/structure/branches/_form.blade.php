<x-form.section :title="__('structure.common.identity')" :description="__('structure.common.identity_help')">
    @include('admin.structure._identity-fields')
    <div class="md:col-span-2">
        <input type="hidden" name="is_headquarters" value="0">
        <x-form.switch name="is_headquarters" :label="__('structure.branches.headquarters')" :description="__('structure.branches.headquarters_help')"
            :checked="(bool) old('is_headquarters', $record->is_headquarters)" />
    </div>
</x-form.section>

<x-form.section :title="__('structure.branches.contact')" :description="__('structure.branches.contact_help')">
    <x-form.input name="email" type="email" :label="__('structure.fields.email')" :value="old('email', $record->email)" maxlength="150" dir="ltr" />
    <x-form.input name="phone" type="tel" :label="__('structure.fields.phone')" :value="old('phone', $record->phone)" maxlength="30" dir="ltr" />
    <x-form.input name="country_code" :label="__('structure.fields.country_code')" :value="old('country_code', $record->country_code)"
        minlength="2" maxlength="2" dir="ltr" class="uppercase" :help="__('structure.branches.country_help')" />
    <div class="hidden md:block" aria-hidden="true"></div>
    <x-form.textarea name="address_ar" :label="__('structure.fields.address_ar')" maxlength="500" rows="2" dir="rtl" lang="ar">{{ old('address_ar', $record->address_ar) }}</x-form.textarea>
    <x-form.textarea name="address_en" :label="__('structure.fields.address_en')" maxlength="500" rows="2" dir="ltr" lang="en">{{ old('address_en', $record->address_en) }}</x-form.textarea>
</x-form.section>

<x-form.section :title="__('structure.branches.management')" :description="__('structure.branches.management_help')">
    <x-form.select name="manager_position_id" :label="__('structure.fields.manager_position_id')">
        <option value="">{{ __('structure.common.not_set') }}</option>
        @foreach ($positions as $position)
            <option value="{{ $position->id }}" @selected((string) old('manager_position_id', $record->manager_position_id) === (string) $position->id)>{{ $position->name }} · {{ $position->department?->name }}</option>
        @endforeach
    </x-form.select>

    <x-form.select name="timezone" :label="__('structure.fields.timezone')" :help="__('structure.branches.timezone_help')" dir="ltr">
        <option value="">{{ __('structure.branches.timezone_default', ['timezone' => setting('localisation.timezone')]) }}</option>
        @foreach ($timezones as $timezone)
            <option value="{{ $timezone }}" @selected(old('timezone', $record->timezone) === $timezone)>{{ $timezone }}</option>
        @endforeach
    </x-form.select>

    @include('admin.structure._active-switch')
</x-form.section>
