@extends('layouts.admin', [
    'breadcrumbs' => [['label' => __('assets.title'), 'url' => route('admin.assets.index')]],
    'subtitle' => $asset->category?->name.' · '.($asset->branch?->name ?? '—'),
])

@section('title', $asset->asset_tag.' · '.$asset->name)

@section('page-actions')
    @if ($canUpdate)
        <x-button variant="secondary" icon="pencil" :href="route('admin.assets.edit', $asset)">{{ __('app.actions.edit') }}</x-button>
    @endif
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

    @php($current = $asset->currentAssignment)
    <div class="grid gap-4 lg:grid-cols-3 lg:gap-6">
        <div class="flex min-w-0 flex-col gap-4 lg:col-span-2 lg:gap-6">
            <x-card :title="__('assets.sections.details')">
                <x-slot:actions>@include('admin.assets._status', ['status' => $asset->status])</x-slot:actions>
                <x-detail-list>
                    <x-detail-item :label="__('assets.fields.asset_tag')"><span dir="ltr">{{ $asset->asset_tag }}</span></x-detail-item>
                    <x-detail-item :label="__('assets.fields.asset_category_id')">{{ $asset->category?->name }}</x-detail-item>
                    <x-detail-item :label="__('assets.fields.brand')"><span dir="ltr">{{ trim($asset->brand.' '.$asset->model) ?: '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('assets.fields.serial_number')"><span dir="ltr">{{ $asset->serial_number ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('assets.fields.condition')">@include('admin.assets._condition', ['condition' => $asset->condition])</x-detail-item>
                    <x-detail-item :label="__('assets.fields.branch_id')">{{ $asset->branch?->name ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('assets.fields.purchase_date')"><span dir="ltr">{{ $asset->purchase_date?->toDateString() ?? '—' }}</span></x-detail-item>
                    <x-detail-item :label="__('assets.fields.purchase_cost')">
                        @if ($asset->purchase_cost !== null)
                            @include('admin.payroll._money', ['amount' => $asset->purchase_cost, 'currency' => $currency])
                        @else
                            —
                        @endif
                    </x-detail-item>
                    <x-detail-item :label="__('assets.fields.supplier')">{{ $asset->supplier ?? '—' }}</x-detail-item>
                    <x-detail-item :label="__('assets.fields.warranty_expires_on')">
                        <span dir="ltr" @class(['text-feedback-danger-text' => $asset->warranty_expires_on?->isPast()])>{{ $asset->warranty_expires_on?->toDateString() ?? '—' }}</span>
                    </x-detail-item>
                </x-detail-list>
                @if ($asset->notes)
                    <p class="mt-4 whitespace-pre-line text-body-sm text-text-secondary">{{ $asset->notes }}</p>
                @endif
            </x-card>

            <x-card :title="__('assets.sections.custody_history')" padding="none" data-region="custody-history">
                <x-table :caption="__('assets.sections.custody_history')">
                    <x-slot:head>
                        <tr>
                            <x-table.th class="min-w-48">{{ __('assets.fields.employee') }}</x-table.th>
                            <x-table.th>{{ __('assets.fields.assigned_on') }}</x-table.th>
                            <x-table.th>{{ __('assets.fields.returned_on') }}</x-table.th>
                            <x-table.th>{{ __('assets.fields.status') }}</x-table.th>
                        </tr>
                    </x-slot:head>
                    @forelse ($asset->assignments as $row)
                        <x-table.row>
                            <x-table.td :label="__('assets.fields.employee')">
                                {{ $row->employee?->label() }}
                                @if ($row->issuer)
                                    <span class="block text-caption-md text-text-tertiary">{{ __('assets.show.issued_by', ['name' => $row->issuer->name]) }}</span>
                                @endif
                            </x-table.td>
                            <x-table.td :label="__('assets.fields.assigned_on')" class="whitespace-nowrap">
                                <span dir="ltr">{{ $row->assigned_on->toDateString() }}</span>
                                <span class="mt-1 block">@include('admin.assets._condition', ['condition' => $row->condition_on_issue])</span>
                            </x-table.td>
                            <x-table.td :label="__('assets.fields.returned_on')" class="whitespace-nowrap">
                                @if ($row->returned_on)
                                    <span dir="ltr">{{ $row->returned_on->toDateString() }}</span>
                                    <span class="mt-1 block">@include('admin.assets._condition', ['condition' => $row->condition_on_return])</span>
                                    @if ($row->return_notes)
                                        <span class="block text-caption-md text-text-tertiary">{{ $row->return_notes }}</span>
                                    @endif
                                @else
                                    <span class="text-text-tertiary">—</span>
                                @endif
                            </x-table.td>
                            <x-table.td :label="__('assets.fields.status')">
                                <x-badge :tone="$row->status->tone()">{{ $row->status->label() }}</x-badge>
                                @if ($row->isActive())
                                    <span class="block text-caption-md text-text-tertiary">{{ $row->acknowledged_at ? __('assets.assignments.acknowledged_on', ['date' => $row->acknowledged_at->toDateString()]) : __('assets.assignments.not_acknowledged') }}</span>
                                @endif
                            </x-table.td>
                        </x-table.row>
                    @empty
                        <x-table.empty :colspan="4" icon="users" :title="__('assets.show.no_custody')" />
                    @endforelse
                </x-table>
            </x-card>

            <x-card :title="__('assets.sections.events')" data-region="asset-events">
                @if ($asset->events->isEmpty())
                    <x-empty-state icon="history" :title="__('assets.show.no_events')" />
                @else
                    <ol class="relative flex flex-col gap-4 border-s border-border-default ps-6">
                        @foreach ($asset->events as $event)
                            <li class="relative">
                                <span class="absolute -start-[1.95rem] top-1 inline-flex size-4 rounded-full border-2 border-surface-default bg-action-primary-default" aria-hidden="true"></span>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-caption-md text-text-tertiary tabular-nums" dir="ltr">{{ $event->occurred_on->toDateString() }}</span>
                                    <x-badge :tone="$event->type->tone()">{{ $event->type->label() }}</x-badge>
                                    @if ($event->condition)
                                        @include('admin.assets._condition', ['condition' => $event->condition])
                                    @endif
                                </div>
                                @if ($event->employee)
                                    <p class="mt-1 text-body-sm text-text-primary">{{ $event->employee->label() }}</p>
                                @endif
                                @if ($event->note)
                                    <p class="mt-1 text-body-sm text-text-secondary">{{ $event->note }}</p>
                                @endif
                                @if ($event->creator)
                                    <p class="mt-1 text-caption-sm text-text-tertiary">{{ $event->creator->name }} · <span dir="ltr">{{ $event->created_at->format('Y-m-d H:i') }}</span></p>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-card>
        </div>

        <div class="flex flex-col gap-4 lg:gap-6">
            <x-card :title="__('assets.sections.custodian')" data-region="custodian" id="custodian">
                @if ($current)
                    <div class="flex items-center gap-3">
                        <x-avatar :name="$current->employee?->name" />
                        <div class="min-w-0">
                            @if ($current->employee && auth()->user()->can('view', $current->employee))
                                <a href="{{ route('admin.employees.tab', [$current->employee, 'assets']) }}" class="text-label-md text-text-link hover:underline">{{ $current->employee->label() }}</a>
                            @else
                                <p class="text-label-md text-text-primary">{{ $current->employee?->label() }}</p>
                            @endif
                            <p class="text-caption-md text-text-tertiary">{{ __('assets.show.since', ['date' => $current->assigned_on->toDateString()]) }}
                                @if ($current->expected_return_on) · {{ __('assets.portal.return_by', ['date' => $current->expected_return_on->toDateString()]) }}@endif</p>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($current->isAcknowledged())
                            <x-badge tone="success">{{ __('assets.assignments.acknowledged_on', ['date' => $current->acknowledged_at->toDateString()]) }}</x-badge>
                        @else
                            <x-badge tone="warning">{{ __('assets.show.awaiting_acknowledgement') }}</x-badge>
                        @endif
                        @if ($current->isOverdue())
                            <x-badge tone="danger">{{ __('assets.assignments.overdue') }}</x-badge>
                        @endif
                    </div>
                    @if ($current->handover_notes)
                        <p class="mt-3 text-body-sm text-text-secondary">{{ $current->handover_notes }}</p>
                    @endif
                    @can('update', $current)
                        <div class="mt-4 border-t border-border-subtle pt-4">
                            @include('admin.assets._return-forms', ['assignment' => $current])
                        </div>
                    @endcan
                @else
                    <p class="text-body-sm text-text-tertiary">{{ __('assets.show.not_assigned') }}</p>
                @endif
            </x-card>

            @if ($canAssign)
                <x-card :title="__('assets.show.assign')" :subtitle="__('assets.show.assign_help')" data-region="assign">
                    <form method="POST" action="{{ route('admin.asset-assignments.store') }}" class="flex flex-col gap-3" data-validate>
                        @csrf
                        <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                        <x-form.select name="employee_id" required :label="__('assets.fields.employee')">
                            <option value="">—</option>
                            @foreach ($employees as $employee)
                                @continue($employee->id === auth()->user()->employee_id)
                                <option value="{{ $employee->id }}" @selected((string) old('employee_id') === (string) $employee->id)>{{ $employee->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.input type="date" name="assigned_on" required :label="__('assets.fields.assigned_on')" :value="old('assigned_on', now()->toDateString())" :max="now()->toDateString()" />
                        <x-form.input type="date" name="expected_return_on" :label="__('assets.fields.expected_return_on')" :value="old('expected_return_on')" />
                        <x-form.select name="condition_on_issue" required :label="__('assets.fields.condition_on_issue')">
                            @foreach ($conditions as $condition)
                                <option value="{{ $condition->value }}" @selected(old('condition_on_issue', $asset->condition->value) === $condition->value)>{{ $condition->label() }}</option>
                            @endforeach
                        </x-form.select>
                        <x-form.textarea name="handover_notes" rows="2" maxlength="2000" :label="__('assets.fields.handover_notes')">{{ old('handover_notes') }}</x-form.textarea>
                        <x-button icon="user-plus">{{ __('assets.show.assign') }}</x-button>
                    </form>
                </x-card>
            @endif

            @if ($statusActions !== [])
                <x-card :title="__('assets.status_actions.title')" data-region="status-actions">
                    <div class="flex flex-col gap-4">
                        @foreach ($statusActions as $action)
                            @continue($action === 'retire' && ! $canRetire)
                            <form method="POST" action="{{ route('admin.assets.status', [$asset, $action]) }}" class="flex flex-col gap-2" data-validate
                                @if ($action === 'retire') data-confirm="{{ __('assets.status_actions.confirm_retire') }}" data-confirm-tone="danger" @endif>
                                @csrf
                                @if ($action === 'maintenance_done')
                                    <x-form.select name="condition" :id="'condition-'.$action" :label="__('assets.fields.condition')">
                                        @foreach ($conditions as $condition)
                                            <option value="{{ $condition->value }}" @selected($condition === App\Enums\AssetCondition::Good)>{{ $condition->label() }}</option>
                                        @endforeach
                                    </x-form.select>
                                @endif
                                <x-form.input name="note" :id="'note-'.$action" maxlength="2000" :label="__('assets.fields.note')"
                                    :required="in_array($action, ['lost', 'damaged', 'retire'], true)" />
                                <x-button size="sm" :variant="in_array($action, ['lost', 'retire', 'damaged'], true) ? 'danger' : 'secondary'">{{ __('assets.status_actions.'.$action) }}</x-button>
                            </form>
                        @endforeach
                    </div>
                </x-card>
            @endif
        </div>
    </div>
@endsection
