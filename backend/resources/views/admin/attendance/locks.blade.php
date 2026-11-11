@extends('layouts.admin', ['subtitle' => __('attendance.locks.subtitle')])

@section('title', __('attendance.locks.title'))

@section('content')
    <div class="grid gap-4 lg:grid-cols-2 lg:gap-6">
        <x-card :title="__('attendance.locks.lock')" :subtitle="__('attendance.locks.lock_help')">
            <form method="POST" action="{{ route('admin.attendance.locks.store') }}" class="grid gap-4 sm:grid-cols-2" data-validate
                data-confirm="{{ __('attendance.locks.confirm_lock') }}" data-confirm-tone="warning" data-confirm-label="{{ __('attendance.locks.lock') }}">
                @csrf
                <x-form.input name="start_date" type="date" :label="__('attendance.fields.from')" :value="old('start_date')" required />
                <x-form.input name="end_date" type="date" :label="__('attendance.fields.to')" :value="old('end_date')" required />
                <x-form.select name="branch_id" :label="__('attendance.fields.branch')">
                    <option value="">{{ __('attendance.locks.all_branches') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->code }} · {{ $branch->name }}</option>
                    @endforeach
                </x-form.select>
                <x-form.input name="note" :label="__('attendance.fields.note')" :value="old('note')" maxlength="500" />
                <div class="sm:col-span-2"><x-button icon="lock">{{ __('attendance.locks.lock') }}</x-button></div>
            </form>
        </x-card>

        <x-card :title="__('attendance.locks.reprocess')" :subtitle="__('attendance.locks.reprocess_help')">
            <form method="POST" action="{{ route('admin.attendance.reprocess') }}" class="grid gap-4 sm:grid-cols-2" data-validate>
                @csrf
                <x-form.input name="from" type="date" :label="__('attendance.fields.from')" :value="old('from')" required />
                <x-form.input name="to" type="date" :label="__('attendance.fields.to')" :value="old('to')" required />
                <x-form.select name="branch_id" :id="'reprocess-branch'" :label="__('attendance.fields.branch')">
                    <option value="">{{ __('attendance.locks.all_branches') }}</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->code }} · {{ $branch->name }}</option>
                    @endforeach
                </x-form.select>
                <div class="sm:col-span-2"><x-button variant="secondary" icon="history">{{ __('attendance.locks.reprocess') }}</x-button></div>
            </form>
        </x-card>
    </div>

    <x-table :caption="__('attendance.locks.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('attendance.locks.period') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.branch') }}</x-table.th>
                <x-table.th>{{ __('attendance.locks.days') }}</x-table.th>
                <x-table.th>{{ __('attendance.locks.locked_by') }}</x-table.th>
                <x-table.th>{{ __('attendance.fields.status') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($locks as $lock)
            <x-table.row>
                <x-table.td :label="__('attendance.locks.period')" class="tabular-nums"><span dir="ltr">{{ $lock->start_date->format('Y-m-d') }} – {{ $lock->end_date->format('Y-m-d') }}</span>
                    @if ($lock->note)<span class="block text-caption-md text-text-tertiary">{{ $lock->note }}</span>@endif
                </x-table.td>
                <x-table.td :label="__('attendance.fields.branch')">{{ $lock->branch ? $lock->branch->code.' · '.$lock->branch->name : __('attendance.locks.all_branches') }}</x-table.td>
                <x-table.td :label="__('attendance.locks.days')" class="tabular-nums">{{ $lock->days_count }}</x-table.td>
                <x-table.td :label="__('attendance.locks.locked_by')">{{ $lock->locker?->name }} <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $lock->locked_at->format('Y-m-d H:i') }}</span></x-table.td>
                <x-table.td :label="__('attendance.fields.status')">
                    <x-status-badge :state="$lock->isActive() ? 'finalized' : 'draft'">{{ $lock->isActive() ? __('attendance.locks.active') : __('attendance.locks.unlocked') }}</x-status-badge>
                </x-table.td>
                <x-table.td>
                    @if ($lock->isActive())
                        <form method="POST" action="{{ route('admin.attendance.locks.unlock', $lock) }}" class="flex justify-end"
                            data-confirm="{{ __('attendance.locks.confirm_unlock') }}" data-confirm-tone="warning" data-confirm-label="{{ __('attendance.locks.unlock') }}">
                            @csrf
                            <x-button variant="ghost" size="sm" icon="lock">{{ __('attendance.locks.unlock') }}</x-button>
                        </form>
                    @endif
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="6" icon="lock" :title="__('attendance.locks.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$locks" />
        </x-slot:footer>
    </x-table>
@endsection
