<div class="grid4" style="margin-bottom:20px">
  <div class="stat"><small><?= e(__('In attesa di promemoria')) ?></small><strong><?= (int)$stats['waiting'] ?></strong></div>
  <div class="stat"><small><?= e(__('Promemoria inviati')) ?></small><strong><?= (int)$stats['reminded'] ?></strong></div>
  <div class="stat"><small><?= e(__('Carrelli recuperati')) ?></small><strong><?= (int)$stats['recovered'] ?></strong></div>
  <div class="stat"><small><?= e(__('Vendite recuperate')) ?></small><strong><?= e(money($stats['recovered_value'])) ?></strong></div></div>
<div class="grid2"><div class="card" style="padding:0"><table><thead><tr><th>Email</th><th><?= e(__('Valore')) ?></th><th><?= e(__('Stato')) ?></th><th></th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td><?= e($r['email']) ?><div class="muted"><?= e(a_dt($r['updated_at'])) ?></div></td><td><?= e(money($r['total'])) ?></td>
<td><?= $r['recovered'] ? '<span class="pill ok">' . e(__('Recuperato')) . '</span>' : ($r['reminded_at'] ? '<span class="pill info">' . e(__('Promemoria inviato')) . '</span>' : '<span class="pill warn">' . e(__('In attesa')) . '</span>') ?></td>
<td class="right"><?php if (!$r['recovered'] && !$r['reminded_at']): ?><form method="post" action="<?= e(url('admin/abandoned/' . $r['id'] . '/remind')) ?>"><?= csrf_field() ?><button class="btn sec sm"><?= e(__('Invia ora')) ?></button></form><?php endif ?></td></tr><?php endforeach ?>
<?php if (!$rows): ?><tr><td colspan="4"><?= a_empty('🛒', __('Nessun carrello abbandonato'), __('Compaiono qui quando un cliente inserisce l\'email al checkout senza completare l\'ordine.')) ?></td></tr><?php endif ?></tbody></table></div>
<div class="card"><h2><?= e(__('Come funziona')) ?></h2><form method="post" action="<?= e(url('admin/abandoned/settings')) ?>"><?= csrf_field() ?>
<?= a_input('abandoned_delay', __('Invia il promemoria dopo (ore)'), setting('abandoned_delay', 2), 'number', ['min' => 1, 'max' => 72]) ?>
<?= a_select('abandoned_coupon', __('Codice sconto da regalare (facoltativo)'), ['' => '— ' . __('Nessuno') . ' —'] + $coupons, setting('abandoned_coupon', '')) ?>
<?= a_input('abandoned_subject', __('Oggetto email'), setting('abandoned_subject', ''), 'text', ['placeholder' => __('Hai dimenticato qualcosa nel carrello?')]) ?>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button></form></div></div>
