{{--
    Component showcase (Super Admin only, route admin.ui-kit). Every block here
    is a real component from resources/views/components; copy the markup.
    Values on this page are labelled sample data.
--}}
@extends('layouts.admin', ['subtitle' => __('app.ui_kit.subtitle')])

@section('title', __('app.ui_kit.title'))

@section('page-actions')
    <x-button variant="secondary" type="button" icon="layout-grid" data-modal-open="ui-kit-modal">{{ __('app.ui_kit.open_modal') }}</x-button>
@endsection

@section('content')
    @php
        $tones = ['neutral', 'primary', 'success', 'warning', 'danger', 'info'];
        $states = ['present', 'absent', 'late', 'on-leave', 'pending', 'approved', 'rejected', 'finalized', 'draft'];
        $colorTokens = [
            'surface-page', 'surface-default', 'surface-subtle', 'surface-muted', 'surface-raised', 'surface-inverse', 'surface-brand', 'surface-brand-strong',
            'action-primary-default', 'action-primary-hover', 'action-primary-subtle', 'action-danger-default', 'accent-default', 'accent-subtle',
            'feedback-success-solid', 'feedback-warning-solid', 'feedback-danger-solid', 'feedback-info-solid',
            'chart-1', 'chart-2', 'chart-3', 'chart-4', 'chart-5', 'chart-6',
        ];
        $swatchClasses = [
            'surface-page' => 'bg-surface-page', 'surface-default' => 'bg-surface-default', 'surface-subtle' => 'bg-surface-subtle',
            'surface-muted' => 'bg-surface-muted', 'surface-raised' => 'bg-surface-raised', 'surface-inverse' => 'bg-surface-inverse',
            'surface-brand' => 'bg-surface-brand', 'surface-brand-strong' => 'bg-surface-brand-strong',
            'action-primary-default' => 'bg-action-primary-default', 'action-primary-hover' => 'bg-action-primary-hover',
            'action-primary-subtle' => 'bg-action-primary-subtle', 'action-danger-default' => 'bg-action-danger-default',
            'accent-default' => 'bg-accent-default', 'accent-subtle' => 'bg-accent-subtle',
            'feedback-success-solid' => 'bg-feedback-success-solid', 'feedback-warning-solid' => 'bg-feedback-warning-solid',
            'feedback-danger-solid' => 'bg-feedback-danger-solid', 'feedback-info-solid' => 'bg-feedback-info-solid',
            'chart-1' => 'bg-chart-1', 'chart-2' => 'bg-chart-2', 'chart-3' => 'bg-chart-3',
            'chart-4' => 'bg-chart-4', 'chart-5' => 'bg-chart-5', 'chart-6' => 'bg-chart-6',
        ];
        $typeStyles = [
            'display-md' => 'text-display-md', 'h1' => 'text-h1', 'h2' => 'text-h2', 'h3' => 'text-h3', 'h4' => 'text-h4', 'h5' => 'text-h5',
            'body-lg' => 'text-body-lg', 'body-md' => 'text-body-md', 'body-sm' => 'text-body-sm',
            'label-md' => 'text-label-md', 'caption-md' => 'text-caption-md', 'numeric-xl' => 'text-numeric-xl',
        ];
    @endphp

    <x-tabs :label="__('app.ui_kit.title')" :items="[
        ['label' => __('app.ui_kit.foundations'), 'id' => 'kit-foundations'],
        ['label' => __('app.ui_kit.buttons'), 'id' => 'kit-buttons'],
        ['label' => __('app.ui_kit.forms'), 'id' => 'kit-forms'],
        ['label' => __('app.ui_kit.data'), 'id' => 'kit-data'],
        ['label' => __('app.ui_kit.feedback'), 'id' => 'kit-feedback'],
    ]" />

    {{-- Foundations --------------------------------------------------- --}}
    <div id="kit-foundations" data-tab-panel class="flex flex-col gap-6">
        <x-card :title="__('app.ui_kit.colors')">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-6">
                @foreach ($colorTokens as $token)
                    <div class="flex flex-col gap-1.5">
                        <span class="h-12 rounded-md border border-border-subtle {{ $swatchClasses[$token] }}"></span>
                        <code class="text-caption-sm text-text-secondary" dir="ltr">{{ $token }}</code>
                    </div>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('app.ui_kit.typography')">
            <div class="flex flex-col gap-3">
                @foreach ($typeStyles as $name => $class)
                    <div class="flex flex-col gap-1 border-b border-border-subtle pb-3 last:border-0 sm:flex-row sm:items-baseline sm:gap-6">
                        <code class="w-28 shrink-0 text-caption-md text-text-tertiary" dir="ltr">{{ $name }}</code>
                        <p class="{{ $class }} truncate">{{ $name === 'numeric-xl' ? '1,234' : __('app.ui_kit.sample_text') }}</p>
                    </div>
                @endforeach
            </div>
        </x-card>
    </div>

    {{-- Buttons and badges -------------------------------------------- --}}
    <div id="kit-buttons" data-tab-panel class="flex flex-col gap-6">
        <x-card :title="__('app.ui_kit.buttons')">
            <div class="flex flex-col gap-4">
                @foreach (['lg', 'md', 'sm'] as $size)
                    <div class="flex flex-wrap items-center gap-3">
                        @foreach (['primary', 'secondary', 'ghost', 'danger'] as $variant)
                            <x-button type="button" :variant="$variant" :size="$size" :icon="$variant === 'primary' ? 'plus' : null">{{ __('app.ui_kit.variants.'.$variant) }}</x-button>
                        @endforeach
                        <x-button type="button" :size="$size" disabled>{{ __('app.ui_kit.variants.disabled') }}</x-button>
                        <x-button type="button" :size="$size" loading icon="check">{{ __('app.ui_kit.variants.loading') }}</x-button>
                    </div>
                @endforeach
                <div class="flex flex-wrap items-center gap-2">
                    <x-icon-button icon="pencil" :label="__('app.actions.edit')" />
                    <x-icon-button icon="eye" variant="secondary" :label="__('app.actions.view')" />
                    <x-icon-button icon="plus" variant="primary" :label="__('app.actions.create')" />
                    <x-icon-button icon="trash" variant="danger" :label="__('app.actions.delete')" />
                </div>
            </div>
        </x-card>

        <x-card :title="__('app.ui_kit.badges')">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap gap-2">
                    @foreach ($tones as $tone)
                        <x-badge :tone="$tone" :dot="$loop->odd">{{ __('app.ui_kit.tones.'.$tone) }}</x-badge>
                    @endforeach
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($states as $state)
                        <x-status-badge :state="$state">{{ __('app.ui_kit.states.'.$state) }}</x-status-badge>
                    @endforeach
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    @foreach (['sm', 'md', 'lg', 'xl'] as $size)
                        <x-avatar :name="auth()->user()->name" :size="$size" />
                    @endforeach
                </div>
            </div>
        </x-card>

        <x-card :title="__('app.ui_kit.navigation')">
            <div class="flex flex-col gap-5">
                <x-breadcrumbs :items="[
                    ['label' => __('app.nav.dashboard'), 'url' => route('admin.dashboard')],
                    ['label' => __('app.ui_kit.title'), 'url' => route('admin.ui-kit')],
                    ['label' => __('app.ui_kit.navigation'), 'url' => null],
                ]" />

                <div>
                    <x-tabs :label="__('app.ui_kit.navigation')" :items="[
                        ['label' => __('app.ui_kit.tab', ['n' => 1]), 'id' => 'demo-tab-1'],
                        ['label' => __('app.ui_kit.tab', ['n' => 2]), 'id' => 'demo-tab-2'],
                        ['label' => __('app.ui_kit.tab', ['n' => 3]), 'id' => 'demo-tab-3'],
                    ]" />
                    @foreach ([1, 2, 3] as $n)
                        <p id="demo-tab-{{ $n }}" data-tab-panel class="pt-4 text-body-md text-text-secondary">{{ __('app.ui_kit.tab_panel', ['n' => $n]) }}</p>
                    @endforeach
                </div>

                <x-dropdown align="start" :label="__('app.common.actions')">
                    <x-slot:trigger>
                        <span class="inline-flex h-10 items-center gap-2 rounded-md border border-border-default bg-surface-default px-4 text-label-md">
                            {{ __('app.common.actions') }} <x-icon name="chevron-down" size="sm" />
                        </span>
                    </x-slot:trigger>
                    <x-dropdown.item :href="route('admin.ui-kit')" icon="eye">{{ __('app.actions.view') }}</x-dropdown.item>
                    <x-dropdown.item :href="route('admin.ui-kit')" icon="pencil">{{ __('app.actions.edit') }}</x-dropdown.item>
                    <x-dropdown.divider />
                    <x-dropdown.item :href="route('admin.ui-kit')" icon="trash" tone="danger" data-confirm="{{ __('app.ui_kit.confirm_message') }}">{{ __('app.actions.delete') }}</x-dropdown.item>
                </x-dropdown>
            </div>
        </x-card>
    </div>

    {{-- Forms --------------------------------------------------------- --}}
    <div id="kit-forms" data-tab-panel class="flex flex-col gap-6">
        <form method="GET" action="{{ route('admin.ui-kit') }}" class="flex flex-col gap-6" data-validate>
            <x-form.section :title="__('app.ui_kit.forms')" :description="__('app.ui_kit.sample_help')">
                <x-form.input name="kit_name" :label="__('app.ui_kit.sample_name')" required maxlength="40" :help="__('app.ui_kit.sample_help')" />
                <x-form.input name="kit_email" type="email" :label="__('app.common.email')" icon="mail" required />
                <x-form.select name="kit_select" :label="__('app.common.status')">
                    @foreach ([1, 2, 3] as $n)
                        <option value="{{ $n }}">{{ __('app.ui_kit.sample_option', ['n' => $n]) }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="kit_date" type="date" :label="__('app.common.date')" />
                <x-form.input name="kit_search" type="search" icon="search" :label="__('app.common.search')" :placeholder="__('app.common.search')" />
                <x-form.input name="kit_disabled" :label="__('app.ui_kit.variants.disabled')" disabled :value="__('app.ui_kit.sample_name')" />
                <x-form.textarea name="kit_notes" :label="__('app.common.description')" class="md:col-span-2" />
                <x-form.file-input name="kit_file" :label="__('app.ui_kit.sample_name')" />
                <div class="flex flex-col gap-3">
                    <x-form.checkbox name="kit_check" :label="__('app.ui_kit.sample_option', ['n' => 1])" checked />
                    <x-form.radio name="kit_radio" value="a" :label="__('app.ui_kit.sample_option', ['n' => 2])" checked />
                    <x-form.radio name="kit_radio" value="b" :label="__('app.ui_kit.sample_option', ['n' => 3])" />
                    <x-form.switch name="kit_switch" :label="__('app.ui_kit.sample_option', ['n' => 4])" checked />
                </div>
                <x-form.field :label="__('app.ui_kit.sample_name')" for="kit-error" :message="__('app.ui_kit.sample_error')">
                    <input id="kit-error" aria-invalid="true" aria-describedby="kit-error-error"
                        class="h-10 w-full rounded-md border border-feedback-danger-solid bg-surface-default px-3 text-body-md shadow-xs focus:shadow-focus-danger focus:outline-none">
                </x-form.field>
            </x-form.section>

            <x-form.actions>
                <x-button variant="secondary" type="reset">{{ __('app.actions.reset') }}</x-button>
                <x-button>{{ __('app.actions.save') }}</x-button>
            </x-form.actions>
        </form>
    </div>

    {{-- Data ---------------------------------------------------------- --}}
    <div id="kit-data" data-tab-panel class="flex flex-col gap-6">
        <div class="grid grid-cols-2 gap-4 lg:gap-6 xl:grid-cols-4">
            <x-stat-card :label="__('app.ui_kit.sample_stat')" value="1,248" icon="users" trend="up" trend-value="4.2%" :hint="__('app.ui_kit.sample_hint')" />
            <x-stat-card :label="__('app.ui_kit.sample_stat')" value="64" icon="calendar" trend="neutral" trend-value="0%" :hint="__('app.ui_kit.sample_hint')" />
            <x-stat-card :label="__('app.ui_kit.sample_stat')" value="38" icon="clock" trend="down" trend-value="12%" :hint="__('app.ui_kit.sample_hint')" />
            <div class="flex flex-col gap-3 rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs" aria-label="{{ __('app.ui_kit.loading') }}">
                <x-skeleton class="h-4 w-24" />
                <x-skeleton class="h-10 w-32" />
                <x-skeleton class="h-3 w-40" />
            </div>
        </div>

        <x-filter-bar :action="route('admin.ui-kit')" :reset="route('admin.ui-kit')">
            <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :placeholder="__('app.common.search')"
                :value="request('search')" class="md:w-72" data-filter-debounce />
            <x-form.select name="status" :label="__('app.common.status')" hide-label class="md:w-44">
                <option value="">{{ __('app.common.all') }}</option>
                @foreach (['pending', 'approved', 'rejected'] as $state)
                    <option value="{{ $state }}" @selected(request('status') === $state)>{{ __('app.ui_kit.states.'.$state) }}</option>
                @endforeach
            </x-form.select>
        </x-filter-bar>

        <x-table :caption="__('app.ui_kit.data')">
            <x-slot:head>
                <tr>
                    <x-table.th class="w-10"><input type="checkbox" class="size-4 accent-action-primary-default" data-table-select-all aria-label="{{ __('app.common.all') }}"></x-table.th>
                    <x-table.th sort="name">{{ __('app.common.name') }}</x-table.th>
                    <x-table.th sort="date">{{ __('app.common.date') }}</x-table.th>
                    <x-table.th>{{ __('app.common.status') }}</x-table.th>
                    <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
                </tr>
            </x-slot:head>
            @foreach (['approved', 'pending', 'rejected'] as $index => $state)
                <x-table.row :selected="$index === 1">
                    <x-table.td><input type="checkbox" class="size-4 accent-action-primary-default" value="{{ $index }}" data-table-select @checked($index === 1) aria-label="{{ __('app.ui_kit.sample_row', ['n' => $index + 1]) }}"></x-table.td>
                    <x-table.td>
                        <div class="flex items-center gap-3">
                            <x-avatar :name="__('app.ui_kit.sample_row', ['n' => $index + 1])" />
                            <span class="text-label-md">{{ __('app.ui_kit.sample_row', ['n' => $index + 1]) }}</span>
                        </div>
                    </x-table.td>
                    <x-table.td :label="__('app.common.date')" class="text-text-secondary">{{ now()->subDays($index)->toDateString() }}</x-table.td>
                    <x-table.td :label="__('app.common.status')"><x-status-badge :state="$state">{{ __('app.ui_kit.states.'.$state) }}</x-status-badge></x-table.td>
                    <x-table.td>
                        <div class="flex justify-end gap-1">
                            <x-icon-button icon="eye" size="sm" :label="__('app.actions.view')" :href="route('admin.ui-kit')" />
                            <x-icon-button icon="pencil" size="sm" :label="__('app.actions.edit')" :href="route('admin.ui-kit')" />
                        </div>
                    </x-table.td>
                </x-table.row>
            @endforeach
            <x-table.empty :colspan="5" :title="__('app.ui_kit.empty_title')" :description="__('app.ui_kit.empty_text')" />
        </x-table>

        <x-card :title="__('app.ui_kit.data')">
            <x-detail-list>
                <x-detail-item :label="__('app.common.name')">{{ auth()->user()->name }}</x-detail-item>
                <x-detail-item :label="__('app.common.email')">{{ auth()->user()->email }}</x-detail-item>
            </x-detail-list>
        </x-card>
    </div>

    {{-- Feedback and overlays ----------------------------------------- --}}
    <div id="kit-feedback" data-tab-panel class="flex flex-col gap-6">
        <x-card :title="__('app.ui_kit.feedback')">
            <div class="flex flex-col gap-3">
                @foreach (['success', 'warning', 'danger', 'info'] as $tone)
                    <x-alert :tone="$tone" :title="__('app.ui_kit.tones.'.$tone)" dismissible>{{ __('app.ui_kit.sample_text') }}</x-alert>
                @endforeach
            </div>
        </x-card>

        <x-card :title="__('app.ui_kit.overlays')">
            <div class="flex flex-wrap gap-3">
                <x-button type="button" variant="secondary" data-modal-open="ui-kit-modal">{{ __('app.ui_kit.open_modal') }}</x-button>
                <form method="GET" action="{{ route('admin.ui-kit') }}" data-confirm="{{ __('app.ui_kit.confirm_message') }}" data-confirm-label="{{ __('app.actions.delete') }}">
                    <x-button variant="danger" icon="trash">{{ __('app.ui_kit.confirm_demo') }}</x-button>
                </form>
                @foreach (['success', 'warning', 'danger', 'info'] as $tone)
                    <x-button type="button" variant="ghost" data-toast-trigger="{{ $tone }}" data-js-only hidden
                        data-toast-message="{{ __('app.ui_kit.toast_message', ['tone' => __('app.ui_kit.tones.'.$tone)]) }}">
                        {{ __('app.ui_kit.toast_demo', ['tone' => __('app.ui_kit.tones.'.$tone)]) }}
                    </x-button>
                @endforeach
            </div>
        </x-card>

        <x-card padding="none">
            <x-empty-state :title="__('app.ui_kit.empty_title')" :description="__('app.ui_kit.empty_text')">
                <x-button variant="secondary" icon="plus" type="button" data-modal-open="ui-kit-modal">{{ __('app.ui_kit.open_modal') }}</x-button>
            </x-empty-state>
        </x-card>
    </div>

    <x-modal id="ui-kit-modal" :title="__('app.ui_kit.modal_title')">
        <div class="flex flex-col gap-5">
            <p class="text-body-md text-text-secondary">{{ __('app.ui_kit.modal_body') }}</p>
            <x-form.input name="modal_name" :label="__('app.ui_kit.sample_name')" />
        </div>
        <x-slot:footer>
            <x-button type="button" variant="secondary" data-modal-close>{{ __('app.actions.cancel') }}</x-button>
            <x-button type="button" data-modal-close>{{ __('app.actions.save') }}</x-button>
        </x-slot:footer>
    </x-modal>
@endsection
