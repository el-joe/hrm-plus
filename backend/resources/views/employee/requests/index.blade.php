@extends('layouts.employee', ['subtitle' => __('hr_requests.portal.subtitle')])

@section('title', __('hr_requests.portal.title'))

@section('page-actions')
    @if ($enabled)
        <x-button icon="plus" :href="route('portal.requests.create')">{{ __('hr_requests.portal.new') }}</x-button>
    @endif
@endsection

@section('content')
    @unless ($enabled)
        <x-alert tone="info">{{ __('hr_requests.portal.disabled') }}</x-alert>
    @endunless

    <x-tabs :label="__('hr_requests.portal.title')" :items="collect(['' => 'all', 'open' => 'open', 'closed' => 'closed'])->map(fn ($label, $value) => [
        'label' => __('hr_requests.portal.'.$label),
        'url' => route('portal.requests.index', $value === '' ? [] : ['status' => $value]),
        'active' => ($filters['status'] ?? '') === $value,
    ])->values()->all()" />

    <x-card padding="none" data-region="my-requests">
        <ul class="divide-y divide-border-subtle">
            @forelse ($requests as $row)
                <li class="flex flex-col gap-1 px-4 py-4 sm:px-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <a href="{{ route('portal.requests.show', $row->id) }}" class="min-w-0 text-label-md text-text-link hover:underline">{{ $row->subject }}</a>
                        @include('admin.hr-requests._status', ['status' => $row->status])
                    </div>
                    <p class="text-body-sm text-text-secondary">
                        {{ $row->category->name }} · <span dir="ltr">{{ $row->reference }}</span> · <x-date :value="$row->created_at->timezone(setting('localisation.timezone'))" />
                    </p>
                    @if ($row->status === App\Enums\HrRequestStatus::AwaitingEmployee)
                        <p class="text-caption-md text-feedback-warning-text">{{ __('hr_requests.portal.waiting_for_you') }}</p>
                    @endif
                </li>
            @empty
                <li class="px-5 py-6">
                    <x-empty-state icon="inbox" :title="__('hr_requests.portal.empty')" :description="__('hr_requests.portal.empty_hint')">
                        @if ($enabled)
                            <x-button icon="plus" :href="route('portal.requests.create')">{{ __('hr_requests.portal.new') }}</x-button>
                        @endif
                    </x-empty-state>
                </li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-pagination :paginator="$requests" />
        </x-slot:footer>
    </x-card>
@endsection
