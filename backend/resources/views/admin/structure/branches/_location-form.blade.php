<x-form.section :title="__('structure.common.identity')" :description="__('structure.locations.details_help')">
    @include('admin.structure._identity-fields', ['withCode' => false])
    <div class="md:col-span-2">
        <x-form.input name="address" :label="__('structure.fields.address')" :value="old('address', $record->address)" maxlength="500" />
    </div>
</x-form.section>

<x-form.section :title="__('structure.locations.geofence')" :description="__('structure.locations.geofence_help')">
    <x-form.input name="latitude" type="number" step="0.0000001" min="-90" max="90" inputmode="decimal" dir="ltr"
        :label="__('structure.fields.latitude')" :value="old('latitude', $record->latitude)" required :help="__('structure.locations.coordinates_help')" />
    <x-form.input name="longitude" type="number" step="0.0000001" min="-180" max="180" inputmode="decimal" dir="ltr"
        :label="__('structure.fields.longitude')" :value="old('longitude', $record->longitude)" required />
    <x-form.input name="radius_meters" type="number" step="1" min="10" max="5000" dir="ltr"
        :label="__('structure.fields.radius_meters')" :value="old('radius_meters', $record->radius_meters)" required
        :help="__('structure.locations.radius_help', ['radius' => setting('attendance.geofence_default_radius_meters')])" />
    <div class="hidden md:block" aria-hidden="true"></div>
    @include('admin.structure._active-switch')
</x-form.section>
