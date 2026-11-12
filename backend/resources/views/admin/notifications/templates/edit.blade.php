@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('notifications.templates.title'), 'url' => route('admin.notification-templates.index')]],
    'subtitle' => __('notifications.modules.'.$definition['module']).' · '.__('notifications.templates.audiences.'.$definition['audience']),
])

@section('title', $label)

@section('page-actions')
    @if ($canManage)
        <form method="POST" action="{{ route('admin.notification-templates.reset', $event) }}"
            data-confirm="{{ __('notifications.templates.reset_all_confirm') }}" data-confirm-tone="warning">
            @csrf
            <x-button variant="secondary" icon="refresh">{{ __('notifications.templates.reset_all') }}</x-button>
        </form>
    @endif
@endsection

@section('content')
    @if ($definition['mandatory'] ?? false)
        <x-alert tone="warning">{{ __('notifications.preferences.mandatory_help') }}</x-alert>
    @endif
    @unless ($canManage)
        <x-alert tone="info">{{ __('notifications.templates.read_only') }}</x-alert>
    @endunless

    {{-- One text per channel and language. --}}
    <x-tabs :label="__('notifications.templates.title')" :items="collect($channels)->crossJoin($locales)->map(fn (array $pair): array => [
        'label' => __('notifications.channels.'.$pair[0]).' · '.__('app.locales.'.$pair[1]),
        'url' => route('admin.notification-templates.edit', ['event' => $event, 'channel' => $pair[0], 'locale' => $pair[1]]),
        'active' => $pair[0] === $channel && $pair[1] === $locale,
    ])->all()" />

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <form method="POST" action="{{ route('admin.notification-templates.update', $event) }}" class="flex min-w-0 flex-col gap-4 lg:col-span-2" data-template-editor>
            @csrf
            @method('PUT')
            <input type="hidden" name="channel" value="{{ $channel }}">
            <input type="hidden" name="locale" value="{{ $locale }}">

            <x-card :title="__('notifications.channels.'.$channel).' · '.__('app.locales.'.$locale)">
                <x-slot:actions>
                    <x-badge :tone="$stored?->is_customised ? 'primary' : 'neutral'">{{ $stored?->is_customised ? __('notifications.templates.customised') : __('notifications.templates.default') }}</x-badge>
                </x-slot:actions>
                <fieldset @disabled(! $canManage) class="flex min-w-0 flex-col gap-4" dir="{{ config('app.supported_locales.'.$locale) }}">
                    <x-form.input name="subject" id="template-subject" :label="__('notifications.templates.subject')" :help="__('notifications.templates.subject_help')"
                        :value="$subject" required maxlength="255" />
                    <x-form.textarea name="body" id="template-body" :label="__('notifications.templates.body')" :help="__('notifications.templates.body_help')"
                        rows="5" required maxlength="2000">{{ $body }}</x-form.textarea>
                </fieldset>
                @if ($stored?->editor)
                    <p class="mt-3 text-caption-md text-text-tertiary">{{ __('notifications.templates.last_edited', ['name' => $stored->editor->name, 'date' => $stored->updated_at->toDateTimeString()]) }}</p>
                @endif
                @if ($canManage)
                    <x-slot:footer>
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <x-button variant="ghost" name="intent" value="preview" icon="eye">{{ __('notifications.templates.refresh_preview') }}</x-button>
                            <x-button name="intent" value="save" icon="check">{{ __('app.actions.save') }}</x-button>
                        </div>
                    </x-slot:footer>
                @endif
            </x-card>

            <x-card :title="__('notifications.templates.preview')" :subtitle="__('notifications.templates.preview_help')" data-region="preview">
                <div class="rounded-lg border border-border-subtle bg-surface-muted p-4" dir="{{ config('app.supported_locales.'.$locale) }}">
                    <p class="text-label-lg text-text-primary">{{ $preview['subject'] }}</p>
                    <p class="mt-1 whitespace-pre-line text-body-md text-text-secondary">{{ $preview['body'] }}</p>
                </div>
            </x-card>
        </form>

        <div class="flex min-w-0 flex-col gap-4">
            <x-card :title="__('notifications.templates.placeholders')" :subtitle="__('notifications.templates.placeholders_help')" data-region="placeholders">
                <ul class="flex flex-col gap-2">
                    @foreach ($placeholders as $name)
                        @php($token = str_repeat('{', 2).$name.str_repeat('}', 2))
                        <li class="flex flex-wrap items-center justify-between gap-2">
                            <button type="button" data-placeholder="{{ $name }}"
                                class="rounded-sm border border-border-default bg-surface-default px-2 py-0.5 font-mono text-caption-md text-text-primary hover:border-action-primary-default" dir="ltr">{{ $token }}</button>
                            <span class="min-w-0 truncate text-caption-md text-text-tertiary">{{ $samples[$name] ?? '' }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <x-card :title="__('notifications.templates.default')">
                <div dir="{{ config('app.supported_locales.'.$locale) }}">
                    <p class="text-label-md text-text-primary">{{ $default['subject'] }}</p>
                    <p class="mt-1 whitespace-pre-line text-body-sm text-text-secondary">{{ $default['body'] }}</p>
                </div>
                @if ($canManage && $stored?->is_customised)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('admin.notification-templates.reset', $event) }}"
                            data-confirm="{{ __('notifications.templates.reset_confirm') }}" data-confirm-tone="warning">
                            @csrf
                            <input type="hidden" name="channel" value="{{ $channel }}">
                            <input type="hidden" name="locale" value="{{ $locale }}">
                            <x-button size="sm" variant="secondary" icon="refresh">{{ __('notifications.templates.reset') }}</x-button>
                        </form>
                    </x-slot:footer>
                @endif
            </x-card>
        </div>
    </div>
@endsection
