@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('app.settings.title'), 'url' => route('admin.settings.index')],
], 'subtitle' => __('app.settings.subtitle')])

@section('title', __('app.settings.title').' — '.__('settings.groups.'.$group))

@section('content')
    @php
        $canManage = auth()->user()->can('settings.manage');
    @endphp

    <x-tabs data-region="tabs" :label="__('app.settings.title')" :items="collect($groups)->map(fn (string $name): array => [
        'label' => __('settings.groups.'.$name),
        'url' => route('admin.settings.edit', $name),
        'active' => $name === $group,
    ])->all()" />

    @unless ($canManage)
        <x-alert tone="info" data-notice="read-only">{{ __('app.settings.read_only') }}</x-alert>
    @endunless

    <form method="POST" action="{{ route('admin.settings.update', $group) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @method('PUT')

        <fieldset @disabled(! $canManage) class="min-w-0">
            <x-form.section :title="__('settings.groups.'.$group)" :description="__('app.settings.group_help')">
                @foreach ($definitions as $name => $definition)
                    @php
                        $value = $definition['type'] === 'secret' ? null : old($name, $values[$name]);
                        $label = __('settings.keys.'.$group.'.'.$name);
                        $options = $definition['options'] ?? null;
                        $optionLabel = fn (string $option): string => Lang::has('settings.options.'.$option) ? __('settings.options.'.$option) : $option;
                    @endphp
                    <div data-setting="{{ $group }}.{{ $name }}" data-type="{{ $definition['type'] }}" @class(['md:col-span-2' => $definition['type'] === 'list' && $options])>
                        @if ($definition['type'] === 'secret')
                            {{-- Credentials are never echoed back: an empty field keeps the stored value. --}}
                            <x-form.input :name="$name" id="setting-{{ $name }}" :label="$label" type="password" autocomplete="new-password"
                                :help="($secrets[$name] ?? false) ? __('notifications.settings.secret_set').' '.__('notifications.settings.secret_keep') : __('notifications.settings.secret_not_set')" />
                            @if ($secrets[$name] ?? false)
                                <div class="mt-2"><x-form.checkbox name="{{ $name }}_clear" id="setting-{{ $name }}-clear" :label="__('notifications.settings.secret_clear')" /></div>
                            @endif
                        @elseif ($definition['type'] === 'boolean')
                            <div class="flex h-full items-center">
                                <x-form.switch :name="$name" id="setting-{{ $name }}" :label="$label" :checked="(bool) $value" />
                            </div>
                            @error($name) <p class="mt-1 text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
                        @elseif ($definition['type'] === 'list' && $options)
                            <fieldset class="flex flex-col gap-2">
                                <legend class="mb-1.5 text-label-md text-text-primary">{{ $label }}</legend>
                                <div class="flex flex-wrap gap-x-5 gap-y-2">
                                    @foreach ($options as $option)
                                        <x-form.checkbox name="{{ $name }}[]" :value="$option" id="setting-{{ $name }}-{{ $option }}"
                                            :label="$optionLabel($option)" :checked="in_array($option, (array) $value, true)" />
                                    @endforeach
                                </div>
                                @foreach ([$name, $name.'.*'] as $key)
                                    @error($key) <p class="text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
                                @endforeach
                            </fieldset>
                        @elseif ($options)
                            <x-form.select :name="$name" id="setting-{{ $name }}" :label="$label" :error-keys="[$name.'.*']">
                                @foreach ($options as $option)
                                    <option value="{{ $option }}" @selected($value === $option)>{{ $optionLabel($option) }}</option>
                                @endforeach
                            </x-form.select>
                        @else
                            <x-form.input :name="$name" id="setting-{{ $name }}" :label="$label" :error-keys="[$name.'.*']"
                                :type="$definition['type'] === 'integer' ? 'number' : 'text'"
                                :inputmode="$definition['type'] === 'decimal' ? 'decimal' : null"
                                :value="is_array($value) ? implode(', ', $value) : $value" />
                        @endif
                    </div>
                @endforeach
            </x-form.section>
        </fieldset>

        @if ($canManage)
            <x-form.actions>
                <x-button>{{ __('app.actions.save') }}</x-button>
            </x-form.actions>
        @endif
    </form>

    {{-- Group-specific tools (test e-mail, FCM key upload, test push). --}}
    @includeIf('admin.settings.extras.'.$group, ['canManage' => $canManage, 'extras' => $extras ?? []])
@endsection
