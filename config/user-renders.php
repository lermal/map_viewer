<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Submissions
    |--------------------------------------------------------------------------
    |
    | Accepted uploads per client IP per hour, and the total size of files waiting
    | for moderation above which new uploads are refused until the queue is reviewed.
    |
    */

    'uploads_per_hour' => (int) env('USER_RENDERS_UPLOADS_PER_HOUR', 5),

    'pending_quota_mb' => (int) env('USER_RENDERS_PENDING_QUOTA_MB', 500),

    // a form submitted faster than this after it was opened is treated as automated
    'min_form_seconds' => 3,

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    |
    | Uploads are decoded and re-encoded to WebP; anything larger is refused before decoding.
    | Lossless encoding needs about 30 bytes of memory per pixel (16 MP ≈ 470 MB, a few seconds),
    | so images are processed one at a time.
    |
    */

    'max_side' => 8192,

    'max_pixels' => 16_000_000,

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    */

    'rejected_retention_days' => 7,

];
