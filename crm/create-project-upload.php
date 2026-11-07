<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/project-uploads.php';

crm_require_login();
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit;
}
crm_require_valid_csrf();
if (crm_current_user_is_agency()) {
    crm_forbid('Este perfil não pode criar links de upload.');
}

$leadId = trim((string) ($_POST['lead_id'] ?? ''));
$lead = crm_find_lead($leadId);
if ($lead === null) {
    http_response_code(404);
    exit('Contato não encontrado.');
}

$user = crm_current_user();
crm_project_upload_create($leadId, (int) ($user['id'] ?? 0));
header('Location: whatsapp.php?lead=' . rawurlencode($leadId) . '&project_link=1');
exit;
