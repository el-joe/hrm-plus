{{--
    Shared create / edit page of the organisation-structure records.
    Variables (from StructureController::formPage): record, formView, title, action,
    cancelUrl, breadcrumbs, multipart, plus whatever the entity's _form partial needs.
--}}
@extends('layouts.admin', ['breadcrumbs' => $breadcrumbs])

@section('title', $title)

@section('content')
    <form method="POST" action="{{ $action }}" class="flex flex-col gap-4 lg:gap-6" data-validate
        @if ($multipart) enctype="multipart/form-data" @endif>
        @csrf
        @if ($record->exists)
            @method('PUT')
        @endif

        @include($formView)

        <x-form.actions>
            @if ($cancelUrl)
                <x-button variant="secondary" :href="$cancelUrl">{{ __('app.actions.cancel') }}</x-button>
            @endif
            <x-button>{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
