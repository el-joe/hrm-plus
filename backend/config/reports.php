<?php

/*
|--------------------------------------------------------------------------
| Reports centre (Phase 8c)
|--------------------------------------------------------------------------
|
| queue_threshold  exports with more rows than this are prepared by the queue
|                  (App\Jobs\GenerateReportExport) and announced in the notification
|                  centre instead of being downloaded at once.
| pdf_max_rows     mPDF tables beyond this become unreasonably slow: larger PDF
|                  exports are refused (Excel / CSV have no limit).
| export_days      how long a queued export file stays downloadable.
|
*/

return [
    'queue_threshold' => [
        'xlsx' => (int) env('REPORTS_QUEUE_THRESHOLD_XLSX', 5000),
        'csv' => (int) env('REPORTS_QUEUE_THRESHOLD_CSV', 5000),
        'pdf' => (int) env('REPORTS_QUEUE_THRESHOLD_PDF', 400),
    ],

    'pdf_max_rows' => (int) env('REPORTS_PDF_MAX_ROWS', 3000),

    'export_days' => 7,
];
