@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('recruitment.candidates.title'), 'url' => route('admin.candidates.index')]],
    'subtitle' => $candidate->current_job_title,
])

@section('title', $candidate->name)

@section('page-actions')
    @if ($candidate->cv_path)
        <x-button variant="secondary" :href="route('admin.candidates.cv', $candidate)" icon="download">{{ __('recruitment.candidates.download_cv') }}</x-button>
    @endif
    @can('update', $candidate)
        <x-button variant="secondary" :href="route('admin.candidates.edit', $candidate)" icon="pencil">{{ __('app.actions.edit') }}</x-button>
    @endcan
    @can('delete', $candidate)
        <form method="POST" action="{{ route('admin.candidates.destroy', $candidate) }}" data-confirm="{{ __('recruitment.candidates.delete_confirm', ['name' => $candidate->name]) }}">
            @csrf
            @method('DELETE')
            <x-button variant="danger" icon="trash">{{ __('app.actions.delete') }}</x-button>
        </form>
    @endcan
@endsection

@section('content')
    @if ($candidate->employee)
        <x-alert tone="success" :title="__('recruitment.candidates.hired_as', ['number' => $candidate->employee->employee_number])">
            @can('view', $candidate->employee)
                <a class="text-text-link underline" href="{{ route('admin.employees.show', $candidate->employee) }}">{{ __('recruitment.candidates.open_employee') }}</a>
            @endcan
        </x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('recruitment.candidates.profile')">
                <div class="mb-4 flex items-center gap-3">
                    <x-avatar :name="$candidate->name" size="lg" />
                    <div class="min-w-0">
                        <p class="text-h5">{{ $candidate->name }}</p>
                        @if ($candidate->name_ar && $candidate->name_en)
                            <p class="text-body-sm text-text-tertiary">{{ app()->getLocale() === 'ar' ? $candidate->name_en : $candidate->name_ar }}</p>
                        @endif
                    </div>
                </div>
                <x-detail-list :columns="1">
                    <x-detail-item :label="__('app.common.email')"><span dir="ltr">{{ $candidate->email ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.phone')"><span dir="ltr">{{ $candidate->phone ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.city')">{{ $candidate->city ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.current_employer')">{{ $candidate->current_employer ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.experience_years')">{{ $candidate->experience_years ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('recruitment.fields.source')">{{ $candidate->source->label() }}@if ($candidate->source_detail) — {{ $candidate->source_detail }}@endif</x-detail-item>
                    @if ($candidate->linkedin_url)
                        <x-detail-item :label="__('recruitment.fields.linkedin_url')"><a class="text-text-link underline" href="{{ $candidate->linkedin_url }}" rel="noopener noreferrer nofollow" target="_blank" dir="ltr">{{ $candidate->linkedin_url }}</a></x-detail-item>
                    @endif
                    @if ($candidate->portfolio_url)
                        <x-detail-item :label="__('recruitment.fields.portfolio_url')"><a class="text-text-link underline" href="{{ $candidate->portfolio_url }}" rel="noopener noreferrer nofollow" target="_blank" dir="ltr">{{ $candidate->portfolio_url }}</a></x-detail-item>
                    @endif
                    @if ($showSalary)
                        <x-detail-item :label="__('recruitment.fields.expected_salary')"><span dir="ltr" class="tabular-nums">{{ App\Support\Money::format($candidate->expected_salary) }}</span></x-detail-item>
                        <x-detail-item :label="__('recruitment.fields.current_salary')"><span dir="ltr" class="tabular-nums">{{ App\Support\Money::format($candidate->current_salary) }}</span></x-detail-item>
                    @elseif (! $canSeeSalary)
                        <x-detail-item :label="__('recruitment.fields.expected_salary')"><span class="text-text-tertiary">{{ __('recruitment.common.restricted') }}</span></x-detail-item>
                    @endif
                    <x-detail-item :label="__('recruitment.fields.cv')">{{ $candidate->cv_name ?? __('recruitment.candidates.no_cv') }}</x-detail-item>
                </x-detail-list>
                @if ($candidate->skills || $candidate->tags->isNotEmpty())
                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($candidate->skills ?? [] as $skill)
                            <x-badge tone="info">{{ $skill }}</x-badge>
                        @endforeach
                        @foreach ($candidate->tags as $tag)
                            <x-badge>#{{ $tag->name }}</x-badge>
                        @endforeach
                    </div>
                @endif
                @if ($candidate->qualifications)
                    <p class="mt-4 whitespace-pre-line text-body-sm text-text-secondary">{{ $candidate->qualifications }}</p>
                @endif
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('recruitment.candidates.applications')" padding="none">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($candidate->applications as $application)
                        <li class="flex flex-wrap items-center gap-3 px-5 py-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('admin.applications.show', $application) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $application->vacancy->title }}</a>
                                <span class="text-caption-md text-text-tertiary"><span dir="ltr">{{ $application->vacancy->reference }}</span> · {{ __('recruitment.fields.applied_at') }} <span dir="ltr">{{ $application->applied_at->toDateString() }}</span></span>
                            </div>
                            <span class="text-body-sm text-text-secondary">{{ $application->stage->name }}</span>
                            @include('admin.recruitment._badge', ['value' => $application->status])
                            @if ($application->average_score !== null)
                                <x-badge>{{ __('recruitment.fields.average_score') }}: {{ $application->average_score }}</x-badge>
                            @endif
                        </li>
                    @empty
                        <li class="px-5 py-6 text-body-md text-text-tertiary">{{ __('recruitment.candidates.no_applications') }}</li>
                    @endforelse
                </ul>
                @can('update', $candidate)
                    @if ($openVacancies->isNotEmpty() && ! $candidate->isHired())
                        <x-slot:footer>
                            <form method="POST" action="{{ route('admin.candidates.apply', $candidate) }}" class="flex flex-col gap-3 md:flex-row md:items-end">
                                @csrf
                                <x-form.select name="vacancy_id" :label="__('recruitment.candidates.add_to_vacancy')" required class="md:flex-1">
                                    @foreach ($openVacancies as $vacancy)
                                        <option value="{{ $vacancy->id }}">{{ $vacancy->reference }} · {{ $vacancy->title }}</option>
                                    @endforeach
                                </x-form.select>
                                <x-form.select name="source" :label="__('recruitment.fields.source')" required>
                                    @foreach (App\Enums\CandidateSource::cases() as $source)
                                        <option value="{{ $source->value }}" @selected($source === $candidate->source)>{{ $source->label() }}</option>
                                    @endforeach
                                </x-form.select>
                                <x-button variant="secondary" icon="plus">{{ __('recruitment.candidates.add') }}</x-button>
                            </form>
                        </x-slot:footer>
                    @endif
                @endcan
            </x-card>

            <x-card :title="__('recruitment.candidates.notes')">
                @can('update', $candidate)
                    <form method="POST" action="{{ route('admin.candidates.notes.store', $candidate) }}" class="mb-4 flex flex-col gap-3" data-validate>
                        @csrf
                        <x-form.textarea name="body" :label="__('recruitment.candidates.new_note')" hide-label rows="2" required maxlength="5000" :placeholder="__('recruitment.candidates.note_placeholder')" />
                        <div><x-button size="sm" icon="plus">{{ __('recruitment.candidates.add_note') }}</x-button></div>
                    </form>
                @endcan
                <ul class="flex flex-col gap-3">
                    @forelse ($candidate->notes as $note)
                        <li class="rounded-md bg-surface-subtle p-3">
                            <p class="whitespace-pre-line text-body-md">{{ $note->body }}</p>
                            <p class="mt-1 text-caption-md text-text-tertiary">{{ $note->author?->name ?? '—' }} · <span dir="ltr">{{ $note->created_at->format('Y-m-d H:i') }}</span></p>
                        </li>
                    @empty
                        <li class="text-body-md text-text-tertiary">{{ __('recruitment.candidates.no_notes') }}</li>
                    @endforelse
                </ul>
            </x-card>

            <x-card :title="__('recruitment.candidates.attachments')">
                <ul class="mb-4 divide-y divide-border-subtle">
                    @forelse ($candidate->attachments as $attachment)
                        <li class="flex items-center gap-3 py-2">
                            <x-icon name="file-text" class="text-text-tertiary" />
                            <div class="min-w-0 flex-1">
                                <a class="block truncate text-label-md text-text-link" href="{{ route('admin.candidates.attachments.download', [$candidate, $attachment]) }}">{{ $attachment->title }}</a>
                                <span class="text-caption-md text-text-tertiary">{{ $attachment->file_name }} · {{ $attachment->uploader?->name }}</span>
                            </div>
                            @can('update', $candidate)
                                <form method="POST" action="{{ route('admin.candidates.attachments.destroy', [$candidate, $attachment]) }}" data-confirm="{{ __('recruitment.candidates.delete_attachment_confirm') }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-icon-button icon="trash" type="submit" :label="__('app.actions.delete')" />
                                </form>
                            @endcan
                        </li>
                    @empty
                        <li class="py-2 text-body-md text-text-tertiary">{{ __('recruitment.candidates.no_attachments') }}</li>
                    @endforelse
                </ul>
                @can('update', $candidate)
                    <form method="POST" action="{{ route('admin.candidates.attachments.store', $candidate) }}" enctype="multipart/form-data" class="grid gap-3 md:grid-cols-[1fr_1fr_auto] md:items-end" data-validate>
                        @csrf
                        <x-form.input name="title" :label="__('recruitment.fields.attachment_title')" required maxlength="150" />
                        <x-form.file-input name="file" :label="__('recruitment.fields.file')" required />
                        <x-button variant="secondary" icon="upload">{{ __('recruitment.candidates.upload') }}</x-button>
                    </form>
                @endcan
            </x-card>
        </div>
    </div>
@endsection
