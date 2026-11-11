{{-- Code + Arabic / English name inputs. Variables: $record; optional $withCode (default true). --}}
@if ($withCode ?? true)
    <x-form.input name="code" :label="__('structure.fields.code')" :value="old('code', $record->code)" required maxlength="20"
        dir="ltr" class="uppercase" pattern="[A-Za-z0-9][A-Za-z0-9._\-]*" :help="__('structure.common.code_help')" />
    <div class="hidden md:block" aria-hidden="true"></div>
@endif
<x-form.input name="name_ar" :label="__('structure.fields.name_ar')" :value="old('name_ar', $record->name_ar)" required maxlength="150" dir="rtl" lang="ar" />
<x-form.input name="name_en" :label="__('structure.fields.name_en')" :value="old('name_en', $record->name_en)" required maxlength="150" dir="ltr" lang="en" />
