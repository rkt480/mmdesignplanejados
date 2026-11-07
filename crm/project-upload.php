<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/project-uploads.php';

crm_send_security_headers();
header('Cache-Control: no-store');
$token = (string) ($_GET['token'] ?? '');
$upload = crm_project_upload_find_token($token);
$available = is_array($upload)
    && in_array((string) $upload['status'], ['open', 'uploading'], true)
    && (string) $upload['link_expires_at'] > date('Y-m-d H:i:s');
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="robots" content="noindex,nofollow" />
  <title>Enviar projeto | MM Design Planejados</title>
  <style>
    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 20px; background: #edf4f3; color: #17312e; font: 16px/1.5 system-ui, sans-serif; }
    main { width: min(100%, 520px); padding: 32px; border-radius: 22px; background: #fff; box-shadow: 0 14px 45px #153b3020; }
    .brand { color: #10776b; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; font-size: 13px; }
    h1 { margin: 12px 0 8px; font-size: clamp(27px, 6vw, 34px); line-height: 1.15; }
    p { margin: 0 0 20px; color: #536764; }
    label { display: block; margin-bottom: 8px; font-weight: 700; }
    input[type=file] { display: block; width: 100%; padding: 16px; border: 2px dashed #9ebbb5; border-radius: 12px; background: #f7fbfa; }
    button { width: 100%; margin-top: 18px; padding: 14px; border: 0; border-radius: 12px; background: #0c766b; color: #fff; font: inherit; font-weight: 800; cursor: pointer; }
    button:disabled { opacity: .55; cursor: wait; }
    progress { display: block; width: 100%; height: 16px; margin-top: 18px; accent-color: #0c766b; }
    small, #result { display: block; margin-top: 12px; color: #536764; }
    #result[data-error] { color: #ad3434; }
    #result[data-success] { color: #087464; font-weight: 700; }
  </style>
</head>
<body>
<main>
  <div class="brand">MM Design Planejados</div>
  <?php if (!$available): ?>
    <h1>Link indisponível</h1>
    <p>Este link expirou ou já foi utilizado. Peça um novo link à equipe que está atendendo você.</p>
  <?php else: ?>
    <h1>Envie seu projeto</h1>
    <p>Selecione o arquivo do projeto. Ao concluir, ele ficará disponível para a equipe no seu atendimento.</p>
    <form id="upload-form">
      <label for="project-file">Arquivo do projeto</label>
      <input id="project-file" type="file" accept=".skp,.zip,.pdf,.dwg,.dxf" required />
      <small>Formatos: SKP, ZIP, PDF, DWG ou DXF. Tamanho máximo: 1 GB. Mantenha esta página aberta até a confirmação.</small>
      <button type="submit">Enviar projeto</button>
      <progress id="progress" max="100" value="0" hidden></progress>
      <div id="result" role="status" aria-live="polite"></div>
    </form>
    <script>
      const token = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      const form = document.getElementById('upload-form');
      const input = document.getElementById('project-file');
      const button = form.querySelector('button');
      const progress = document.getElementById('progress');
      const result = document.getElementById('result');
      const endpoint = (action, offset) => `api/project-upload.php?token=${encodeURIComponent(token)}&action=${action}${offset === undefined ? '' : `&offset=${offset}`}`;
      async function request(action, body, offset) {
        const response = await fetch(endpoint(action, offset), {
          method: 'POST', body,
          headers: action === 'start' ? { 'Content-Type': 'application/json' } : {},
          cache: 'no-store'
        });
        const data = await response.json();
        if (!data.ok) throw new Error(data.error || 'Não foi possível concluir o envio.');
        return data;
      }
      form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const file = input.files[0];
        if (!file) return;
        if (file.size > 1024 * 1024 * 1024) {
          result.dataset.error = '';
          result.textContent = 'O arquivo deve ter até 1 GB.';
          return;
        }
        button.disabled = true;
        input.disabled = true;
        progress.hidden = false;
        result.removeAttribute('data-error');
        result.textContent = 'Preparando o envio...';
        try {
          const started = await request('start', JSON.stringify({ name: file.name, size: file.size }));
          let offset = Number(started.received) || 0;
          const chunkSize = 2 * 1024 * 1024;
          while (offset < file.size) {
            const chunk = file.slice(offset, Math.min(offset + chunkSize, file.size));
            let sent = false;
            for (let attempt = 0; attempt < 3 && !sent; attempt++) {
              try {
                const response = await request('chunk', chunk, offset);
                offset = Number(response.received);
                sent = true;
              } catch (error) {
                if (attempt === 2) throw error;
                await new Promise((resolve) => setTimeout(resolve, 700 * (attempt + 1)));
              }
            }
            progress.value = Math.round(offset / file.size * 100);
            result.textContent = `Enviando: ${progress.value}%`;
          }
          const finished = await request('finish');
          result.dataset.success = '';
          result.textContent = finished.message;
          form.reset();
          progress.value = 100;
          button.hidden = true;
        } catch (error) {
          result.dataset.error = '';
          result.textContent = `${error.message} Você pode tentar novamente com o mesmo arquivo.`;
          button.disabled = false;
          input.disabled = false;
        }
      });
    </script>
  <?php endif; ?>
</main>
</body>
</html>
