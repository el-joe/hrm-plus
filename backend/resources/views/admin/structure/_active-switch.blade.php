{{-- is_active switch; the hidden input sends 0 when unchecked. Variables: $record. --}}
<div class="md:col-span-2">
    <input type="hidden" name="is_active" value="0">
    <x-form.switch name="is_active" :label="__('structure.common.active')" :description="__('structure.common.status_help')"
        :checked="(bool) old('is_active', $record->is_active ?? true)" />
</div>
