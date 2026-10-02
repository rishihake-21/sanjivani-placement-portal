<?php

return [
    'documents' => [
        // Which filesystem disk holds the uploaded files. Must be a PRIVATE disk.
        // 'tpms_local' = server disk (development / single server)
        // 'tpms_s3'    = S3 / MinIO / any S3-compatible object storage (production)
        'disk' => env('TPMS_DOCUMENT_DISK', 'tpms_local'),

        'max_kb' => (int) env('TPMS_DOCUMENT_MAX_KB', 5120),

        // Checked against the file CONTENT (finfo), not the client-supplied name or header.
        'allowed_mimes' => ['application/pdf', 'image/jpeg', 'image/png'],
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    // Roles whose access to someone else's document is written to the audit log.
    'audit_document_views_for' => ['tp_coordinator', 'tpo', 'hod'],
];
