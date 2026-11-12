@extends('layouts.employee', ['subtitle' => __('disciplinary.portal.subtitle')])

@section('title', __('disciplinary.portal.title'))

@section('content')
    <x-card padding="none" data-region="my-disciplinary">
        <ul class="divide-y divide-border-subtle">
            @forelse ($notices as $notice)
                <li class="flex flex-col gap-2 px-5 py-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <a href="{{ route('portal.disciplinary.show', $notice->id) }}" class="text-label-md text-text-link hover:underline">
                            {{ $notice->type->label() }} · <span dir="ltr">{{ $notice->disciplinaryCase->reference }}</span>
                        </a>
                        @if (! $notice->hasResponded())
                            <x-badge tone="warning" dot>{{ __('disciplinary.portal.awaiting') }}</x-badge>
                        @endif
                    </div>
                    <p class="text-body-sm text-text-secondary">
                        {{ $notice->disciplinaryCase->violationType->name }} · <x-date :value="$notice->issued_at" />
                        @if (! $notice->hasResponded() && $notice->response_due_on)
                            · {{ __('disciplinary.portal.response_due', ['date' => \App\Support\Formatter::date($notice->response_due_on)]) }}
                        @endif
                    </p>
                </li>
            @empty
                <li class="px-5 py-6"><x-empty-state icon="check-circle" :title="__('disciplinary.portal.empty')" /></li>
            @endforelse
        </ul>
        <x-slot:footer>
            <x-pagination :paginator="$notices" />
        </x-slot:footer>
    </x-card>
@endsection
