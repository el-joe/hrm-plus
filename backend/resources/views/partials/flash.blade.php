{{--
    Validation summary shown above forms. Success messages (session "status")
    are shown as toasts by x-toast-region in the layout.
--}}
@if ($errors->any())
    <x-alert tone="danger" :title="__('app.ui.fix_errors')" data-flash="error">
        <ul class="list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
