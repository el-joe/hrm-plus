{{-- What the reporter of a case sees without disciplinary.view: reference, employee, violation and status only. --}}
@extends('layouts.admin', ['breadcrumbs' => [['label' => __('disciplinary.cases.title'), 'url' => route('admin.disciplinary-cases.index')]]])

@section('title', $case->reference)

@section('content')
    <x-alert tone="info">{{ __('disciplinary.cases.summary_notice') }}</x-alert>
    <x-card :title="__('disciplinary.cases.incident')" data-region="case-summary">
        <x-slot:actions>@include('admin.disciplinary.cases._status', ['status' => $case->status])</x-slot:actions>
        <x-detail-list>
            <x-detail-item :label="__('disciplinary.fields.employee')">{{ $case->employee->label() }}</x-detail-item>
            <x-detail-item :label="__('disciplinary.fields.violation_type')">{{ $case->violationType->name }}</x-detail-item>
            <x-detail-item :label="__('disciplinary.fields.incident_date')"><span dir="ltr">{{ $case->incident_date->toDateString() }}</span></x-detail-item>
            <x-detail-item :label="__('disciplinary.fields.reported_on')"><span dir="ltr">{{ $case->created_at->toDateString() }}</span></x-detail-item>
        </x-detail-list>
    </x-card>
@endsection
