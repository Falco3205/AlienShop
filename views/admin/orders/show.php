<?php $cur = $o['currency']; $f = static fn($c) => Alien\Core\Money::format((int)$c, $cur); $s = $o['shipping_address']; ?>
<?php $flow = ['pending' => 0, 'processing' => 1, 'shipped' => 2, 'completed' => 3]; $pos = $flow[$o['status']] ?? -1; ?>
<?php if ($pos >= 0): ?><div class="steps-flow"><?php foreach ([__('Ricevuto'), __('In lavorazione'), __('Spedito'), __('Completato')] as $i => $l): ?><span class="<?= $i <= $pos ? 'on' : '' ?>"><?= e($l) ?></span><?php endforeach ?></div><?php else: ?><p><?= a_status($o['status']) ?></p><?php endif ?>
<?php if (in_array($o['status'], ['pending', 'processing'], true)): ?><div class="card" style="background:#f5f2ff"><h2><?= e(__('Prossimo passo: spedisci l\'ordine')) ?></h2>
  <?php if ($o['payment_status'] !== 'paid'): ?><p class="muted"><?= e(__('Il pagamento non è ancora stato ricevuto.')) ?> <?= e($o['payment_method']) ?></p><?php endif ?>
  <form method="post" style="display:flex;gap:8px;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="ship"><input name="tracking" placeholder="<?= e(__('Codice tracking (opzionale)')) ?>" style="max-width:280px"><button class="btn" type="submit">🚚 <?= e(__('Segna come spedito e avvisa il cliente')) ?></button></form></div>
<?php elseif ($o['status'] === 'shipped'): ?><div class="card" style="background:#f5f2ff"><form method="post" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><?= csrf_field() ?><input type="hidden" name="action" value="complete"><span><?= e(__('Ordine spedito. Quando il cliente lo riceve:')) ?></span><button class="btn" type="submit">✓ <?= e(__('Segna come completato')) ?></button></form></div><?php endif ?>
<div class="grid2"><div>
  <div class="card"><h2><?= e(__('Articoli')) ?></h2><table><tbody>
  <?php foreach ($o['items'] as $it): ?><tr><td><?= e($it['name']) ?><?php if ($it['variant_label']): ?> <span class="muted">(<?= e($it['variant_label']) ?>)</span><?php endif ?><div class="muted"><?= e($it['sku']) ?></div></td><td><?= e($f($it['price'])) ?> × <?= (int)$it['qty'] ?></td><td class="right"><?= e($f($it['total'])) ?></td></tr><?php endforeach ?>
  <tr><td colspan="2" class="right muted"><?= e(__('Subtotale')) ?></td><td class="right"><?= e($f($o['subtotal'])) ?></td></tr>
  <?php if ($o['discount']): ?><tr><td colspan="2" class="right muted"><?= e(__('Sconto')) ?> <?= e($o['coupon_code']) ?></td><td class="right">-<?= e($f($o['discount'])) ?></td></tr><?php endif ?>
  <tr><td colspan="2" class="right muted"><?= e(__('Spedizione')) ?> (<?= e($o['shipping_method']) ?>)</td><td class="right"><?= e($f($o['shipping'])) ?></td></tr>
  <?php if ($o['tax']): ?><tr><td colspan="2" class="right muted"><?= e(__('Imposte incluse/aggiunte')) ?></td><td class="right"><?= e($f($o['tax'])) ?></td></tr><?php endif ?>
  <tr><td colspan="2" class="right"><strong><?= e(__('Totale')) ?></strong></td><td class="right"><strong><?= e($f($o['total'])) ?></strong></td></tr></tbody></table></div>
  <div class="card"><h2><?= e(__('Cronologia')) ?></h2>
    <form method="post" style="display:flex;gap:8px;margin-bottom:12px"><?= csrf_field() ?><input type="hidden" name="action" value="note"><input name="message" placeholder="<?= e(__('Aggiungi una nota interna')) ?>"><button class="btn sec" type="submit"><?= e(__('Aggiungi')) ?></button></form>
    <table><tbody><?php foreach ($o['events'] as $ev): ?><tr><td class="nowrap muted"><?= e(a_dt($ev['created_at'])) ?></td><td><?= e($ev['message']) ?></td></tr><?php endforeach ?></tbody></table></div>
</div><div>
  <div class="card"><h2><?= e(__('Stato')) ?></h2>
    <p><?= a_status($o['status']) ?> <?= a_status($o['payment_status']) ?> <span class="muted"><?= e($o['payment_method']) ?><?= $o['payment_ref'] ? ' · ' . e($o['payment_ref']) : '' ?></span></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="status">
      <?= a_select('status', __('Cambia stato'), array_map('__', Alien\Services\Orders::STATUSES), $o['status']) ?>
      <?= a_input('tracking', __('Codice tracking'), $o['tracking']) ?>
      <button class="btn" type="submit"><?= e(__('Aggiorna')) ?></button></form>
    <p style="margin-top:10px"><a class="btn sec sm" href="<?= e(url('admin/orders/' . $o['id'] . '/print')) ?>" target="_blank"><?= e(__('Stampa / packing slip')) ?></a></p>
    <?php if (Alien\Services\Modules::on('invoices')): ?><div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:8px"><a class="btn sec sm" href="<?= e(url('admin/orders/' . $o['id'] . '/invoice')) ?>">🧾 <?= e($o['invoice_number'] !== '' ? __('Scarica') . ' ' . $o['invoice_number'] : __('Genera PDF')) ?></a>
      <form method="post" action="<?= e(url('admin/orders/' . $o['id'] . '/invoice/send')) ?>"><?= csrf_field() ?><button class="btn sec sm"><?= e(__('Invia al cliente')) ?></button></form></div><?php endif ?>
    <?php if ($o['payment_status'] === 'paid' && !in_array($o['status'], ['refunded'], true) && in_array($o['payment_method'], ['stripe', 'paypal', 'mollie'], true)): ?><form method="post" data-confirm="<?= e(__('Rimborsare l\'intero importo al cliente?')) ?>" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="refund"><button class="btn danger sm" type="submit"><?= e(__('Rimborsa tramite gateway')) ?></button></form><?php endif ?>
    <?php if ($o['payment_status'] !== 'paid'): ?><form method="post" style="margin-top:10px"><?= csrf_field() ?><input type="hidden" name="action" value="paid"><button class="btn sec sm" type="submit"><?= e(__('Segna come pagato')) ?></button></form><?php endif ?></div>
  <div class="card"><h2><?= e(__('Cliente')) ?></h2><p><a href="mailto:<?= e($o['email']) ?>"><?= e($o['email']) ?></a><br><?= e($s['phone'] ?? '') ?></p>
    <h2><?= e(__('Spedizione')) ?></h2><p><?= e($s['name'] ?? '') ?><br><?= e($s['address'] ?? '') ?><br><?= e(($s['zip'] ?? '') . ' ' . ($s['city'] ?? '') . ' ' . ($s['state'] ?? '')) ?><br><?= e($s['country'] ?? '') ?></p>
    <?php if ($o['note']): ?><h2><?= e(__('Note')) ?></h2><p><?= nl2br(e($o['note'])) ?></p><?php endif ?></div>
</div></div>
