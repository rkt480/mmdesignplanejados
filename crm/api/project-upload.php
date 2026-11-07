<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/project-uploads.php';

crm_send_security_headers();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function project_upload_reply(int $status, array $data): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    project_upload_reply(405, ['ok' => false, 'error' => 'Método não permitido.']);
}

$token = (string) ($_GET['token'] ?? '');
$upload = crm_project_upload_find_token($token);
if ($upload === null || !crm_project_upload_is_current_link($upload)) {
    project_upload_reply(404, ['ok' => false, 'error' => 'Link inválido.']);
}

if ((string) $upload['link_expires_at'] <= date('Y-m-d H:i:s')) {
    project_upload_reply(410, ['ok' => false, 'error' => 'Este link expirou. Peça um novo link ao vendedor.']);
}

$action = (string) ($_GET['action'] ?? '');
$parentId = (int) $upload['id'];
$directory = crm_project_upload_directory();
if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
    project_upload_reply(500, ['ok' => false, 'error' => 'Não foi possível preparar o armazenamento.']);
}

if ($action === 'start') {
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    $name = trim(basename(str_replace('\\', '/', (string) ($input['name'] ?? ''))));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? '';
    $size = filter_var($input['size'] ?? null, FILTER_VALIDATE_INT);
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    if ($name === '' || strlen($name) > 255 || !in_array($extension, ['skp', 'zip', 'pdf', 'dwg', 'dxf'], true)) {
        project_upload_reply(422, ['ok' => false, 'error' => 'Selecione um projeto .skp, .zip, .pdf, .dwg ou .dxf.']);
    }
    if ($size === false || $size < 1 || $size > 1024 * 1024 * 1024) {
        project_upload_reply(422, ['ok' => false, 'error' => 'O arquivo deve ter até 1 GB.']);
    }
    if ((string) $upload['status'] === 'uploading'
        && (string) $upload['original_name'] === $name
        && (int) $upload['expected_size'] === $size) {
        project_upload_reply(200, ['ok' => true, 'upload_id' => $parentId, 'received' => (int) $upload['bytes_received']]);
    }

    $resume = crm_db()->prepare(
        'SELECT id, bytes_received FROM project_uploads
         WHERE parent_upload_id = :parent_id AND status = "uploading"
           AND original_name = :name AND expected_size = :size
         ORDER BY id DESC LIMIT 1'
    );
    $resume->execute(['parent_id' => $parentId, 'name' => $name, 'size' => $size]);
    $inProgress = $resume->fetch();
    if (is_array($inProgress)) {
        project_upload_reply(200, ['ok' => true, 'upload_id' => (int) $inProgress['id'], 'received' => (int) $inProgress['bytes_received']]);
    }

    $key = bin2hex(random_bytes(32));
    $stmt = crm_db()->prepare(
        'INSERT INTO project_uploads
         (lead_id, token, parent_upload_id, created_by, status, original_name, file_key,
          expected_size, bytes_received, created_at, link_expires_at)
         VALUES (:lead_id, :token, :parent_id, :created_by, "uploading", :name,
                 :file_key, :size, 0, :created_at, :link_expires_at)'
    );
    $stmt->execute([
        'lead_id' => $upload['lead_id'],
        'token' => bin2hex(random_bytes(32)),
        'parent_id' => $parentId,
        'created_by' => $upload['created_by'],
        'name' => $name,
        'file_key' => $key,
        'size' => $size,
        'created_at' => date('Y-m-d H:i:s'),
        'link_expires_at' => $upload['link_expires_at'],
    ]);
    project_upload_reply(200, ['ok' => true, 'upload_id' => (int) crm_db()->lastInsertId(), 'received' => 0]);
}

$uploadId = filter_var($_GET['upload_id'] ?? null, FILTER_VALIDATE_INT);
if ($uploadId === false || $uploadId < 1) {
    project_upload_reply(422, ['ok' => false, 'error' => 'Arquivo não identificado.']);
}
$stmt = crm_db()->prepare(
    'SELECT * FROM project_uploads WHERE id = :id
     AND (parent_upload_id = :parent_id OR id = :legacy_parent_id) LIMIT 1'
);
$stmt->execute(['id' => $uploadId, 'parent_id' => $parentId, 'legacy_parent_id' => $parentId]);
$fileUpload = $stmt->fetch();
if (!is_array($fileUpload)) {
    project_upload_reply(404, ['ok' => false, 'error' => 'Arquivo não encontrado neste link.']);
}

if ($action === 'chunk') {
    $offset = filter_var($_GET['offset'] ?? null, FILTER_VALIDATE_INT);
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($offset === false || $offset < 0 || $length < 1 || $length > 2 * 1024 * 1024) {
        project_upload_reply(422, ['ok' => false, 'error' => 'Bloco de arquivo inválido.']);
    }
    $key = (string) ($fileUpload['file_key'] ?? '');
    if ((string) $fileUpload['status'] !== 'uploading' || $key === '') {
        project_upload_reply(409, ['ok' => false, 'error' => 'O envio não foi iniciado.']);
    }
    $path = crm_project_upload_path($key);
    $file = @fopen($path, 'c+b');
    if ($file === false || !flock($file, LOCK_EX)) {
        project_upload_reply(500, ['ok' => false, 'error' => 'Não foi possível gravar o arquivo.']);
    }
    try {
        $freshStmt = crm_db()->prepare('SELECT * FROM project_uploads WHERE id = :id LIMIT 1');
        $freshStmt->execute(['id' => $uploadId]);
        $fresh = $freshStmt->fetch();
        if (!is_array($fresh) || (string) $fresh['status'] !== 'uploading') {
            project_upload_reply(409, ['ok' => false, 'error' => 'Este envio já foi concluído ou expirou.']);
        }
        $received = (int) ($fresh['bytes_received'] ?? 0);
        if ($offset !== $received) {
            project_upload_reply(409, ['ok' => false, 'error' => 'Posição do arquivo divergente.', 'received' => $received]);
        }
        $data = file_get_contents('php://input');
        if (!is_string($data) || strlen($data) !== $length || $received + $length > (int) $fresh['expected_size']) {
            project_upload_reply(422, ['ok' => false, 'error' => 'Bloco incompleto ou maior que o arquivo esperado.']);
        }
        ftruncate($file, $received);
        fseek($file, $received);
        $written = 0;
        while ($written < $length) {
            $part = fwrite($file, substr($data, $written));
            if ($part === false || $part === 0) {
                project_upload_reply(500, ['ok' => false, 'error' => 'Falha ao gravar o arquivo.']);
            }
            $written += $part;
        }
        fflush($file);
        $stmt = crm_db()->prepare('UPDATE project_uploads SET bytes_received = :received WHERE id = :id AND status = "uploading"');
        $stmt->execute(['received' => $received + $length, 'id' => $fresh['id']]);
        project_upload_reply(200, ['ok' => true, 'received' => $received + $length]);
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

if ($action === 'finish') {
    $freshStmt = crm_db()->prepare('SELECT * FROM project_uploads WHERE id = :id LIMIT 1');
    $freshStmt->execute(['id' => $uploadId]);
    $fileUpload = $freshStmt->fetch();
    if (!is_array($fileUpload) || (string) $fileUpload['status'] !== 'uploading'
        || (int) $fileUpload['bytes_received'] !== (int) $fileUpload['expected_size']) {
        project_upload_reply(409, ['ok' => false, 'error' => 'O envio ainda não terminou.']);
    }
    $path = crm_project_upload_path((string) $fileUpload['file_key']);
    if (!is_file($path) || filesize($path) !== (int) $fileUpload['expected_size']) {
        project_upload_reply(409, ['ok' => false, 'error' => 'O arquivo recebido está incompleto.']);
    }
    $mime = (string) (mime_content_type($path) ?: 'application/octet-stream');
    $now = date('Y-m-d H:i:s');
    $deleteAt = date('Y-m-d H:i:s', time() + 7 * 86400);
    $stmt = crm_db()->prepare(
        'UPDATE project_uploads SET status = "ready", mime_type = :mime,
         uploaded_at = :uploaded_at, delete_at = :delete_at
         WHERE id = :id AND status = "uploading"'
    );
    $stmt->execute(['mime' => $mime, 'uploaded_at' => $now, 'delete_at' => $deleteAt, 'id' => $uploadId]);
    if ($stmt->rowCount() !== 1) {
        project_upload_reply(409, ['ok' => false, 'error' => 'O envio já foi concluído.']);
    }
    crm_append_lead_note((string) $fileUpload['lead_id'], 'Observação do CRM em ' . date('d/m/Y H:i:s')
        . ":\nProjeto recebido pelo link de upload: " . (string) $fileUpload['original_name']);
    project_upload_reply(200, ['ok' => true, 'message' => 'Projeto enviado. A equipe já pode acessá-lo no CRM.']);
}

project_upload_reply(400, ['ok' => false, 'error' => 'Ação inválida.']);
