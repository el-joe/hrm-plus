@extends('layouts.admin', ['subtitle' => __('hr_requests.subtitle')])

@section('title', __('hr_requests.queue.title'))

@section('page-actions')
    @can('export', App\Models\HrRequest::class)
        <x-button variant="ghost" icon="download" :href="route('admin.hr-requests.export', request()->query())">{{ __('hr_requests.queue.export') }}</x-button>
    @endcan
    @can('create', App\Models\HrRequest::class)
        <x-button icon="plus" :href="route('admin.hr-requests.create')">{{ __('hr_requests.queue.new') }}</x-button>
    @endcan
@endsection

@section('content')
    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5" data-region="hr-request-stats">
        <x-stat-card :label="__('hr_requests.queue.stats.open')" :value="$counts['open']" icon="inbox" :href="route('admin.hr-requests.index', ['status' => 'open'])" />
        <x-stat-card :label="__('hr_requests.queue.stats.mine')" :value="$counts['mine']" icon="user" :href="route('admin.hr-requests.index', ['status' => 'open', 'assignee' => 'mine'])" />
        <x-stat-card :label="__('hr_requests.queue.stats.unassigned')" :value="$counts['unassigned']" icon="users" :href="route('admin.hr-requests.index', ['status' => 'open', 'assignee' => 'unassigned'])" />
        <x-stat-card :label="__('hr_requests.queue.stats.overdue')" :value="$counts['overdue']" icon="alert-triangle" :href="route('admin.hr-requests.index', ['overdue' => 1])" />
        <x-stat-card :label="__('hr_requests.queue.stats.pending_approval')" :value="$counts['pending_approval']" icon="clock" :href="route('admin.hr-requests.index', ['status' => 'pending_approval'])" />
    </div>

    <x-filter-bar :action="route('admin.hr-requests.index')" :reset="route('admin.hr-requests.index')">
        <x-form.input name="search" type="search" icon="search" :label="__('app.common.search')" :placeholder="__('hr_requests.queue.filters.search')" hide-label :value="$filters['search'] ?? ''" class="md:w-56" data-filter-debounce />
        <x-form.select name="status" :label="__('hr_requests.fields.status')" hide-label class="md:w-48">
            <option value="">{{ __('hr_requests.queue.filters.all_statuses') }}</option>
            <option value="open" @selected(($filters['status'] ?? '') === 'open')>{{ __('hr_requests.queue.filters.open') }}</option>
            <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>{{ __('hr_requests.queue.filters.closed') }}</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="category_id" :label="__('hr_requests.fields.category')" hide-label class="md:w-48">
            <option value="">{{ __('hr_requests.queue.filters.all_categories') }}</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((string) ($filters['category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="priority" :label="__('hr_requests.fields.priority')" hide-label class="md:w-40">
            <option value="">{{ __('hr_requests.queue.filters.all_priorities') }}</option>
            @foreach ($priorities as $priority)
                <option value="{{ $priority->value }}" @selected(($filters['priority'] ?? '') === $priority->value)>{{ $priority->label() }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="assignee" :label="__('hr_requests.fields.assigned_to')" hide-label class="md:w-44">
            <option value="">{{ __('hr_requests.queue.filters.any_assignee') }}</option>
            <option value="mine" @selected(($filters['assignee'] ?? '') === 'mine')>{{ __('hr_requests.queue.filters.mine') }}</option>
            <option value="unassigned" @selected(($filters['assignee'] ?? '') === 'unassigned')>{{ __('hr_requests.queue.filters.unassigned') }}</option>
            @foreach ($assignees as $assignee)
                <option value="{{ $assignee->id }}" @selected((string) ($filters['assignee'] ?? '') === (string) $assignee->id)>{{ $assignee->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="branch_id" :label="__('hr_requests.fields.branch')" hide-label class="md:w-40">
            <option value="">{{ __('hr_requests.queue.filters.all_branches') }}</option>
            @foreach ($branches as $branch)
                <option value="{{ $branch->id }}" @selected((string) ($filters['branch_id'] ?? '') === (string) $branch->id)>{{ $branch->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.select name="department_id" :label="__('hr_requests.fields.department')" hide-label class="md:w-44">
            <option value="">{{ __('hr_requests.queue.filters.all_departments') }}</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected((string) ($filters['department_id'] ?? '') === (string) $department->id)>{{ $department->name }}</option>
            @endforeach
        </x-form.select>
        <x-form.checkbox name="overdue" value="1" :label="__('hr_requests.queue.filters.overdue')" :checked="(bool) ($filters['overdue'] ?? false)" />
        <x-form.checkbox name="escalated" value="1" :label="__('hr_requests.queue.filters.escalated')" :checked="(bool) ($filters['escalated'] ?? false)" />
    </x-filter-bar>

    <x-table :caption="__('hr_requests.queue.title')">
        <x-slot:head>
            <tr>
                <x-table.th sort="reference">{{ __('hr_requests.fields.reference') }}</x-table.th>
                <x-table.th class="min-w-48">{{ __('hr_requests.fields.subject') }}</x-table.th>
                <x-table.th class="min-w-40">{{ __('hr_requests.fields.employee') }}</x-table.th>
                <x-table.th sort="priority">{{ __('hr_requests.fields.priority') }}</x-table.th>
                <x-table.th sort="status">{{ __('hr_requests.fields.status') }}</x-table.th>
                <x-table.th>{{ __('hr_requests.fields.assigned_to') }}</x-table.th>
                <x-table.th sort="due">{{ __('hr_requests.fields.due_at') }}</x-table.th>
                <x-table.th sort="submitted">{{ __('hr_requests.fields.submitted_at') }}</x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($requests as $row)
            <x-table.row :data-href="route('admin.hr-requests.show', $row)" data-request-id="{{ $row->id }}">
                <x-table.td :label="__('hr_requests.fields.reference')" class="whitespace-nowrap">
                    <a href="{{ route('admin.hr-requests.show', $row) }}" class="text-label-md text-text-link hover:underline" dir="ltr">{{ $row->reference }}</a>
                </x-table.td>
                <x-table.td :label="__('hr_requests.fields.subject')">
                    <span class="block text-label-md text-text-primary">{{ $row->subject }}</span>
                    <span class="block text-caption-md text-text-tertiary">{{ $row->category->name }}</span>
                </x-table.td>
                <x-table.td :label="__('hr_requests.fields.employee')">{{ $row->employee->label() }}</x-table.td>
                <x-table.td :label="__('hr_requests.fields.priority')">@include('admin.hr-requests._priority', ['priority' => $row->priority])</x-table.td>
                <x-table.td :label="__('hr_requests.fields.status')">@include('admin.hr-requests._status', ['status' => $row->status])</x-table.td>
                <x-table.td :label="__('hr_requests.fields.assigned_to')">{{ $row->assignee?->name ?? __('hr_requests.common.unassigned') }}</x-table.td>
                <x-table.td :label="__('hr_requests.fields.due_at')">
                    @include('admin.hr-requests._sla', ['hrRequest' => $row])
                    @if ($row->isClosed()) <span class="text-text-tertiary">—</span> @endif
                </x-table.td>
                <x-table.td :label="__('hr_requests.fields.submitted_at')" class="whitespace-nowrap"><span dir="ltr">{{ $row->created_at->timezone(setting('localisation.timezone'))->format('Y-m-d H:i') }}</span></x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="8" icon="inbox" :title="__('hr_requests.queue.empty')" :description="__('hr_requests.queue.empty_hint')" />
        @endforelse
        <x-slot:footer>
            <x-pagination :paginator="$requests" />
        </x-slot:footer>
    </x-table>
@endsection
