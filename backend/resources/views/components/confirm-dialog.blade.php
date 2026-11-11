{{--
    The one confirmation dialog of a layout (440 wide, padding 24; tones danger, warning, info).
    Driven by resources/js/ui/confirm.js: forms or buttons with data-confirm="message"
    (optional data-confirm-title, data-confirm-label, data-confirm-tone) open it before submitting.
--}}
<dialog id="confirm-dialog" data-modal data-confirm-dialog class="max-w-110" aria-labelledby="confirm-dialog-title" aria-describedby="confirm-dialog-message">
    <form method="dialog" class="flex w-full flex-col gap-4 rounded-xl bg-surface-raised p-6 text-text-primary shadow-xl">
        <div class="flex items-start gap-4">
            <span class="inline-flex size-10 shrink-0 items-center justify-center rounded-full" data-confirm-icon-wrap>
                <x-icon name="alert-triangle" data-confirm-icon />
            </span>
            <div class="min-w-0">
                <h2 id="confirm-dialog-title" class="text-h4" data-confirm-title>{{ __('app.ui.confirm_title') }}</h2>
                <p id="confirm-dialog-message" class="mt-1 text-body-md text-text-secondary" data-confirm-message></p>
            </div>
        </div>
        <div class="flex flex-wrap justify-end gap-3">
            <x-button variant="secondary" value="cancel" data-confirm-cancel>{{ __('app.actions.cancel') }}</x-button>
            <x-button variant="danger" value="confirm" data-confirm-accept>{{ __('app.ui.confirm') }}</x-button>
        </div>
    </form>
</dialog>
