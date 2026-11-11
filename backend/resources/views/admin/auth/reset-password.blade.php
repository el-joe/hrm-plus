@extends('layouts.auth')

@section('title', __('app.auth.reset_title'))

@section('content')
    <form method="POST" action="{{ route('admin.password.update') }}" class="flex flex-col gap-5" data-validate>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input name="email" type="email" :label="__('app.common.email')" :value="old('email', $email)" icon="mail"
            required autocomplete="username" />

        <x-form.input name="password" type="password" :label="__('app.auth.new_password')" icon="lock"
            required autocomplete="new-password" />

        <x-form.input name="password_confirmation" type="password" :label="__('app.common.password_confirmation')" icon="lock"
            required autocomplete="new-password" />

        <x-button size="lg" class="w-full">{{ __('app.auth.reset_password') }}</x-button>
    </form>

    <a href="{{ route('admin.login') }}" class="inline-flex items-center gap-1 text-label-md text-text-link hover:underline">
        <x-icon name="arrow-left" size="sm" flip />{{ __('app.auth.back_to_login') }}
    </a>
@endsection
