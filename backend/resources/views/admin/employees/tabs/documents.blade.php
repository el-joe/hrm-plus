{{-- Documents tab. Variables: $employee, $documents. Files are private; links go through the authorised download route. --}}
@php($reminderDays = (int) setting('notifications.document_expiry_reminder_days'))
<x-card :title="__('employees.tabs.documents')" :subtitle="__('employees.documents.subtitle')" padding="none">
    @can('createDocuments', $employee)
        <x-slot:actions>
            <x-button type="button" size="sm" icon="upload" data-modal-open="upload-document">{{ __('employees.documents.upload') }}</x-button>
        </x-slot:actions>
    @endcan
    <x-table :caption="__('employees.tabs.documents')">
        <x-slot:head>
            <tr>
                <x-table.th class="min-w-56">{{ __('employees.fields.title') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.category') }}</x-table.th>
                <x-table.th>{{ __('employees.fields.expiry_date') }}</x-table.th>
                <x-table.th>{{ __('employees.documents.shared') }}</x-table.th>
                <x-table.th class="text-end"><span class="sr-only">{{ __('app.common.actions') }}</span></x-table.th>
            </tr>
        </x-slot:head>
        @forelse ($documents as $document)
            <x-table.row>
                <x-table.td :label="__('employees.fields.title')">
                    <a href="{{ route('admin.employees.documents.download', [$employee, $document]) }}" class="block text-label-md text-text-primary hover:text-text-link">{{ $document->title }}</a>
                    <span class="block text-caption-md text-text-tertiary">{{ $document->file_name }} · {{ Illuminate\Support\Number::fileSize($document->file_size) }} · {{ $document->created_at->toDateString() }}</span>
                </x-table.td>
                <x-table.td :label="__('employees.fields.category')">{{ $document->category->label() }}</x-table.td>
                <x-table.td :label="__('employees.fields.expiry_date')" class="whitespace-nowrap">
                    @if ($document->expiry_date)
                        <span dir="ltr" class="tabular-nums">{{ $document->expiry_date->toDateString() }}</span>
                        @if ($document->isExpired())
                            <x-badge tone="danger">{{ __('employees.documents.expired') }}</x-badge>
                        @elseif ($document->expiresWithin($reminderDays))
                            <x-badge tone="warning">{{ __('employees.documents.expiring') }}</x-badge>
                        @endif
                    @else
                        <span class="text-text-tertiary">—</span>
                    @endif
                </x-table.td>
                <x-table.td :label="__('employees.documents.shared')">
                    <x-badge :tone="$document->visible_to_employee ? 'info' : 'neutral'">{{ $document->visible_to_employee ? __('app.common.yes') : __('app.common.no') }}</x-badge>
                </x-table.td>
                <x-table.td>
                    <div class="flex items-center justify-end gap-1">
                        <x-icon-button icon="download" size="sm" :href="route('admin.employees.documents.download', [$employee, $document])" :label="__('employees.documents.download')" />
                        @can('updateDocuments', $employee)
                            <x-icon-button type="button" icon="pencil" size="sm" :label="__('app.actions.edit')" data-modal-open="edit-document-{{ $document->id }}" hidden data-js-only />
                        @endcan
                        @can('deleteDocuments', $employee)
                            <form method="POST" action="{{ route('admin.employees.documents.destroy', [$employee, $document]) }}"
                                data-confirm="{{ __('employees.documents.confirm_delete', ['title' => $document->title]) }}" data-confirm-label="{{ __('app.actions.delete') }}">
                                @csrf
                                @method('DELETE')
                                <x-icon-button type="submit" icon="trash" size="sm" variant="danger" :label="__('app.actions.delete')" />
                            </form>
                        @endcan
                    </div>
                </x-table.td>
            </x-table.row>
        @empty
            <x-table.empty :colspan="5" icon="file-text" :title="__('employees.documents.empty')" />
        @endforelse
    </x-table>
</x-card>

@can('createDocuments', $employee)
    <x-modal id="upload-document" :title="__('employees.documents.upload')">
        <form method="POST" action="{{ route('admin.employees.documents.store', $employee) }}" enctype="multipart/form-data" id="upload-document-form" class="flex flex-col gap-4" data-validate>
            @csrf
            @include('admin.employees.tabs._document-fields', ['document' => null, 'prefix' => 'new-doc'])
            <x-form.file-input name="file" id="new-doc-file" :label="__('employees.documents.file')" required
                :help="__('employees.documents.file_help', ['types' => implode(', ', (array) setting('documents.allowed_extensions')), 'kb' => setting('documents.max_upload_size_kb')])" />
        </form>
        <x-slot:footer>
            <x-button type="button" variant="secondary" data-modal-close>{{ __('app.actions.cancel') }}</x-button>
            <x-button form="upload-document-form" icon="upload">{{ __('employees.documents.upload') }}</x-button>
        </x-slot:footer>
    </x-modal>
@endcan

@can('updateDocuments', $employee)
    @foreach ($documents as $document)
        <x-modal :id="'edit-document-'.$document->id" :title="__('app.actions.edit').': '.$document->title">
            <form method="POST" action="{{ route('admin.employees.documents.update', [$employee, $document]) }}" id="edit-document-form-{{ $document->id }}" class="flex flex-col gap-4">
                @csrf
                @method('PUT')
                @include('admin.employees.tabs._document-fields', ['document' => $document, 'prefix' => 'doc-'.$document->id])
            </form>
            <x-slot:footer>
                <x-button type="button" variant="secondary" data-modal-close>{{ __('app.actions.cancel') }}</x-button>
                <x-button :form="'edit-document-form-'.$document->id">{{ __('app.actions.save') }}</x-button>
            </x-slot:footer>
        </x-modal>
    @endforeach
@endcan

@if ($errors->any())
    <x-alert tone="danger" :title="__('employees.documents.upload_failed')" data-region="document-errors">
        <ul class="mt-1 list-disc ps-5">
            @foreach ($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
