<div class="grid2"><div>
<div class="status-tabs"><?php foreach (['confirmed' => __('Confermati'), 'pending' => __('In attesa'), 'unsubscribed' => __('Disiscritti')] as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= e(url('admin/newsletter?status=' . $k)) ?>"><?= e($l) ?><b><?= (int)$counts[$k] ?></b></a><?php endforeach ?>
  <a class="btn sec sm" style="margin-left:auto" href="<?= e(url('admin/newsletter/export')) ?>">⬇ CSV</a></div>
<div class="card" style="padding:0"><table><thead><tr><th>Email</th><th><?= e(__('Origine')) ?></th><th><?= e(__('Data')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['email']) ?></td><td class="muted"><?= e($r['source']) ?></td><td class="muted"><?= e(a_dt($r['created_at'])) ?></td><td class="right"><form method="post" action="<?= e(url('admin/newsletter/' . $r['id'] . '/delete')) ?>"><?= csrf_field() ?><button class="btn danger sm">✕</button></form></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="4"><?= a_empty('✉️', __('Nessun iscritto qui'), __('Il modulo di iscrizione è nel footer del negozio.')) ?></td></tr><?php endif ?></tbody></table></div></div>
<div><div class="card"><h2><?= e(__('Scrivi una campagna')) ?></h2>
<form method="post" action="<?= e(url('admin/newsletter/send')) ?>"><?= csrf_field() ?>
<?= a_input('subject', __('Oggetto'), '', 'text', ['required' => true]) ?>
<?= a_textarea('body', __('Testo (HTML)'), '', 10, __('Il link per annullare l\'iscrizione viene aggiunto in automatico.'), true) ?>
<div style="display:flex;gap:8px"><button class="btn sec" name="test" value="1"><?= e(__('Invia una prova a me')) ?></button><button class="btn" type="submit" onclick="return confirm('<?= e(__('Inviare la campagna a tutti gli iscritti confermati?')) ?>')">🚀 <?= e(__('Invia a %d iscritti', $counts['confirmed'])) ?></button></div></form>
<p class="muted" style="margin-top:12px;font-size:.85rem"><?= e(__('In coda: %d · Inviate: %d', $queued, $sent)) ?></p></div></div></div>
