<div class="status-tabs"><?php foreach (['pending' => __('Da approvare'), 'approved' => __('Pubblicate'), 'rejected' => __('Rifiutate')] as $k => $l): ?><a class="<?= $status === $k ? 'on' : '' ?>" href="<?= e(url('admin/reviews?status=' . $k)) ?>"><?= e($l) ?><b><?= (int)$counts[$k] ?></b></a><?php endforeach ?></div>
<div class="grid2"><div>
<?php if (!$rows): ?><div class="card"><?= a_empty('⭐', __('Nessuna recensione qui'), __('Le recensioni dei clienti appariranno in questa lista.')) ?></div><?php endif ?>
<?php foreach ($rows as $r): ?><div class="card">
  <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap"><div><strong style="color:#e8a317"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></strong> <strong><?= e($r['title']) ?></strong>
    <div class="muted"><?= e($r['author']) ?> <?= $r['verified'] ? '<span class="pill ok">' . e(__('acquisto verificato')) . '</span>' : '' ?> · <?= e(a_dt($r['created_at'])) ?> · <a href="<?= e(url('admin/products/' . $r['product_id'])) ?>"><?= e($r['product']) ?></a></div></div></div>
  <p style="margin:10px 0"><?= nl2br(e($r['body'])) ?></p>
  <form method="post" action="<?= e(url('admin/reviews/' . $r['id'])) ?>" style="display:flex;gap:6px"><?= csrf_field() ?><input type="hidden" name="back" value="<?= e($status) ?>">
    <?php if ($status !== 'approved'): ?><button class="btn sm" name="action" value="approve">✓ <?= e(__('Approva')) ?></button><?php endif ?>
    <?php if ($status !== 'rejected'): ?><button class="btn sec sm" name="action" value="reject"><?= e(__('Rifiuta')) ?></button><?php endif ?>
    <button class="btn danger sm" name="action" value="delete" onclick="return confirm('<?= e(__('Eliminare la recensione?')) ?>')">✕</button></form></div><?php endforeach ?>
</div><div><div class="card"><h2><?= e(__('Impostazioni')) ?></h2><form method="post" action="<?= e(url('admin/reviews/settings')) ?>"><?= csrf_field() ?>
  <?= a_check('reviews_auto', __('Pubblica subito senza moderazione'), setting('reviews_auto', '0') === '1') ?>
  <?= a_input('reviews_request_days', __('Chiedi una recensione dopo (giorni dalla spedizione)'), setting('reviews_request_days', 7), 'number', ['min' => 1, 'max' => 60]) ?>
  <button class="btn" type="submit"><?= e(__('Salva')) ?></button></form></div></div></div>
