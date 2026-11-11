{{-- Status message on auth pages (no toast region there). Field errors are shown at each field. --}}
@if (session('status'))
    <x-alert tone="success" data-flash="success">{{ session('status') }}</x-alert>
@endif
