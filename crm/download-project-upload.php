<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/project-uploads.php';

crm_require_login();
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    http_response_code(404);
    exit;
}

$stmt = crm_db()->prepare('SELECT * FROM project_uploads WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$upload = $stmt->fetch();
if (!is_array($upload) || crm_find_lead((string) $upload['lead_id']) === null
    || (string) $upload['status'] !== 'ready'
    || (string) $upload['delete_at'] <= date('Y-m-d H:i:s')) {
    http_response_code(404);
    exit('Arquivo indisponível.');
}

$path = crm_project_upload_path((string) $upload['file_key']);
if (!is_file($path) || !is_readable($path)) {
    http_response_code(404);
    exit('Arquivo indisponível.');
}

if (empty($upload['first_download_at'])) {
    $now = date('Y-m-d H:i:s');
    $newDeadline = date('Y-m-d H:i:s', time() + 72 * 3600);
    $stmt = crm_db()->prepare(
        'UPDATE project_uploads
         SET first_download_at = :downloaded_at,
             delete_at = LEAST(delete_at, :deadline)
         WHERE id = :id AND first_download_at IS NULL AND status = "ready"'
    );
    $stmt->execute(['downloaded_at' => $now, 'deadline' => $newDeadline, 'id' => $id]);
}

$name = trim((string) $upload['original_name']);
$name = preg_replace('/[\x00-\x1F\x7F"\\]/u', '_', $name) ?? 'projeto.skp';
$fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $name) ?: 'projeto.skp';
header('Content-Type: application/octet-stream');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($name));
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, no-store');
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
$file = fopen($path, 'rb');
if ($file === false) {
    exit;
}
while (!feof($file) && !connection_aborted()) {
    echo fread($file, 1024 * 1024);
    flush();
}
fclose($file);
