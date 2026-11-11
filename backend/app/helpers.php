<?php

use App\Services\Settings\SettingsService;

if (! function_exists('setting')) {
    /**
     * Read a system setting by its dotted "group.name" key (config/settings.php).
     */
    function setting(string $key): mixed
    {
        return app(SettingsService::class)->get($key);
    }
}
