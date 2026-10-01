<?php $latest = $st['latest'] ?? []; $cur = $st['current'] ?? ''; ?>
<div class="grid2"><div>
<div class="card"><h2>⬆️ <?= e(__('Stato')) ?></h2>
  <p><?= e(__('Versione installata')) ?>: <strong><?= e($version) ?></strong><?= $cur ? ' · <code>' . e(substr($cur, 0, 7)) . '</code>' : '' ?>
  <br><span class="muted"><?= e(__('Metodo di aggiornamento')) ?>: <?= $st['method'] === 'git' ? 'Git' : e(__('download da GitHub (ZIP)')) ?><?= $applied ? ' · ' . e(__('ultimo aggiornamento: %s', a_dt($applied))) : '' ?></span></p>
  <?php if ($st['error']): ?><div class="alert error"><?= e($st['error']) ?></div><?php endif ?>
  <?php if ($st['available']): ?>
    <div class="alert info"><strong><?= e(__('Aggiornamento disponibile')) ?></strong><?= !empty($latest['sha']) ? ' · <code>' . e(substr($latest['sha'], 0, 7)) . '</code> ' . e($latest['message'] ?? '') : '' ?></div>
    <?php if ($st['changes']): ?><ul style="line-height:1.7;padding-left:18px"><?php foreach ($st['changes'] as $c): ?><li><code><?= e($c['sha']) ?></code> <?= e($c['message']) ?></li><?php endforeach ?></ul><?php endif ?>
    <?php if (!$writable): ?><div class="alert error"><?= e(__('I file del sito non sono scrivibili dal server web: sistema i permessi prima di aggiornare.')) ?></div>
    <?php elseif ($st['method'] === 'zip' && !$zip): ?><div class="alert error"><?= e(__('Per aggiornare senza Git serve l\'estensione PHP "zip".')) ?></div>
    <?php else: ?><form method="post" action="<?= e(url('admin/updates/apply')) ?>" data-busy="<?= e(__('Aggiornamento in corso…')) ?>"><?= csrf_field() ?><button class="btn" type="submit"><?= e(__('Aggiorna ora')) ?></button>
      <span class="muted"><?= e(__('Prima dell\'aggiornamento viene salvata una copia del database.')) ?></span></form><?php endif ?>
  <?php elseif (!$st['error']): ?><div class="alert success">✔ <?= e(__('Il negozio è aggiornato.')) ?></div><?php endif ?>
  <div style="display:flex;gap:10px;margin-top:14px;flex-wrap:wrap">
    <form method="post" action="<?= e(url('admin/updates/check')) ?>"><?= csrf_field() ?><button class="btn sec" type="submit"><?= e(__('Controlla adesso')) ?></button></form>
    <?php if ($canRollback): ?><form method="post" action="<?= e(url('admin/updates/rollback')) ?>" data-confirm="<?= e(__('Tornare alla versione precedente?')) ?>"><?= csrf_field() ?><button class="btn sec" type="submit"><?= e(__('Torna alla versione precedente')) ?></button></form><?php endif ?></div></div>
<div class="card"><h2><?= e(__('Come funziona')) ?></h2><ul style="line-height:1.8;padding-left:18px">
  <li><?= e(__('Ogni aggiornamento pubblicato su GitHub può essere installato con un clic: i tuoi dati, le immagini e la configurazione non vengono toccati.')) ?></li>
  <li><?= e(__('Il database si aggiorna da solo e la cache viene svuotata.')) ?></li>
  <li><?= e(__('Se hai modificato a mano i file del negozio, l\'aggiornamento con Git si ferma per non cancellare le tue modifiche.')) ?></li></ul></div></div>
<div class="card"><h2><?= e(__('Impostazioni')) ?></h2><form method="post" action="<?= e(url('admin/updates/settings')) ?>"><?= csrf_field() ?>
<?= a_input('update_repo', __('Repository GitHub'), setting('update_repo', 'Falco3205/AlienShop'), 'text', ['placeholder' => 'utente/repository']) ?>
<?= a_input('update_branch', __('Branch da seguire'), setting('update_branch', 'main')) ?>
<?= a_input('update_token', __('Token GitHub (solo repository privati)'), '', 'password', ['placeholder' => $hasToken ? '••••••••' : '', 'autocomplete' => 'new-password']) ?>
<?php if ($hasToken): ?><?= a_check('clear_token', __('Rimuovi il token salvato'), false) ?><?php endif ?>
<?= a_check('update_auto', __('Aggiorna automaticamente quando c\'è una novità su GitHub'), setting('update_auto', '0') === '1') ?>
<div class="field"><label><?= e(__('Aggiornamento immediato con webhook (facoltativo)')) ?></label>
<div class="help"><?= e(__('Su GitHub: Settings → Webhooks → Add webhook. Payload URL:')) ?> <code><?= e($hookUrl) ?></code> · Content type <code>application/json</code> · Secret: <code><?= e($secret) ?></code> · <?= e(__('evento "Just the push event". Richiede l\'aggiornamento automatico attivo.')) ?></div></div>
<?= a_check('regenerate', __('Genera un nuovo secret'), false) ?>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button></form></div></div>
