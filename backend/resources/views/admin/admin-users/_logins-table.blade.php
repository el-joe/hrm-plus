{{-- Variables: $logins (iterable of App\Models\LoginHistory), $paginator (optional: renders pagination). --}}
<x-table data-region="table" :caption="__('app.admin_users.login_history')">
    <x-slot:head>
        <tr>
            <x-table.th>{{ __('app.common.date') }}</x-table.th>
            <x-table.th>{{ __('app.admin_users.area') }}</x-table.th>
            <x-table.th>{{ __('app.admin_users.result') }}</x-table.th>
            <x-table.th>{{ __('app.admin_users.failure_reason') }}</x-table.th>
            <x-table.th>{{ __('app.common.ip_address') }}</x-table.th>
            <x-table.th>{{ __('app.common.user_agent') }}</x-table.th>
        </tr>
    </x-slot:head>

    @forelse ($logins as $login)
        <x-table.row>
            <x-table.td :label="__('app.common.date')" class="whitespace-nowrap">{{ $login->created_at?->format('Y-m-d H:i:s') }}</x-table.td>
            <x-table.td :label="__('app.admin_users.area')">{{ $login->area->label() }}</x-table.td>
            <x-table.td :label="__('app.admin_users.result')">
                <x-badge :tone="$login->successful ? 'success' : 'danger'" dot data-badge="login" data-successful="{{ $login->successful ? 'true' : 'false' }}">
                    {{ $login->successful ? __('app.admin_users.successful') : __('app.admin_users.failed') }}
                </x-badge>
            </x-table.td>
            <x-table.td :label="__('app.admin_users.failure_reason')" class="text-text-secondary">{{ $login->failure_reason }}</x-table.td>
            <x-table.td :label="__('app.common.ip_address')" class="font-mono text-body-sm" dir="ltr">{{ $login->ip_address }}</x-table.td>
            <x-table.td :label="__('app.common.user_agent')" class="max-w-80 truncate text-body-sm text-text-secondary" title="{{ $login->user_agent }}">{{ $login->user_agent }}</x-table.td>
        </x-table.row>
    @empty
        <x-table.empty :colspan="6" icon="history" />
    @endforelse

    @isset($paginator)
        <x-slot:footer><x-pagination :paginator="$paginator" /></x-slot:footer>
    @endisset
</x-table>
