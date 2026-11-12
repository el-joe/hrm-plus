<?php

/*
|--------------------------------------------------------------------------
| Reports centre (Phase 8c)
|--------------------------------------------------------------------------
|
| export_days  how long a queued export file stays downloadable.
|
| The queue thresholds and the PDF row limit are system settings of the
| "reports" group (config/settings.php, Settings → Reports).
|
*/

return [
    'export_days' => 7,
];
