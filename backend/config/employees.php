<?php

/*
|--------------------------------------------------------------------------
| Employee management (Phase 3b)
|--------------------------------------------------------------------------
|
| Technical limits only. Business rules the client may change (number
| pattern, upload size and types, reminder lead times) are system settings
| (config/settings.php).
|
*/

return [

    // Private disk for photos, documents, contracts and import files (config/filesystems.php).
    'disk' => 'employee_files',

    // Profile photos.
    'photo_max_kb' => 2048,

    // Largest spreadsheet accepted by the import, in rows (excluding the header).
    'import_max_rows' => 2000,

    // How long an uploaded import file waits for confirmation after its dry run, in minutes.
    'import_ttl_minutes' => 60,

];
