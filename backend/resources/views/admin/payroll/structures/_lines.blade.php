{{--
    Structure lines editor. Variables: $components (SalaryComponent list), $lines (list of arrays).
    Server-rendered rows (with a few empty ones) work without JS; pages/payroll.js adds "Add component" / "Remove".
--}}
<div class="md:col-span-2 flex flex-col gap-3" data-repeater="lines">
    @error('lines')
        <x-alert tone="danger">{{ $message }}</x-alert>
    @enderror
    <p class="text-caption-md text-text-tertiary">{{ __('payroll.help.structure_lines') }}</p>
    <div class="flex flex-col gap-3" data-repeater-rows>
        @foreach ($lines as $index => $line)
            @include('admin.payroll.structures._line', ['index' => $index, 'line' => $line])
        @endforeach
    </div>
    <template data-repeater-template>
        @include('admin.payroll.structures._line', ['index' => '__INDEX__', 'line' => []])
    </template>
    <div>
        <x-button type="button" variant="secondary" size="sm" icon="plus" data-repeater-add hidden data-js-only>{{ __('payroll.structures.add_line') }}</x-button>
    </div>
</div>
