<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/lib/project-uploads.php';

$lock = @fopen(__DIR__ . '/data/project-upload-cleanup.lock', 'c');
if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
    exit;
}

try {
    $removed = crm_project_upload_cleanup();
    echo "Projetos expirados removidos: {$removed}\n";
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
