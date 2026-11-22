<?php

declare(strict_types=1);

/** Return a local CRM file only for URLs produced by the media storage helpers. */
function crm_whatsapp_download_media_path(string $url): ?string
{
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) {
        return null;
    }

    $path = rawurldecode((string) ($parts['path'] ?? ''));
    if (preg_match('~/(inbound-media|whatsapp-media)/([a-f0-9]{32}\.[a-z0-9]+)$~iD', $path, $match) !== 1) {
        return null;
    }

    $directory = realpath(dirname(__DIR__) . '/' . $match[1]);
    $file = $directory !== false ? realpath($directory . '/' . $match[2]) : false;
    if ($file === false || !str_starts_with($file, $directory . DIRECTORY_SEPARATOR) || !is_file($file) || !is_readable($file)) {
        return null;
    }

    return $file;
}

function crm_whatsapp_download_media_token(array $media): string
{
    $url = trim((string) ($media['url'] ?? ''));
    return $url !== '' && crm_whatsapp_download_media_path($url) !== null ? hash('sha256', $url) : '';
}

/** @return array{media: array<string, mixed>, path: string}|null */
function crm_whatsapp_find_download_media(array $lead, string $token): ?array
{
    if (preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
        return null;
    }

    if (preg_match_all('/\[crm_media\]([^\r\n]+)/u', (string) ($lead['notes'] ?? ''), $matches) < 1) {
        return null;
    }

    foreach ($matches[1] as $encoded) {
        $media = json_decode((string) $encoded, true);
        if (!is_array($media)) {
            continue;
        }
        $url = trim((string) ($media['url'] ?? ''));
        if ($url === '' || !hash_equals(hash('sha256', $url), $token)) {
            continue;
        }
        $path = crm_whatsapp_download_media_path($url);
        if ($path !== null) {
            return ['media' => $media, 'path' => $path];
        }
    }

    return null;
}

function crm_whatsapp_download_filename(array $media, string $path): string
{
    $name = str_replace('\\', '/', trim((string) ($media['filename'] ?? '')));
    $name = basename($name);
    $name = preg_replace('/[\x00-\x1f\x7f\/\\\\]+/u', '', $name) ?? '';
    return $name !== '' && $name !== '.' && $name !== '..' ? $name : basename($path);
}
