@extends('layouts.employee')

@section('title', $doc['period']['name'])

@section('page-actions')
    <x-button variant="ghost" icon="arrow-left" :href="route('portal.payroll')">{{ __('payroll_runs.portal.title') }}</x-button>
    <x-button variant="ghost" icon="globe" :href="route('portal.payroll.show', [$payslip->id, 'lang' => $doc['locale'] === 'ar' ? 'en' : 'ar'])">{{ $doc['locale'] === 'ar' ? 'English' : 'العربية' }}</x-button>
    <x-button icon="download" :href="route('portal.payroll.pdf', [$payslip->id, 'lang' => $doc['locale'], 'download' => 1])">{{ __('payroll_runs.payslips.download_pdf') }}</x-button>
@endsection

@section('content')
    @include('payslips._document')
@endsection
