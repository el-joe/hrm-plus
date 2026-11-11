@extends('layouts.admin', ['subtitle' => $canSeeAll ? __('recruitment.interviews.subtitle_all') : __('recruitment.interviews.subtitle_mine')])

@section('title', __('recruitment.interviews.title'))

@section('content')
    <x-filter-bar :action="route('admin.interviews.index')" :reset="route('admin.interviews.index')">
        @if ($canSeeAll)
            <x-form.select name="scope" id="filter-scope" :label="__('recruitment.interviews.scope')" hide-label class="md:w-48">
                <option value="all" @selected($filters['scope'] === 'all')>{{ __('recruitment.interviews.scope_all') }}</option>
                <option value="mine" @selected($filters['scope'] === 'mine')>{{ __('recruitment.interviews.scope_mine') }}</option>
            </x-form.select>
        @endif
        <x-form.select name="when" id="filter-when" :label="__('recruitment.interviews.when')" hide-label class="md:w-40">
            <option value="upcoming" @selected($filters['when'] === 'upcoming')>{{ __('recruitment.interviews.upcoming') }}</option>
            <option value="past" @selected($filters['when'] === 'past')>{{ __('recruitment.interviews.past') }}</option>
        </x-form.select>
        <x-form.select name="status" id="filter-status" :label="__('recruitment.fields.status')" hide-label class="md:w-40">
            <option value="">{{ __('recruitment.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach (App\Enums\InterviewStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('recruitment.interviews.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('recruitment.fields.scheduled_at') }}</x-table.th>
                <x-table.th class="min-w-56">{{ __('recruitment.fields.candidate') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.type') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.interviewers') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($interviews as $interview)
            <x-table.row :data-href="route('admin.interviews.show', $interview)">
                <x-table.td :label="__('recruitment.fields.scheduled_at')" class="whitespace-nowrap tabular-nums">
                    <span dir="ltr">{{ $interview->localStart()->format('Y-m-d H:i') }}</span>
                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $interview->timezone }}</span>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.candidate')">
                    <a href="{{ route('admin.interviews.show', $interview) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $interview->application->candidate->name }}</a>
                    <span class="text-caption-md text-text-tertiary">{{ $interview->application->vacancy->title }}</span>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.type')">{{ $interview->type->label() }} <span class="block text-caption-md text-text-tertiary">{{ $interview->mode->label() }}</span></x-table.td>
                <x-table.td :label="__('recruitment.fields.interviewers')" class="text-text-secondary">{{ $interview->interviewers->pluck('name')->implode(__('recruitment.common.separator')) }}</x-table.td>
                <x-table.td :label="__('recruitment.fields.status')">@include('admin.recruitment._badge', ['value' => $interview->status])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="calendar" :title="__('recruitment.interviews.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$interviews" />
        </x-slot:footer>
    </x-table>
@endsection
