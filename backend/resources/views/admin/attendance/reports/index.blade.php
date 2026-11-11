@extends('layouts.admin', ['subtitle' => __('attendance_reports.index.subtitle')])

@section('title', __('attendance_reports.index.title'))

@section('content')
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $report)
            <a href="{{ route('admin.attendance.reports.show', $report) }}" class="flex flex-col gap-2 rounded-xl border border-border-subtle bg-surface-default p-5 shadow-xs transition-shadow hover:shadow-sm" data-report="{{ $report }}">
                <span class="flex items-center gap-2 text-h5 text-text-primary"><x-icon name="file-text" class="text-text-link" /> {{ __('attendance_reports.reports.'.$report.'.title') }}</span>
                <span class="text-body-sm text-text-secondary">{{ __('attendance_reports.reports.'.$report.'.description') }}</span>
            </a>
        @endforeach
    </div>
@endsection
