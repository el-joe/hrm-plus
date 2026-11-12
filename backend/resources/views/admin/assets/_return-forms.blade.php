{{--
    Return / acknowledgement / loss forms of an active handover (asset page, custody list modal-free).
    Variables: $assignment (active App\Models\AssetAssignment), $conditions.
--}}
@php($suffix = $assignment->id)
<div class="flex flex-col gap-5">
    <form method="POST" action="{{ route('admin.asset-assignments.return', $assignment) }}" class="flex flex-col gap-3" data-validate data-region="return-form">
        @csrf
        <p class="text-label-md text-text-primary">{{ __('assets.show.return') }}</p>
        <p class="text-caption-md text-text-tertiary">{{ __('assets.show.return_help') }}</p>
        <x-form.input type="date" name="returned_on" :id="'returned_on-'.$suffix" required :label="__('assets.fields.returned_on')"
            :value="old('returned_on', now()->toDateString())" :min="$assignment->assigned_on->toDateString()" :max="now()->toDateString()" />
        <x-form.select name="condition_on_return" :id="'condition_on_return-'.$suffix" required :label="__('assets.fields.condition_on_return')">
            @foreach ($conditions as $condition)
                <option value="{{ $condition->value }}" @selected(old('condition_on_return', $assignment->condition_on_issue->value) === $condition->value)>{{ $condition->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.textarea name="return_notes" :id="'return_notes-'.$suffix" rows="2" maxlength="2000" :label="__('assets.fields.return_notes')">{{ old('return_notes') }}</x-form.textarea>
        <x-button icon="check">{{ __('assets.show.return') }}</x-button>
    </form>

    @unless ($assignment->isAcknowledged())
        <form method="POST" action="{{ route('admin.asset-assignments.acknowledge', $assignment) }}" class="flex flex-col gap-2 border-t border-border-subtle pt-4">
            @csrf
            <x-form.input name="note" :id="'ack-note-'.$suffix" maxlength="500" :label="__('assets.fields.note')" />
            <x-button variant="secondary" icon="file-text">{{ __('assets.show.record_acknowledgement') }}</x-button>
        </form>
    @endunless

    <form method="POST" action="{{ route('admin.asset-assignments.lost', $assignment) }}" class="flex flex-col gap-2 border-t border-border-subtle pt-4" data-validate
        data-confirm="{{ __('assets.show.report_lost_help') }}" data-confirm-tone="danger" data-confirm-title="{{ __('assets.show.report_lost') }}">
        @csrf
        <p class="text-caption-md text-text-tertiary">{{ __('assets.show.report_lost_help') }}</p>
        <x-form.input type="date" name="reported_on" :id="'reported_on-'.$suffix" required :label="__('assets.fields.reported_on')" :value="now()->toDateString()" :max="now()->toDateString()" />
        <x-form.input name="note" :id="'lost-note-'.$suffix" required maxlength="2000" :label="__('assets.fields.note')" />
        <x-button variant="danger" icon="alert-triangle">{{ __('assets.show.report_lost') }}</x-button>
    </form>
</div>
