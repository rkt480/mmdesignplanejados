<?php

declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

$env = static function (string $name, string $fallback = ''): string {
    $value = getenv($name);

    return $value === false || trim($value) === '' ? $fallback : trim($value);
};

return [
    'admin_user' => $env('MMPLANEJADOS_ADMIN_USER', 'admin'),
    // Gere com password_hash() antes de liberar o CRM para uso.
    'admin_password_hash' => $env('MMPLANEJADOS_ADMIN_PASSWORD_HASH'),
    'app_url' => $env('MMPLANEJADOS_APP_URL'),
    'company_name' => 'MM Planejados',
    'auto_migrate' => false,
    'settings_encryption_key' => $env('MMPLANEJADOS_SETTINGS_ENCRYPTION_KEY'),
    'db' => [
        'host' => $env('MMPLANEJADOS_DB_HOST', '127.0.0.1'),
        'port' => (int) $env('MMPLANEJADOS_DB_PORT', '3306'),
        'database' => $env('MMPLANEJADOS_DB_NAME', 'mm_planejados_crm'),
        'user' => $env('MMPLANEJADOS_DB_USER', 'root'),
        'password' => $env('MMPLANEJADOS_DB_PASSWORD'),
        'charset' => 'utf8mb4',
    ],
    'meta_whatsapp' => [
        'access_token' => $env('MMPLANEJADOS_META_WHATSAPP_ACCESS_TOKEN'),
        'verify_token' => $env('MMPLANEJADOS_META_WHATSAPP_VERIFY_TOKEN'),
        'app_secret' => $env('MMPLANEJADOS_META_WHATSAPP_APP_SECRET'),
    ],
    'pilot_status' => [
        'base_url' => $env('MMPLANEJADOS_PILOT_STATUS_BASE_URL', 'https://pilotstatus.com.br/v1'),
        'api_key' => $env('MMPLANEJADOS_PILOT_STATUS_API_KEY'),
        'webhook_secret' => $env('MMPLANEJADOS_PILOT_STATUS_WEBHOOK_SECRET'),
    ],
];
