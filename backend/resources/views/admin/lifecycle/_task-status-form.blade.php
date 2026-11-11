{{--
    Done / skip / reopen buttons of an onboarding task or handover item.
    Variables: $action (PATCH URL), $item (status: TaskStatus)
--}}
<div class="flex flex-wrap gap-1.5">
    @if ($item->status === App\Enums\TaskStatus::Pending)
        <form method="POST" action="{{ $action }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="done">
            <x-button size="sm" icon="check">{{ __('onboarding.actions.done') }}</x-button>
        </form>
        <form method="POST" action="{{ $action }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="skipped">
            <x-button size="sm" variant="ghost">{{ __('onboarding.actions.skip') }}</x-button>
        </form>
    @else
        <form method="POST" action="{{ $action }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="pending">
            <x-button size="sm" variant="ghost" icon="history">{{ __('onboarding.actions.reopen') }}</x-button>
        </form>
    @endif
</div>
