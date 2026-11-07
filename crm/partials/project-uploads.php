<?php
require_once dirname(__DIR__) . '/lib/project-uploads.php';
$projectUploads = crm_project_uploads_for_lead((string) $activeLead['id']);
$nowProject = date('Y-m-d H:i:s');
?>
<section class="wa-project-uploads" aria-label="Projetos do cliente">
  <div class="wa-project-heading">
    <div><h3>Projetos para orçamento</h3><small>Arquivos deste cliente</small></div>
    <?php if (!$isAgency): ?>
      <form method="post" action="create-project-upload.php">
        <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>" />
        <input type="hidden" name="lead_id" value="<?= htmlspecialchars((string) $activeLead['id'], ENT_QUOTES, 'UTF-8') ?>" />
        <button type="submit">Gerar link de envio</button>
      </form>
    <?php endif; ?>
  </div>
  <?php foreach ($projectUploads as $project): ?>
    <?php
      $status = (string) $project['status'];
      $linkOpen = in_array($status, ['open', 'uploading'], true) && (string) $project['link_expires_at'] > $nowProject;
      $fileReady = $status === 'ready' && (string) $project['delete_at'] > $nowProject;
    ?>
    <div class="wa-project-item">
      <?php if ($linkOpen): ?>
        <strong><?= $status === 'uploading' ? 'Cliente enviando o projeto' : 'Link pronto para o cliente' ?></strong>
        <small>Válido até <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $project['link_expires_at'])), ENT_QUOTES, 'UTF-8') ?></small>
        <div class="wa-project-link-row">
          <input type="text" readonly value="<?= htmlspecialchars(crm_project_upload_link($project), ENT_QUOTES, 'UTF-8') ?>" aria-label="Link de envio do projeto" />
          <button type="button" data-project-copy>Copiar</button>
        </div>
      <?php elseif ($fileReady): ?>
        <strong><?= htmlspecialchars((string) $project['original_name'], ENT_QUOTES, 'UTF-8') ?></strong>
        <small><?= number_format((int) $project['expected_size'] / 1024 / 1024, 1, ',', '.') ?> MB · Excluído em <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) $project['delete_at'])), ENT_QUOTES, 'UTF-8') ?></small>
        <a href="download-project-upload.php?id=<?= (int) $project['id'] ?>">Baixar projeto</a>
      <?php else: ?>
        <strong><?= htmlspecialchars((string) ($project['original_name'] ?: 'Link de envio'), ENT_QUOTES, 'UTF-8') ?></strong>
        <small><?= $status === 'deleted' || $status === 'ready' ? 'Arquivo removido após o prazo de armazenamento.' : 'Link expirado sem envio.' ?></small>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  <?php if ($projectUploads === []): ?><small>Nenhum projeto enviado por este link ainda.</small><?php endif; ?>
</section>
