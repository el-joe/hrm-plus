{{-- Shared <head> content. Expects the page <title> in the "title" section. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<title>@yield('title') — {{ config('app.name') }}</title>
{{-- Theme and sidebar state are applied before the first paint so there is no flash.
     Stored choice wins; otherwise the OS preference (see resources/js/ui/theme.js). --}}
<script>
    (function () {
        var root = document.documentElement;
        try {
            var theme = localStorage.getItem('hrm-theme');
            var dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.dataset.theme = dark ? 'dark' : 'light';
            if (localStorage.getItem('hrm-sidebar') === 'collapsed') {
                root.dataset.sidebar = 'collapsed';
            }
        } catch (error) {
            root.dataset.theme = 'light';
        }
        root.classList.add('js');
    })();
</script>
{{-- Strings used by resources/js (fetch errors, client-side validation). Same
     messages as the server's lang/{locale}/validation.php. --}}
@php
    $clientStrings = [
        'request_failed' => __('app.ui.request_failed'),
        'network_error' => __('app.ui.network_error'),
        'fix_errors' => __('app.ui.fix_errors'),
        'confirm' => __('app.ui.confirm'),
        'validation' => [
            'required' => __('validation.required'),
            'email' => __('validation.email'),
            'confirmed' => __('validation.confirmed'),
            'integer' => __('validation.integer'),
            'date' => __('validation.date'),
            'min_string' => __('validation.min.string'),
            'max_string' => __('validation.max.string'),
            'min_numeric' => __('validation.min.numeric'),
            'max_numeric' => __('validation.max.numeric'),
        ],
    ];
@endphp
<script type="application/json" id="app-i18n">{!! json_encode($clientStrings, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
