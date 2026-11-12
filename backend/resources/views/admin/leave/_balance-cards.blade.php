{{-- Balance cards per leave type. Variables: $balances (Collection<LeaveBalance>), optional $compact (bool), $single (one column, for side cards). --}}
<div @class([
    'grid gap-3',
    'grid-cols-2 xl:grid-cols-3' => ($compact ?? false) && ! ($single ?? false),
    'sm:grid-cols-2 xl:grid-cols-3' => ! ($compact ?? false) && ! ($single ?? false),
]) data-region="leave-balances">
    @foreach ($balances as $balance)
        <div class="flex min-w-0 flex-col gap-2 rounded-lg border border-border-subtle bg-surface-default p-3 sm:p-4" data-leave-type="{{ $balance->type->code }}">
            <div class="flex items-center justify-between gap-2">
                <span class="inline-flex min-w-0 items-center gap-2 text-label-md text-text-primary">@include('admin.leave._swatch', ['color' => $balance->type->color]) <span class="truncate">{{ $balance->type->name }}</span></span>
                @unless ($balance->type->is_paid)
                    <x-badge tone="warning">{{ __('leave.types.unpaid') }}</x-badge>
                @endunless
            </div>
            @if ($balance->tracksBalance())
                <p class="text-numeric-lg text-text-primary tabular-nums" dir="ltr">{{ \App\Support\Formatter::digits($balance->available) }}</p>
                <p class="text-caption-md text-text-secondary">{{ __('leave.balances.available_days') }}</p>
                @unless ($compact ?? false)
                    <dl class="grid grid-cols-2 gap-x-3 gap-y-1 text-caption-md">
                        <dt class="text-text-tertiary">{{ __('leave.balances.entitlement') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->entitlement) }}</dd>
                        <dt class="text-text-tertiary">{{ __('leave.balances.carried_in') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->carriedIn) }}</dd>
                        <dt class="text-text-tertiary">{{ __('leave.balances.accrued') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->accrued) }}</dd>
                        <dt class="text-text-tertiary">{{ __('leave.balances.adjusted') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->adjusted) }}</dd>
                        <dt class="text-text-tertiary">{{ __('leave.balances.used') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->used) }}</dd>
                        <dt class="text-text-tertiary">{{ __('leave.balances.pending') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->pending) }}</dd>
                        @if (bccomp($balance->expired, '0', 2) !== 0)
                            <dt class="text-text-tertiary">{{ __('leave.balances.expired') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->expired) }}</dd>
                        @endif
                        @if (bccomp($balance->encashed, '0', 2) !== 0)
                            <dt class="text-text-tertiary">{{ __('leave.balances.encashed') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->encashed) }}</dd>
                        @endif
                        @if (bccomp($balance->carriedOut, '0', 2) !== 0)
                            <dt class="text-text-tertiary">{{ __('leave.balances.carried_out') }}</dt><dd class="text-end tabular-nums">{{ \App\Support\Formatter::digits($balance->carriedOut) }}</dd>
                        @endif
                    </dl>
                @else
                    <p class="text-caption-md text-text-tertiary">{{ __('leave.balances.used_pending', ['used' => \App\Support\Formatter::digits($balance->used), 'pending' => \App\Support\Formatter::digits($balance->pending)]) }}</p>
                @endunless
            @else
                <p class="text-numeric-lg text-text-primary tabular-nums" dir="ltr">{{ \App\Support\Formatter::digits($balance->taken) }}</p>
                <p class="text-caption-md text-text-secondary">{{ __('leave.balances.taken_no_balance') }}</p>
            @endif
        </div>
    @endforeach
    @if ($balances->isEmpty())
        <p class="text-body-sm text-text-tertiary">{{ __('leave.balances.none') }}</p>
    @endif
</div>
