@extends('layouts.employee')

@section('title', __('app.my_documents.title'))

@php
    $sections = [
        'documents' => ['icon' => 'file-text', 'route' => fn (array $item): string => route('portal.documents.download', $item['id'])],
        'contracts' => ['icon' => 'briefcase', 'route' => fn (array $item): string => route('portal.documents.contracts.download', $item['id'])],
        'certificates' => ['icon' => 'award', 'route' => fn (array $item): string => route('portal.training.certificates.download', $item['id'])],
        'letters' => ['icon' => 'mail', 'route' => fn (array $item): string => route('portal.requests.attachments.download', [$item['request_id'], $item['id']])],
        'payslips' => ['icon' => 'coins', 'route' => fn (array $item): string => route('portal.payroll.pdf', $item['id'])],
    ];
    $total = array_sum($counts);
@endphp

@section('content')
    <p class="text-body-md text-text-secondary">{{ __('app.my_documents.intro') }}</p>

    @if ($total === 0)
        <x-card>
            <x-empty-state icon="file-text" :title="__('app.my_documents.empty')" :description="__('app.my_documents.empty_help')">
                <x-button variant="secondary" size="sm" icon="plus" :href="route('portal.requests.create')">{{ __('hr_requests.portal.new') }}</x-button>
            </x-empty-state>
        </x-card>
    @else
        <nav aria-label="{{ __('app.my_documents.title') }}" class="-mx-4 overflow-x-auto px-4 md:mx-0 md:px-0">
            <ul class="flex gap-2">
                @foreach ($sections as $type => $section)
                    <li class="shrink-0">
                        <a href="#docs-{{ $type }}" class="inline-flex h-10 items-center gap-2 rounded-full border border-border-default bg-surface-default px-4 text-label-md text-text-primary hover:bg-action-neutral-hover">
                            <x-icon :name="$section['icon']" size="sm" class="text-text-tertiary" />
                            {{ __('app.my_documents.sections.'.$type) }}
                            <x-badge tone="neutral"><x-number :value="$counts[$type]" /></x-badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        @foreach ($sections as $type => $section)
            <x-card :title="__('app.my_documents.sections.'.$type)" :subtitle="__('app.my_documents.help.'.$type)" padding="none" id="docs-{{ $type }}" class="scroll-mt-24" data-region="documents-{{ $type }}">
                <ul class="divide-y divide-border-subtle">
                    @forelse ($groups[$type] as $item)
                        <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-5" data-document="{{ $type }}-{{ $item['id'] }}">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="mt-0.5 inline-flex size-9 shrink-0 items-center justify-center rounded-md bg-surface-muted text-text-tertiary">
                                    <x-icon :name="$section['icon']" size="sm" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-label-md text-text-primary" dir="auto">{{ $item['title'] }}</p>
                                    <p class="text-caption-md text-text-tertiary">
                                        @if ($item['subtitle'])<span dir="auto">{{ $item['subtitle'] }}</span> · @endif
                                        <x-date :value="$item['date']" />
                                        @if ($item['expiry_date'])
                                            · {{ __('app.my_documents.expires') }} <x-date :value="$item['expiry_date']" />
                                        @endif
                                        @if (! empty($item['file_size']))
                                            · <x-number :value="max(1, (int) round($item['file_size'] / 1024))" /> {{ __('app.my_documents.kb') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex shrink-0 flex-wrap items-center gap-2">
                                @if ($item['is_expired'])
                                    <x-badge tone="danger">{{ __('app.my_documents.expired') }}</x-badge>
                                @elseif ($item['is_current'] ?? false)
                                    <x-badge tone="success">{{ __('app.my_documents.current') }}</x-badge>
                                @endif
                                <x-button variant="secondary" size="sm" icon="download" :href="$section['route']($item)">{{ __('app.my_documents.download') }}</x-button>
                            </div>
                        </li>
                    @empty
                        <li class="px-5 py-4 text-body-sm text-text-tertiary">{{ __('app.my_documents.none.'.$type) }}</li>
                    @endforelse
                </ul>
                @if ($type === 'payslips' && $groups[$type] !== [])
                    <x-slot:footer>
                        <x-button variant="secondary" size="sm" icon="coins" :href="route('portal.payroll')">{{ __('app.my_documents.all_payslips') }}</x-button>
                    </x-slot:footer>
                @endif
            </x-card>
        @endforeach
    @endif
@endsection
