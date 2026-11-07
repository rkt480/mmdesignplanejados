<?php

declare(strict_types=1);

require_once __DIR__ . '/storage.php';

function crm_project_upload_directory(): string
{
    return dirname(__DIR__) . '/data/project-uploads';
}

function crm_project_upload_path(string $key): string
{
    if (preg_match('/^[a-f0-9]{64}$/D', $key) !== 1) {
        throw new InvalidArgumentException('Identificador de arquivo inválido.');
    }

    return crm_project_upload_directory() . '/' . $key . '.bin';
}

function crm_project_upload_app_url(): string
{
    $configured = trim((string) (crm_config()['app_url'] ?? ''));
    if (filter_var($configured, FILTER_VALIDATE_URL)) {
        return rtrim($configured, '/');
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || preg_match('/^[a-zA-Z0-9.:-]+$/D', $host) !== 1) {
        return '';
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/crm/whatsapp.php'));
    $root = preg_match('#^(.*?/crm)(?:/|$)#', $script, $match) === 1
        ? $match[1]
        : rtrim(dirname($script), '/');
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    return ($https ? 'https://' : 'http://') . $host . $root;
}

function crm_project_upload_link(array $upload): string
{
    return crm_project_upload_app_url() . '/p.php?t=' . rawurlencode(substr((string) $upload['token'], 0, 24));
}

function crm_project_upload_create(string $leadId, int $userId): array
{
    $token = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    $now = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', time() + 7 * 86400);
    $stmt = crm_db()->prepare(
        'INSERT INTO project_uploads (lead_id, token, created_by, status, created_at, link_expires_at)
         VALUES (:lead_id, :token, :created_by, "open", :created_at, :link_expires_at)'
    );
    $stmt->execute([
        'lead_id' => $leadId,
        'token' => $token,
        'created_by' => $userId,
        'created_at' => $now,
        'link_expires_at' => $expires,
    ]);

    return ['id' => (int) crm_db()->lastInsertId(), 'token' => $token, 'link_expires_at' => $expires];
}

function crm_project_upload_find_token(string $token): ?array
{
    if (preg_match('/^(?:[a-f0-9]{64}|[A-Za-z0-9_-]{24})$/D', $token) !== 1) {
        return null;
    }

    $stmt = crm_db()->prepare(strlen($token) === 24
        ? 'SELECT * FROM project_uploads WHERE LEFT(BINARY token, 24) = :token LIMIT 2'
        : 'SELECT * FROM project_uploads WHERE BINARY token = :token LIMIT 2');
    $stmt->execute(['token' => $token]);
    $rows = $stmt->fetchAll();
    return count($rows) === 1 ? $rows[0] : null;
}

function crm_project_uploads_for_lead(string $leadId): array
{
    $stmt = crm_db()->prepare(
        'SELECT * FROM project_uploads WHERE lead_id = :lead_id
         ORDER BY (parent_upload_id IS NULL AND link_expires_at > NOW()) DESC, created_at DESC LIMIT 100'
    );
    $stmt->execute(['lead_id' => $leadId]);
    return $stmt->fetchAll();
}

function crm_project_upload_cleanup(): int
{
    $now = date('Y-m-d H:i:s');
    $stmt = crm_db()->prepare(
        'SELECT id, file_key FROM project_uploads
         WHERE (status IN ("open", "uploading") AND link_expires_at <= :now_link)
            OR (status = "ready" AND delete_at <= :now_file)
         ORDER BY id ASC LIMIT 100'
    );
    $stmt->execute(['now_link' => $now, 'now_file' => $now]);
    $rows = $stmt->fetchAll();
    $deleted = 0;

    foreach ($rows as $row) {
        $key = (string) ($row['file_key'] ?? '');
        if ($key !== '') {
            $path = crm_project_upload_path($key);
            if (is_file($path) && !@unlink($path)) {
                error_log('Não foi possível remover o projeto expirado #' . (int) $row['id']);
                continue;
            }
            @unlink($path . '.lock');
        }

        $update = crm_db()->prepare('UPDATE project_uploads SET status = "deleted", deleted_at = :deleted_at WHERE id = :id');
        $update->execute(['deleted_at' => $now, 'id' => (int) $row['id']]);
        $deleted++;
    }

    return $deleted;
}

function crm_project_upload_cleanup_if_due(): void
{
    $marker = dirname(__DIR__) . '/data/project-upload-cleanup.check';
    $handle = @fopen($marker, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
        return;
    }
    try {
        $lastRun = (int) (fstat($handle)['mtime'] ?? 0);
        if (time() - $lastRun < 3600) {
            return;
        }
        ftruncate($handle, 0);
        fwrite($handle, (string) time());
        fflush($handle);
        crm_project_upload_cleanup();
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
