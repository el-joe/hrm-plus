<x-table :caption="__('recruitment.pipeline.list')">
    <x-slot:head>
        <tr>
            <x-table.th class="min-w-56">{{ __('recruitment.fields.candidate') }}</x-table.th>
            <x-table.th>{{ __('recruitment.fields.stage') }}</x-table.th>
            <x-table.th>{{ __('recruitment.fields.applied_at') }}</x-table.th>
            <x-table.th>{{ __('recruitment.fields.average_score') }}</x-table.th>
            <x-table.th>{{ __('recruitment.fields.source') }}</x-table.th>
            @if ($canMove)
                <x-table.th class="min-w-64">{{ __('recruitment.pipeline.move') }}</x-table.th>
            @endif
        </tr>
    </x-slot:head>
    @php
        $rows = $columns->flatMap(fn ($column) => $column['applications']->map(fn ($application) => [$column['stage'], $application]));
    @endphp
    @forelse ($rows as [$stage, $application])
        <x-table.row>
            <x-table.td :label="__('recruitment.fields.candidate')">
                <a href="{{ route('admin.applications.show', $application) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $application->candidate->name }}</a>
                <span class="text-caption-md text-text-tertiary" dir="ltr">{{ $application->candidate->email }}</span>
            </x-table.td>
            <x-table.td :label="__('recruitment.fields.stage')">
                <span class="block">{{ $stage->name }}</span>
                @include('admin.recruitment._badge', ['value' => $application->status])
            </x-table.td>
            <x-table.td :label="__('recruitment.fields.applied_at')" class="whitespace-nowrap tabular-nums text-text-secondary"><span dir="ltr">{{ $application->applied_at->toDateString() }}</span></x-table.td>
            <x-table.td :label="__('recruitment.fields.average_score')" class="tabular-nums">{{ $application->average_score ?? '—' }}</x-table.td>
            <x-table.td :label="__('recruitment.fields.source')" class="text-text-secondary">{{ $application->source->label() }}</x-table.td>
            @if ($canMove)
                <x-table.td :label="__('recruitment.pipeline.move')">
                    @if ($application->isActive())
                        @include('admin.recruitment.vacancies._move-form')
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
            @endif
        </x-table.row>
    @empty
        <x-table.empty :colspan="$canMove ? 6 : 5" icon="users" :title="__('recruitment.pipeline.empty')" />
    @endforelse
</x-table>
