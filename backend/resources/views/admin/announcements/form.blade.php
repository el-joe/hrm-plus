@extends('layouts.admin', ['breadcrumbs' => [['label' => __('announcements.title'), 'url' => route('admin.announcements.index')]]])

@section('title', $announcement->exists ? __('announcements.edit') : __('announcements.create'))

@section('content')
    @php
        $selected = $announcement->exists
            ? $announcement->targets->groupBy(fn ($target) => $target->target_type->value)->map(fn ($rows) => $rows->pluck('target_id')->map(fn ($id) => (string) $id)->all())->all()
            : [];
        $oldTargets = old('targets', $selected);
        $dateValue = fn ($value) => $value ? $value->format('Y-m-d\TH:i') : null;
    @endphp

    @if ($restricted)
        <x-alert tone="info">{{ __('announcements.form.restricted_note') }}</x-alert>
    @endif

    <form method="POST" enctype="multipart/form-data" class="flex flex-col gap-4 lg:gap-6" data-validate data-announcement-form
        action="{{ $announcement->exists ? route('admin.announcements.update', $announcement) : route('admin.announcements.store') }}">
        @csrf
        @if ($announcement->exists)
            @method('PUT')
        @endif

        <x-form.section :title="__('announcements.form.content_title')" :description="__('announcements.form.content_help')">
            <x-form.select name="type" :label="__('announcements.fields.type')" required>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $announcement->type?->value ?? 'general') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-form.select>
            <div class="hidden md:block"></div>

            @foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction)
                <div class="flex flex-col gap-3 md:col-span-2" data-region="content-{{ $locale }}">
                    <h3 class="text-label-lg text-text-primary">{{ __('announcements.form.'.($locale === 'ar' ? 'arabic' : 'english')) }}</h3>
                    <x-form.input :name="'title_'.$locale" :label="__('announcements.fields.title_'.$locale)" :value="old('title_'.$locale, $announcement->{'title_'.$locale})" maxlength="200" dir="{{ $direction }}" />
                    <div class="flex flex-col gap-1.5">
                        <div class="flex flex-wrap gap-1" role="toolbar" aria-label="{{ __('announcements.form.toolbar') }}" hidden data-js-only data-format-toolbar="body_{{ $locale }}">
                            <x-button type="button" variant="ghost" size="sm" data-format="strong">{{ __('announcements.form.bold') }}</x-button>
                            <x-button type="button" variant="ghost" size="sm" data-format="em">{{ __('announcements.form.italic') }}</x-button>
                            <x-button type="button" variant="ghost" size="sm" data-format="ul">{{ __('announcements.form.list') }}</x-button>
                            <x-button type="button" variant="ghost" size="sm" data-format="a" data-link-prompt="{{ __('announcements.form.link_prompt') }}">{{ __('announcements.form.link') }}</x-button>
                        </div>
                        <x-form.textarea :name="'body_'.$locale" :label="__('announcements.fields.body_'.$locale)" rows="8" maxlength="50000" dir="{{ $direction }}"
                            :help="__('announcements.form.body_help')">{{ old('body_'.$locale, $announcement->{'body_'.$locale}) }}</x-form.textarea>
                    </div>
                </div>
            @endforeach
        </x-form.section>

        <x-form.section :title="__('announcements.form.audience_title')" :description="__('announcements.form.audience_help')">
            <x-form.select name="audience" :label="__('announcements.fields.audience')" data-switch="audience" required>
                @foreach (['all', 'targeted'] as $audience)
                    @continue($restricted && $audience === 'all' && old('audience', $announcement->audience) !== 'all')
                    <option value="{{ $audience }}" @selected(old('audience', $announcement->audience) === $audience)>{{ __('announcements.audiences.'.$audience) }}</option>
                @endforeach
            </x-form.select>
            <div class="hidden md:block"></div>

            @foreach ($targetOptions as $type => $records)
                @continue($records->isEmpty())
                <fieldset class="flex min-w-0 flex-col gap-2" data-show-for="targeted" data-switch-group="audience" data-target-type="{{ $type }}">
                    <legend class="mb-1.5 text-label-md text-text-primary">{{ App\Enums\AnnouncementTargetType::from($type)->label() }}</legend>
                    <div class="flex max-h-56 flex-col gap-2 overflow-y-auto rounded-md border border-border-subtle p-3">
                        @foreach ($records as $record)
                            <x-form.checkbox name="targets[{{ $type }}][]" :value="$record->id" id="target-{{ $type }}-{{ $record->id }}"
                                :label="$record->name.($type === 'department' && $record->branch ? ' · '.$record->branch->name : '')"
                                :checked="in_array((string) $record->id, array_map('strval', (array) ($oldTargets[$type] ?? [])), true)" />
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            @foreach (['targets', 'targets.*'] as $key)
                @error($key) <p class="text-caption-md text-feedback-danger-text md:col-span-2" data-field-error>{{ $message }}</p> @enderror
            @endforeach
        </x-form.section>

        <x-form.section :title="__('announcements.form.publishing_title')" :description="__('announcements.form.publishing_help')">
            <x-form.input type="datetime-local" name="publish_at" :label="__('announcements.fields.publish_at')" :value="old('publish_at', $dateValue($announcement->publish_at))" />
            <x-form.input type="datetime-local" name="expires_at" :label="__('announcements.fields.expires_at')" :value="old('expires_at', $dateValue($announcement->expires_at))" />
            <x-form.switch name="is_pinned" id="is_pinned" :label="__('announcements.fields.is_pinned')" :checked="(bool) old('is_pinned', $announcement->is_pinned)" />
            <x-form.switch name="requires_acknowledgement" id="requires_acknowledgement" :label="__('announcements.fields.requires_acknowledgement')" :checked="(bool) old('requires_acknowledgement', $announcement->requires_acknowledgement)" />
            <x-form.switch name="notify_push" id="notify_push" :label="__('announcements.fields.notify_push')" :checked="(bool) old('notify_push', $announcement->notify_push)" />
            <x-form.switch name="notify_email" id="notify_email" :label="__('announcements.fields.notify_email')" :checked="(bool) old('notify_email', $announcement->notify_email)" />
            <div class="md:col-span-2">
                <x-form.file-input name="attachments[]" :label="__('announcements.fields.attachments')" error="attachments" :error-keys="['attachments.*']" multiple
                    :help="__('announcements.form.attachments_help', ['types' => implode(', ', (array) setting('documents.allowed_extensions')), 'kb' => setting('announcements.attachment_max_kb')])" />
            </div>
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="$announcement->exists ? route('admin.announcements.show', $announcement) : route('admin.announcements.index')">{{ __('app.actions.cancel') }}</x-button>
            @if (! $announcement->exists || $announcement->isDraft())
                <x-button variant="secondary" name="intent" value="draft">{{ __('announcements.form.save_draft') }}</x-button>
                <x-button icon="send" name="intent" value="publish">{{ __('announcements.form.publish') }}</x-button>
            @else
                <x-button icon="check" name="intent" value="draft">{{ __('announcements.form.save') }}</x-button>
            @endif
        </x-form.actions>
    </form>

    @if ($announcement->exists && $announcement->attachments->isNotEmpty())
        <x-card :title="__('announcements.form.current_attachments')">
            <ul class="flex flex-col divide-y divide-border-subtle">
                @foreach ($announcement->attachments as $attachment)
                    <li class="flex items-center justify-between gap-3 py-2">
                        <a class="min-w-0 truncate text-body-sm text-text-link hover:underline" href="{{ route('admin.announcements.attachments.download', [$announcement, $attachment]) }}">{{ $attachment->file_name }}</a>
                        <form method="POST" action="{{ route('admin.announcements.attachments.destroy', [$announcement, $attachment]) }}" data-confirm="{{ __('announcements.show.remove_attachment_confirm') }}" data-confirm-tone="danger">
                            @csrf
                            @method('DELETE')
                            <x-button variant="ghost" size="sm" icon="trash">{{ __('announcements.show.remove_attachment') }}</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif
@endsection
