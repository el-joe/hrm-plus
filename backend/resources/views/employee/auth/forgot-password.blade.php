@extends('layouts.auth')

@section('title', __('app.auth.forgot_title'))
@section('intro', __('app.auth.forgot_intro'))

@section('content')
    <form method="POST" action="{{ route('portal.password.email') }}" class="flex flex-col gap-5" data-validate>
        @csrf

        <x-form.input name="email" type="email" :label="__('app.common.email')" :value="old('email')" icon="mail"
            required autofocus autocomplete="username" />

        <x-button size="lg" class="w-full">{{ __('app.auth.send_link') }}</x-button>
    </form>

    <a href="{{ route('portal.login') }}" class="inline-flex items-center gap-1 text-label-md text-text-link hover:underline">
        <x-icon name="arrow-left" size="sm" flip />{{ __('app.auth.back_to_login') }}
    </a>
@endsection
