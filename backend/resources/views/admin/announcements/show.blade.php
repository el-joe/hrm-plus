@extends('layouts.admin', ['breadcrumbs' => [['label' => __('announcements.title'), 'url' => route('admin.announcements.index')]]])

@section('title', $announcement->title() ?: __('announcements.untitled'))

@section('page-actions')
    @can('export', $announcement)
        @unless ($announcement->isDraft())
            <x-button variant="ghost" icon="download" :href="route('admin.announcements.export', $announcement)">{{ __('announcements.show.export_xlsx') }}</x-button>
            <x-button variant="ghost" icon="download" :href="route('admin.announcements.export', [$announcement, 'format' => 'csv'])">{{ __('announcements.show.export_csv') }}</x-button>
        @endunless
    @endcan
    @can('update', $announcement)
        <form method="POST" action="{{ route('admin.announcements.pin', $announcement) }}">
            @csrf
            <input type="hidden" name="pinned" value="{{ $announcement->is_pinned ? 0 : 1 }}">
            <x-button variant="secondary" icon="pin">{{ $announcement->is_pinned ? __('announcements.show.unpin') : __('announcements.show.pin') }}</x-button>
        </form>
        <x-button variant="secondary" icon="pencil" :href="route('admin.announcements.edit', $announcement)">{{ __('app.actions.edit') }}</x-button>
        @if ($announcement->isDraft())
            <form method="POST" action="{{ route('admin.announcements.publish', $announcement) }}" data-confirm="{{ __('announcements.show.publish_confirm') }}">
                @csrf
                <x-button icon="send">{{ __('announcements.show.publish') }}</x-button>
            </form>
        @endif
    @endcan
    @if ($announcement->status === App\Enums\AnnouncementStatus::Published)
        @can('archive', $announcement)
            <form method="POST" action="{{ route('admin.announcements.archive', $announcement) }}" data-confirm="{{ __('announcements.show.archive_confirm') }}" data-confirm-tone="warning">
                @csrf
                <x-button variant="secondary" icon="inbox">{{ __('announcements.show.archive') }}</x-button>
            </form>
        @endcan
    @endif
    @can('delete', $announcement)
        <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" data-confirm="{{ __('announcements.show.delete_confirm') }}" data-confirm-tone="danger">
            @csrf
            @method('DELETE')
            <x-button variant="danger" icon="trash">{{ __('announcements.show.delete') }}</x-button>
        </form>
    @endcan
@endsection

@section('content')
    @if ($errors->any())
        <x-alert tone="danger">
            <ul class="list-disc ps-5">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-alert>
    @endif

    @php($state = $announcement->state())
    @if ($state === App\Enums\AnnouncementStatus::Draft)
        <x-alert tone="info" data-notice="draft">{{ __('announcements.show.draft_note') }}</x-alert>
    @elseif ($state === App\Enums\AnnouncementStatus::Scheduled)
        <x-alert tone="info" data-notice="scheduled">{{ __('announcements.show.scheduled_note', ['date' => $announcement->publish_at->format('Y-m-d H:i')]) }}</x-alert>
    @elseif ($state === App\Enums\AnnouncementStatus::Expired)
        <x-alert tone="warning" data-notice="expired">{{ __('announcements.show.expired_note', ['date' => $announcement->expires_at->format('Y-m-d H:i')]) }}</x-alert>
    @elseif ($state === App\Enums\AnnouncementStatus::Archived)
        <x-alert tone="warning" data-notice="archived">{{ __('announcements.show.archived_note') }}</x-alert>
    @endif

    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-6">
            @foreach (['ar' => 'rtl', 'en' => 'ltr'] as $locale => $direction)
                @continue(blank($announcement->{'title_'.$locale}) && blank($announcement->{'body_'.$locale}))
                <x-card :title="__('announcements.show.preview').' · '.__('announcements.form.'.($locale === 'ar' ? 'arabic' : 'english'))" data-region="preview-{{ $locale }}">
                    <article dir="{{ $direction }}" lang="{{ $locale }}" class="flex flex-col gap-3">
                        <h2 class="text-h4 text-text-primary">{{ $announcement->{'title_'.$locale} }}</h2>
                        @include('admin.announcements._body', ['html' => (string) $announcement->{'body_'.$locale}, 'direction' => $direction, 'locale' => $locale])
                    </article>
                </x-card>
            @endforeach

            @if ($summary)
                <x-card :title="__('announcements.show.recipients')" padding="none" data-region="recipients">
                    <div class="p-4">
                        <x-filter-bar :action="route('admin.announcements.show', $announcement)" :reset="route('admin.announcements.show', $announcement)">
                            <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
                            <x-form.select name="reads" :label="__('announcements.fields.reads')" hide-label class="md:w-48">
                                <option value="">{{ __('announcements.show.filters.all') }}</option>
                                @foreach (['read', 'unread', ...($announcement->requires_acknowledgement ? ['acknowledged', 'not_acknowledged'] : [])] as $option)
                                    <option value="{{ $option }}" @selected(($filters['reads'] ?? '') === $option)>{{ __('announcements.show.filters.'.$option) }}</option>
                                @endforeach
                            </x-form.select>
                        </x-filter-bar>
                    </div>
                    <x-table :caption="__('announcements.show.recipients')">
                        <x-slot:head>
                            <tr>
                                <x-table.th>{{ __('announcements.report.employee') }}</x-table.th>
                                <x-table.th>{{ __('announcements.report.department') }}</x-table.th>
                                <x-table.th>{{ __('announcements.fields.reads') }}</x-table.th>
                            </tr>
                        </x-slot:head>
                        @forelse ($recipients as $employee)
                            @php($read = $reads->get($employee->id))
                            <x-table.row>
                                <x-table.td :label="__('announcements.report.employee')">
                                    <span class="text-label-md text-text-primary">{{ $employee->name }}</span>
                                    <span class="block text-caption-md text-text-tertiary" dir="ltr">{{ $employee->employee_number }}</span>
                                </x-table.td>
                                <x-table.td :label="__('announcements.report.department')">{{ $employee->department?->name ?? '—' }}<span class="block text-caption-md text-text-tertiary">{{ $employee->branch?->name }}</span></x-table.td>
                                <x-table.td :label="__('announcements.fields.reads')">
                                    @if ($read)
                                        <x-badge tone="success">{{ __('announcements.show.read_on', ['date' => $read->read_at->format('Y-m-d H:i')]) }}</x-badge>
                                    @else
                                        <x-badge tone="neutral">{{ __('announcements.show.not_read') }}</x-badge>
                                    @endif
                                    @if ($announcement->requires_acknowledgement)
                                        <span class="mt-1 block">
                                            @if ($read?->acknowledged_at)
                                                <x-badge tone="success" dot>{{ __('announcements.show.acknowledged_on', ['date' => $read->acknowledged_at->format('Y-m-d H:i')]) }}</x-badge>
                                            @else
                                                <x-badge tone="warning" dot>{{ __('announcements.show.not_acknowledged') }}</x-badge>
                                            @endif
                                        </span>
                                    @endif
                                </x-table.td>
                            </x-table.row>
                        @empty
                            <x-table.empty :colspan="3" icon="users" :title="__('announcements.show.no_recipients')" />
                        @endforelse
                        <x-slot:footer>
                            <x-pagination :paginator="$recipients" />
                        </x-slot:footer>
                    </x-table>
                </x-card>
            @endif
        </div>

        <div class="flex min-w-0 flex-col gap-4 lg:gap-6">
            @if ($summary)
                <x-card :title="__('announcements.show.statistics')" :subtitle="__('announcements.show.statistics_help')" data-region="statistics">
                    <dl class="flex flex-col gap-4">
                        <div class="flex items-baseline justify-between gap-2">
                            <dt class="text-body-sm text-text-secondary">{{ __('announcements.stats.audience') }}</dt>
                            <dd class="text-numeric-md text-text-primary" data-stat="audience">{{ $summary['audience'] }}</dd>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <div class="flex items-baseline justify-between gap-2">
                                <dt class="text-body-sm text-text-secondary">{{ __('announcements.stats.read') }}</dt>
                                <dd class="text-label-md text-text-primary" data-stat="read">{{ __('announcements.stats.of_audience', ['count' => $summary['read'], 'total' => $summary['audience'], 'percent' => $summary['read_percent']]) }}</dd>
                            </div>
                            @include('admin.announcements._meter', ['percent' => $summary['read_percent'], 'label' => __('announcements.stats.read').' '.$summary['read_percent'].'%'])
                        </div>
                        @if ($announcement->requires_acknowledgement)
                            <div class="flex flex-col gap-1.5">
                                <div class="flex items-baseline justify-between gap-2">
                                    <dt class="text-body-sm text-text-secondary">{{ __('announcements.stats.acknowledged') }}</dt>
                                    <dd class="text-label-md text-text-primary" data-stat="acknowledged">{{ __('announcements.stats.of_audience', ['count' => $summary['acknowledged'], 'total' => $summary['audience'], 'percent' => $summary['acknowledged_percent']]) }}</dd>
                                </div>
                                @include('admin.announcements._meter', ['percent' => $summary['acknowledged_percent'], 'tone' => 'success', 'label' => __('announcements.stats.acknowledged').' '.$summary['acknowledged_percent'].'%'])
                            </div>
                        @endif
                    </dl>
                </x-card>
            @endif

            <x-card :title="__('announcements.show.details')" data-region="details">
                <x-detail-list>
                    <x-detail-item :label="__('announcements.fields.status')">@include('admin.announcements._status')</x-detail-item>
                    <x-detail-item :label="__('announcements.fields.type')"><x-badge :tone="$announcement->type->tone()">{{ $announcement->type->label() }}</x-badge></x-detail-item>
                    <x-detail-item :label="__('announcements.fields.audience')">@include('admin.announcements._audience', ['limit' => 50])</x-detail-item>
                    <x-detail-item :label="__('announcements.fields.publish_at')">
                        @if ($announcement->publish_at ?? $announcement->published_at)
                            <span dir="ltr">{{ ($announcement->publish_at ?? $announcement->published_at)->format('Y-m-d H:i') }}</span>
                        @else
                            —
                        @endif
                    </x-detail-item>
                    <x-detail-item :label="__('announcements.fields.expires_at')">
                        @if ($announcement->expires_at) <span dir="ltr">{{ $announcement->expires_at->format('Y-m-d H:i') }}</span> @else — @endif
                    </x-detail-item>
                    <x-detail-item :label="__('announcements.fields.author')">{{ $announcement->author?->name ?? '—' }}</x-detail-item>
                </x-detail-list>
                <div class="mt-4 flex flex-wrap gap-1.5">
                    @if ($announcement->is_pinned) <x-badge tone="primary">{{ __('announcements.portal.pinned') }}</x-badge> @endif
                    @if ($announcement->requires_acknowledgement) <x-badge tone="warning">{{ __('announcements.portal.requires_acknowledgement') }}</x-badge> @endif
                    @if ($announcement->notify_push) <x-badge tone="info">{{ __('announcements.fields.notify_push') }}</x-badge> @endif
                    @if ($announcement->notify_email) <x-badge tone="info">{{ __('announcements.fields.notify_email') }}</x-badge> @endif
                </div>
                @unless ($announcement->isDraft())
                    <p class="mt-3 text-caption-md text-text-tertiary">
                        {{ $announcement->notified_at
                            ? __('announcements.show.notified', ['date' => $announcement->notified_at->format('Y-m-d H:i'), 'count' => $announcement->notified_count])
                            : __('announcements.show.not_notified') }}
                    </p>
                @endunless
            </x-card>

            @if ($announcement->attachments->isNotEmpty())
                <x-card :title="__('announcements.fields.attachments')" data-region="attachments">
                    <ul class="flex flex-col gap-2">
                        @foreach ($announcement->attachments as $attachment)
                            <li class="flex items-center gap-2">
                                <x-icon name="paperclip" size="sm" class="shrink-0 text-text-tertiary" />
                                <a class="min-w-0 truncate text-body-sm text-text-link hover:underline" href="{{ route('admin.announcements.attachments.download', [$announcement, $attachment]) }}">{{ $attachment->file_name }}</a>
                            </li>
                        @endforeach
                    </ul>
                </x-card>
            @endif
        </div>
    </div>
@endsection
