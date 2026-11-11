@extends('layouts.admin', ['subtitle' => __('leave.reports.subtitle')])

@section('title', __('leave.reports.title'))

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $report)
            <a href="{{ route('admin.leave-reports.show', $report) }}" class="flex flex-col gap-2 rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs transition-shadow hover:shadow-sm" data-report="{{ $report }}">
                <span class="flex items-center gap-2 text-h5 text-text-primary"><x-icon name="file-text" class="text-text-link" /> {{ __('leave.reports.list.'.$report.'.title') }}</span>
                <span class="text-body-sm text-text-secondary">{{ __('leave.reports.list.'.$report.'.description') }}</span>
            </a>
        @endforeach
    </div>
@endsection
