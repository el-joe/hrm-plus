{{-- Login account card. Variables: $employee, $account (User|null). --}}
<x-card :title="__('employees.account.title')" data-region="account">
    @if ($account)
        <x-detail-list :columns="1">
            <x-detail-item :label="__('employees.account.email')"><span dir="ltr">{{ $account->email }}</span></x-detail-item>
            <x-detail-item :label="__('employees.account.type')">{{ $account->account_type->label() }}</x-detail-item>
            <x-detail-item :label="__('app.common.status')">
                <x-badge :tone="$account->isActive() ? 'success' : 'neutral'" dot>{{ $account->status->label() }}</x-badge>
            </x-detail-item>
            <x-detail-item :label="__('employees.account.last_login')">{{ $account->last_login_at?->diffForHumans() ?? __('employees.account.never') }}</x-detail-item>
        </x-detail-list>
        @if ($account->isEmployee())
            @can('manage', $employee)
                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.employees.account.invite', $employee) }}">
                        @csrf
                        <x-button variant="secondary" size="sm" icon="mail">{{ __('employees.account.send_invite') }}</x-button>
                    </form>
                    @if ($account->isActive())
                        <form method="POST" action="{{ route('admin.employees.account.status', $employee) }}" data-confirm="{{ __('employees.account.confirm_disable') }}" data-confirm-tone="warning">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="inactive">
                            <x-button variant="ghost" size="sm" icon="lock">{{ __('employees.account.disable') }}</x-button>
                        </form>
                    @elseif (! $employee->isTerminated())
                        <form method="POST" action="{{ route('admin.employees.account.status', $employee) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="active">
                            <x-button variant="ghost" size="sm" icon="check-circle">{{ __('employees.account.enable') }}</x-button>
                        </form>
                    @endif
                </div>
            @endcan
        @else
            <p class="mt-3 text-caption-md text-text-tertiary">{{ __('employees.account.admin_linked') }}</p>
        @endif
    @else
        <p class="text-body-sm text-text-tertiary">{{ __('employees.account.none') }}</p>
        @can('manage', $employee)
            <form method="POST" action="{{ route('admin.employees.account.store', $employee) }}" class="mt-4 flex flex-col gap-3" data-validate>
                @csrf
                <x-form.input name="account_email" type="email" :label="__('employees.account.email')" :value="old('account_email', $employee->email)" required dir="ltr" maxlength="255" />
                <x-form.select name="locale" :label="__('app.common.language')">
                    <option value="">{{ __('app.common.system_default') }}</option>
                    @foreach (array_keys(config('app.supported_locales')) as $locale)
                        <option value="{{ $locale }}">{{ __('app.locales.'.$locale) }}</option>
                    @endforeach
                </x-form.select>
                <x-button icon="user-plus" size="sm">{{ __('employees.account.create') }}</x-button>
                <p class="text-caption-md text-text-tertiary">{{ __('employees.account.create_help') }}</p>
            </form>
        @endcan
    @endif
</x-card>
