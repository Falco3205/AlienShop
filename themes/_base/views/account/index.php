<div class="section-head"><h1><?= e(__('Il mio account')) ?></h1><form method="post" action="<?= e(url('account/logout')) ?>"><?= csrf_field() ?><button class="btn btn-outline btn-sm" type="submit"><?= e(__('Esci')) ?></button></form></div>
<p><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
<h2><?= e(__('I miei ordini')) ?></h2>
<?php if ($orders): ?>
<table class="cart"><thead><tr><th><?= e(__('Ordine')) ?></th><th><?= e(__('Data')) ?></th><th><?= e(__('Stato')) ?></th><th><?= e(__('Totale')) ?></th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td><a href="<?= e(url('checkout/thank-you/' . $o['token'])) ?>"><?= e($o['number']) ?></a></td><td><?= e(date('d/m/Y', strtotime($o['created_at']))) ?></td><td><span class="status"><?= e(__(Alien\Services\Orders::STATUSES[$o['status']] ?? $o['status'])) ?></span></td><td><?= e(money($o['total'])) ?></td></tr><?php endforeach ?>
</tbody></table>
<?php else: ?><p class="empty"><?= e(__('Non hai ancora effettuato ordini.')) ?></p><?php endif ?>
