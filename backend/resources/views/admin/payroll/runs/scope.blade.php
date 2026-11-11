@extends('layouts.admin', ['breadcrumbs' => [
    ['label' => __('payroll_runs.runs.title'), 'url' => route('admin.payroll-runs.index')],
    ['label' => $run->reference, 'url' => route('admin.payroll-runs.show', $run)],
]])

@section('title', __('payroll_runs.runs.change_scope'))

@section('content')
    <x-alert tone="info">{{ __('payroll_runs.runs.change_scope_help') }}</x-alert>
    <form method="POST" action="{{ route('admin.payroll-runs.scope.update', $run) }}" class="flex flex-col gap-4 lg:gap-6" data-validate>
        @csrf
        @method('PUT')
        @include('admin.payroll.runs._scope-fields')
        <x-form.actions>
            <x-button variant="secondary" :href="route('admin.payroll-runs.show', $run)">{{ __('app.actions.cancel') }}</x-button>
            <x-button icon="check">{{ __('app.actions.save') }}</x-button>
        </x-form.actions>
    </form>
@endsection
