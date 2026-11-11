@extends('layouts.admin', ['subtitle' => __('recruitment.offers.subtitle')])

@section('title', __('recruitment.offers.title'))

@section('content')
    <x-filter-bar :action="route('admin.offers.index')" :reset="route('admin.offers.index')">
        <x-form.select name="status" id="filter-status" :label="__('recruitment.fields.status')" hide-label class="md:w-48">
            <option value="">{{ __('recruitment.fields.status') }}: {{ __('app.common.all') }}</option>
            @foreach (App\Enums\OfferStatus::cases() as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('recruitment.offers.title')">
        <x-slot:head>
            <tr>
                <x-table.th>{{ __('recruitment.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-56">{{ __('recruitment.fields.candidate') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.start_date') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.expires_on') }}</x-table.th>
                <x-table.th>{{ __('recruitment.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($offers as $offer)
            <x-table.row :data-href="route('admin.offers.show', $offer)">
                <x-table.td :label="__('recruitment.fields.reference')"><a href="{{ route('admin.offers.show', $offer) }}" class="text-label-md text-text-link" dir="ltr">{{ $offer->reference }}</a></x-table.td>
                <x-table.td :label="__('recruitment.fields.candidate')">
                    <span class="block text-label-md">{{ $offer->application->candidate->name }}</span>
                    <span class="text-caption-md text-text-tertiary">{{ $offer->jobTitle?->name ?? $offer->application->vacancy->title }}</span>
                </x-table.td>
                <x-table.td :label="__('recruitment.fields.start_date')" class="tabular-nums"><span dir="ltr">{{ $offer->start_date->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('recruitment.fields.expires_on')" class="tabular-nums"><span dir="ltr">{{ $offer->expires_on->toDateString() }}</span></x-table.td>
                <x-table.td :label="__('recruitment.fields.status')">@include('admin.recruitment._badge', ['value' => $offer->status])</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="mail" :title="__('recruitment.offers.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$offers" />
        </x-slot:footer>
    </x-table>
@endsection
