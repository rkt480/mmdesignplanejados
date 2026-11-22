<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/storage.php';
require_once __DIR__ . '/lib/whatsapp-media-download.php';

crm_require_login();

function crm_chat_media_download_error(int $status, string $message): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    crm_chat_media_download_error(405, 'Método não permitido.');
}

$leadId = (string) ($_GET['lead_id'] ?? '');
$token = (string) ($_GET['media'] ?? '');
if ($leadId === '' || strlen($leadId) > 128 || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
    crm_chat_media_download_error(400, 'Referência de mídia inválida.');
}

$lead = crm_find_lead($leadId);
$download = is_array($lead) ? crm_whatsapp_find_download_media($lead, $token) : null;
if ($download === null) {
    crm_chat_media_download_error(404, 'Mídia não encontrada nesta conversa ou arquivo indisponível.');
}

$path = $download['path'];
$filename = crm_whatsapp_download_filename($download['media'], $path);
$mimeType = trim((string) ($download['media']['mime_type'] ?? ''));
if (preg_match('~^[a-z0-9][a-z0-9.+-]*/[a-z0-9][a-z0-9.+-]*$~iD', $mimeType) !== 1) {
    $mimeType = 'application/octet-stream';
}

header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="download"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
