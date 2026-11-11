{{--
    Role fields and the permission matrix (modules x actions).
    Variables: $role, $modules (module => actions), $actions, $granted (permission names).
    Each checkbox is name="permissions[]" value="module.action".
    The per-row "all" checkboxes are a JS convenience (resources/js/ui/check-group.js)
    and stay hidden without JavaScript.
--}}
<x-form.section :title="__('app.roles.details')" data-section="details">
    <x-form.input name="name" :label="__('app.common.name')" :value="old('name', $role->name)" maxlength="100"
        :required="! $role->is_system" :disabled="$role->is_system"
        :help="$role->is_system ? __('app.roles.system_name_locked') : null" />

    <x-form.input name="description" :label="__('app.common.description')" :value="old('description', $role->description)" maxlength="255" />
</x-form.section>

<section class="rounded-xl border border-border-subtle bg-surface-default shadow-xs" data-section="permission-matrix">
    <header class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-6">
        <div>
            <h2 class="text-h5">{{ __('app.roles.matrix') }}</h2>
            <p class="mt-1 text-body-sm text-text-tertiary">{{ __('app.roles.matrix_help') }}</p>
        </div>
        <p class="text-label-md text-text-secondary" data-check-count="permissions" hidden data-js-only
            data-template="{{ __('app.roles.selected_count', ['count' => ':count']) }}"></p>
    </header>

    @error('permissions') <p class="px-6 pb-4 text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror
    @error('permissions.*') <p class="px-6 pb-4 text-caption-md text-feedback-danger-text" data-field-error>{{ $message }}</p> @enderror

    @php
        $checked = old('permissions', $granted);
    @endphp
    <div class="overflow-x-auto border-t border-border-default">
        <table data-region="permission-matrix" class="w-full border-collapse text-body-sm">
            <thead class="bg-surface-subtle">
                <tr>
                    <th scope="col" class="sticky start-0 z-10 h-11 bg-surface-subtle px-4 text-start text-label-sm text-text-secondary">{{ __('app.roles.module') }}</th>
                    <th scope="col" class="h-11 px-2 text-center text-label-sm text-text-secondary" hidden data-js-only>{{ __('app.common.all') }}</th>
                    @foreach ($actions as $action)
                        <th scope="col" class="h-11 px-2 text-center text-label-sm text-text-secondary">{{ __('permissions.actions.'.$action) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y divide-border-subtle">
                @foreach ($modules as $module => $moduleActions)
                    <tr data-module="{{ $module }}" class="hover:bg-surface-subtle">
                        <th scope="row" class="sticky start-0 z-10 h-11 whitespace-nowrap bg-surface-default px-4 text-start text-label-md text-text-primary">{{ __('permissions.modules.'.$module) }}</th>
                        <td class="px-2 text-center" hidden data-js-only>
                            <input type="checkbox" class="size-4 accent-action-primary-default" data-check-all="module-{{ $module }}"
                                aria-label="{{ __('app.roles.select_module', ['module' => __('permissions.modules.'.$module)]) }}">
                        </td>
                        @foreach ($actions as $action)
                            <td class="px-2 text-center">
                                @if (in_array($action, $moduleActions, true))
                                    <input type="checkbox" name="permissions[]" value="{{ $module }}.{{ $action }}" class="size-4 cursor-pointer accent-action-primary-default"
                                        data-check-group="module-{{ $module }}" data-check-counted="permissions"
                                        aria-label="{{ __('permissions.modules.'.$module) }} — {{ __('permissions.actions.'.$action) }}"
                                        @checked(in_array($module.'.'.$action, $checked, true))>
                                @else
                                    <span class="text-text-disabled" aria-hidden="true">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
