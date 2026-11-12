@extends('layouts.admin', ['subtitle' => __('announcements.subtitle')])

@section('title', __('announcements.title'))

@section('page-actions')
    @can('create', App\Models\Announcement::class)
        <x-button icon="plus" :href="route('admin.announcements.create')">{{ __('announcements.create') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('announcements.stats.published')" :value="$counts['published']" icon="megaphone" :href="route('admin.announcements.index', ['status' => 'published'])" />
        <x-stat-card :label="__('announcements.stats.scheduled')" :value="$counts['scheduled']" icon="clock" :href="route('admin.announcements.index', ['status' => 'scheduled'])" />
        <x-stat-card :label="__('announcements.stats.drafts')" :value="$counts['drafts']" icon="pencil" :href="route('admin.announcements.index', ['status' => 'draft'])" />
        <x-stat-card :label="__('announcements.stats.pinned')" :value="$counts['pinned']" icon="pin" />
    </div>

    <x-filter-bar :action="route('admin.announcements.index')" :reset="route('admin.announcements.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-64" data-filter-debounce />
        <x-form.select name="status" :label="__('announcements.fields.status')" hide-label class="md:w-44">
            <option value="">{{ __('announcements.fields.status') }}: {{ __('announcements.all') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="type" :label="__('announcements.fields.type')" hide-label class="md:w-44">
            <option value="">{{ __('announcements.fields.type') }}: {{ __('announcements.all') }}</option>
            @foreach ($types as $type)
                <option value="{{ $type->value }}" @selected(($filters['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
            @endforeach
        </x-form.select>
    </x-filter-bar>

    <x-table :caption="__('announcements.title')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-64">{{ __('announcements.fields.title') }}</x-table.th>
                <x-table.th>{{ __('announcements.fields.audience') }}</x-table.th>
                <x-table.th>{{ __('announcements.fields.publish_at') }}</x-table.th>
                <x-table.th class="min-w-40">{{ __('announcements.fields.reads') }}</x-table.th>
                <x-table.th>{{ __('announcements.fields.status') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($announcements as $announcement)
            @php($summary = $summaries[$announcement->id] ?? null)
            <x-table.row :data-href="route('admin.announcements.show', $announcement)">
                <x-table.td :label="__('announcements.fields.title')">
                    <span class="flex items-center gap-1.5">
                        @if ($announcement->is_pinned)
                            <x-icon name="pin" size="sm" class="shrink-0 text-text-link" /><span class="sr-only">{{ __('announcements.portal.pinned') }}</span>
                        @endif
                        <a href="{{ route('admin.announcements.show', $announcement) }}" class="text-label-md text-text-link hover:underline">{{ $announcement->title() ?: __('announcements.untitled') }}</a>
                    </span>
                    <span class="mt-1 flex flex-wrap items-center gap-1.5">
                        <x-badge :tone="$announcement->type->tone()">{{ $announcement->type->label() }}</x-badge>
                        @if ($announcement->requires_acknowledgement)
                            <x-badge tone="warning">{{ __('announcements.portal.requires_acknowledgement') }}</x-badge>
                        @endif
                    </span>
                </x-table.td>
                <x-table.td :label="__('announcements.fields.audience')">@include('admin.announcements._audience', ['limit' => 2])</x-table.td>
                <x-table.td :label="__('announcements.fields.publish_at')" class="whitespace-nowrap">
                    @if ($announcement->publish_at || $announcement->published_at)
                        <span dir="ltr">{{ ($announcement->publish_at ?? $announcement->published_at)->format('Y-m-d H:i') }}</span>
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                    @if ($announcement->expires_at)
                        <span class="block text-caption-md text-text-tertiary">{{ __('announcements.fields.expires_at') }}: <span dir="ltr">{{ $announcement->expires_at->format('Y-m-d') }}</span></span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('announcements.fields.reads')">
                    @if ($summary)
                        <span class="text-body-sm text-text-secondary">{{ __('announcements.stats.of_audience', ['count' => $summary['read'], 'total' => $summary['audience'], 'percent' => $summary['read_percent']]) }}</span>
                        <span class="mt-1 block">@include('admin.announcements._meter', ['percent' => $summary['read_percent'], 'label' => __('announcements.stats.read').' '.$summary['read_percent'].'%'])</span>
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('announcements.fields.status')">@include('admin.announcements._status')</x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="megaphone" :title="__('announcements.empty')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$announcements" />
        </x-slot:footer>
    </x-table>
@endsection
