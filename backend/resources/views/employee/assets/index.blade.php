@extends('layouts.employee')

@section('title', __('assets.portal.title'))

@section('content')
    @php($pending = $current->reject->isAcknowledged()->count())
    @if ($pending > 0)
        <x-alert tone="warning">{{ trans_choice('assets.portal.pending_count', $pending, ['count' => $pending]) }}</x-alert>
    @endif

    <x-card :title="__('assets.portal.current')" :subtitle="__('assets.portal.contact_hr')" padding="none" data-region="my-assets">
        <ul class="divide-y divide-border-subtle">
            @forelse ($current as $row)
                <li class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-start md:justify-between" data-assignment="{{ $row->id }}">
                    <div class="min-w-0">
                        <p class="text-label-md text-text-primary">{{ $row->asset->name }}</p>
                        <p class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $row->asset->asset_tag }}</span> · {{ $row->asset->category?->name }}
                            @if ($row->asset->serial_number) · <span dir="ltr">{{ $row->asset->serial_number }}</span>@endif</p>
                        <p class="mt-1 text-body-sm text-text-secondary">
                            {{ __('assets.fields.assigned_on') }}: <x-date :value="$row->assigned_on" />
                            · {{ __('assets.fields.condition_on_issue') }}: {{ $row->condition_on_issue->label() }}
                            @if ($row->expected_return_on) · {{ __('assets.portal.return_by', ['date' => \App\Support\Formatter::date($row->expected_return_on)]) }}@endif
                        </p>
                        @if ($row->handover_notes)
                            <p class="mt-1 text-body-sm text-text-secondary">{{ $row->handover_notes }}</p>
                        @endif
                    </div>
                    <div class="shrink-0 md:w-72">
                        @if ($row->isAcknowledged())
                            <x-badge tone="success">{{ __('assets.assignments.acknowledged_on', ['date' => \App\Support\Formatter::date($row->acknowledged_at)]) }}</x-badge>
                        @else
                            <form method="POST" action="{{ route('portal.assets.acknowledge', $row->id) }}" class="flex flex-col gap-2" data-validate>
                                @csrf
                                <p class="text-caption-md text-text-tertiary">{{ __('assets.portal.acknowledge_help') }}</p>
                                <x-form.input name="note" :id="'note-'.$row->id" maxlength="500" :label="__('assets.portal.acknowledge_note')" />
                                <x-button icon="check">{{ __('assets.portal.acknowledge') }}</x-button>
                            </form>
                        @endif
                    </div>
                </li>
            @empty
                <li class="px-5 py-6"><x-empty-state icon="laptop" :title="__('assets.portal.none_current')" /></li>
            @endforelse
        </ul>
    </x-card>

    <x-card :title="__('assets.portal.past')" padding="none" data-region="my-returned-assets">
        <ul class="divide-y divide-border-subtle">
            @forelse ($past as $row)
                <li class="flex flex-wrap items-center justify-between gap-2 px-5 py-3">
                    <div class="min-w-0">
                        <p class="text-label-md text-text-primary">{{ $row->asset->name }} <span class="text-caption-md text-text-tertiary" dir="ltr">{{ $row->asset->asset_tag }}</span></p>
                        <p class="text-caption-md text-text-tertiary"><span dir="ltr">{{ \App\Support\Formatter::date($row->assigned_on) }} → {{ \App\Support\Formatter::date($row->returned_on) ?: '—' }}</span>
                            @if ($row->condition_on_return) · {{ $row->condition_on_return->label() }}@endif</p>
                    </div>
                    <x-badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-badge>
                </li>
            @empty
                <li class="px-5 py-6 text-body-sm text-text-tertiary">{{ __('assets.portal.none_past') }}</li>
            @endforelse
        </ul>
    </x-card>
@endsection
