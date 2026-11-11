{{--
    Row actions of a structure list: view (optional), edit, activate / deactivate, delete.
    Variables: $record, $resource (route resource name, e.g. "job-titles"), $show (bool, default false).
--}}
<div class="flex items-center justify-end gap-1">
    @if ($show ?? false)
        @can('view', $record)
            <x-icon-button icon="eye" size="sm" :href="route('admin.'.$resource.'.show', $record)" :label="__('app.actions.view')" />
        @endcan
    @endif
    @can('update', $record)
        <x-icon-button icon="pencil" size="sm" :href="route('admin.'.$resource.'.edit', $record)" :label="__('app.actions.edit')" />
        <form method="POST" action="{{ route('admin.'.$resource.'.status', $record) }}"
            data-confirm="{{ __($record->is_active ? 'structure.common.confirm_deactivate' : 'structure.common.confirm_activate', ['name' => $record->name]) }}"
            data-confirm-title="{{ $record->is_active ? __('structure.common.deactivate') : __('structure.common.activate') }}"
            data-confirm-label="{{ $record->is_active ? __('structure.common.deactivate') : __('structure.common.activate') }}"
            data-confirm-tone="{{ $record->is_active ? 'warning' : 'info' }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="is_active" value="{{ $record->is_active ? 0 : 1 }}">
            <x-icon-button type="submit" :icon="$record->is_active ? 'power' : 'check-circle'" size="sm"
                :label="$record->is_active ? __('structure.common.deactivate') : __('structure.common.activate')" />
        </form>
    @endcan
    @can('delete', $record)
        <form method="POST" action="{{ route('admin.'.$resource.'.destroy', $record) }}"
            data-confirm="{{ __('structure.common.confirm_delete', ['name' => $record->name]) }}"
            data-confirm-title="{{ __('app.actions.delete') }}: {{ $record->name }}" data-confirm-label="{{ __('app.actions.delete') }}">
            @csrf
            @method('DELETE')
            <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
        </form>
    @endcan
</div>
