@extends('layouts.auth')

@section('title', __('app.auth.portal_login_title'))
@section('intro', __('app.auth.portal_login_intro'))

@section('content')
    <form method="POST" action="{{ route('portal.login.store') }}" class="flex flex-col gap-5" data-validate>
        @csrf

        <x-form.input name="email" type="email" :label="__('app.common.email')" :value="old('email')" icon="mail"
            required autofocus autocomplete="username" />

        <x-form.input name="password" type="password" :label="__('app.common.password')" icon="lock"
            required autocomplete="current-password" />

        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-form.checkbox name="remember" id="remember" :label="__('app.auth.remember')" />
            <a href="{{ route('portal.password.request') }}" class="text-label-md text-text-link hover:underline">{{ __('app.auth.forgot_link') }}</a>
        </div>

        <x-button size="lg" class="w-full">{{ __('app.auth.login') }}</x-button>
    </form>
@endsection
