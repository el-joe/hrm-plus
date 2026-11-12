@extends('layouts.employee')

@section('title', __('hr_requests.portal.new_title'))

@section('content')
    <form method="POST" action="{{ route('portal.requests.store') }}" enctype="multipart/form-data" class="flex flex-col gap-4 md:gap-6" data-validate>
        @csrf
        <x-form.section :title="__('hr_requests.portal.new_title')" :description="__('hr_requests.portal.subtitle')">
            @include('admin.hr-requests._request-fields', [
                'priorities' => [App\Enums\HrRequestPriority::Low, App\Enums\HrRequestPriority::Normal, App\Enums\HrRequestPriority::High],
            ])
        </x-form.section>

        <x-form.actions>
            <x-button variant="secondary" :href="route('portal.requests.index')">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="send">{{ __('hr_requests.portal.submit') }}</x-button>
        </x-form.actions>
    </form>
@endsection
