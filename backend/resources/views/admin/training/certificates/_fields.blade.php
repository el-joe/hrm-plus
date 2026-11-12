{{-- Certificate fields shared by the admin form and the portal upload. Variables: $certificate (optional), $types, $fileRequired. --}}
<x-form.select name="type" :label="__('training.fields.type')" required>
    @foreach ($types as $type)
        <option value="{{ $type->value }}" @selected(old('type', isset($certificate) ? $certificate->type?->value : 'certificate') === $type->value)>{{ $type->label() }}</option>
    @endforeach
</x-form.select>
<x-form.input name="name" :label="__('training.fields.certificate_name')" required maxlength="200" :value="old('name', $certificate->name ?? null)" />
<x-form.input name="issuer" :label="__('training.fields.issuer')" maxlength="200" :value="old('issuer', $certificate->issuer ?? null)" />
<x-form.input name="certificate_number" dir="ltr" :label="__('training.fields.certificate_number')" maxlength="100" :value="old('certificate_number', $certificate->certificate_number ?? null)" />
<x-form.input type="date" name="issued_on" :label="__('training.fields.issued_on')" :max="now()->toDateString()" :value="old('issued_on', isset($certificate) ? $certificate->issued_on?->toDateString() : null)" />
<x-form.input type="date" name="expires_on" :label="__('training.fields.expires_on')" :value="old('expires_on', isset($certificate) ? $certificate->expires_on?->toDateString() : null)" :help="__('training.certificates.expiry_help')" />
<div class="md:col-span-2">
    <x-form.file-input name="file" :label="__('training.fields.file')" :required="$fileRequired" :help="__('training.certificates.file_help', ['types' => implode(', ', (array) setting('documents.allowed_extensions')), 'size' => (int) round(setting('documents.max_upload_size_kb') / 1024)])" />
</div>
