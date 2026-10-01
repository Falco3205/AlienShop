<div class="toolbar"><?php foreach ([7, 30, 90] as $d): ?><a class="btn sm <?= $days === $d ? '' : 'sec' ?>" href="<?= e(url('admin?days=' . $d)) ?>"><?= e(__('%d giorni', $d)) ?></a><?php endforeach ?></div>
<div class="grid4" style="margin-bottom:20px">
  <div class="stat"><small><?= e(__('Vendite')) ?></small><strong><?= e(money($stats['revenue'])) ?></strong></div>
  <div class="stat"><small><?= e(__('Ordini')) ?></small><strong><?= (int)$stats['orders'] ?></strong></div>
  <div class="stat"><small><?= e(__('Da spedire')) ?></small><strong><?= (int)$toShip ?></strong></div>
  <div class="stat"><small><?= e(__('Prodotti / Clienti')) ?></small><strong><?= (int)$products ?> / <?= (int)$customers ?></strong></div>
</div>
<div class="grid2">
  <div>
    <div class="card"><h2><?= e(__('Andamento vendite')) ?></h2>
      <?php $max = max(1, ...array_map(static fn($d) => (int)$d['t'], $stats['daily'] ?: [['t' => 1]])); ?>
      <?php if ($stats['daily']): ?><div class="chart"><?php foreach ($stats['daily'] as $d): ?><i style="height:<?= max(2, round($d['t'] / $max * 100)) ?>%" title="<?= e($d['d'] . ': ' . money($d['t'])) ?>"></i><?php endforeach ?></div>
      <?php else: ?><p class="muted"><?= e(__('Nessun ordine nel periodo.')) ?></p><?php endif ?></div>
    <div class="card"><h2><?= e(__('Ultimi ordini')) ?></h2>
      <table><tbody><?php foreach ($recent as $o): ?><tr><td><a href="<?= e(url('admin/orders/' . $o['id'])) ?>"><?= e($o['number']) ?></a></td><td><?= e($o['email']) ?></td><td><?= a_status($o['status']) ?></td><td class="right"><?= e(money($o['total'])) ?></td></tr><?php endforeach ?>
      <?php if (!$recent): ?><tr><td class="muted"><?= e(__('Nessun ordine.')) ?></td></tr><?php endif ?></tbody></table></div>
  </div>
  <div>
    <div class="card"><h2><?= e(__('Più venduti')) ?></h2><table><tbody><?php foreach ($stats['top'] as $t): ?><tr><td><?= e($t['name']) ?></td><td class="right"><?= (int)$t['qty'] ?>×</td></tr><?php endforeach ?>
      <?php if (!$stats['top']): ?><tr><td class="muted">—</td></tr><?php endif ?></tbody></table></div>
    <div class="card"><h2><?= e(__('Scorte basse')) ?></h2><table><tbody><?php foreach ($lowStock as $p): ?><tr><td><a href="<?= e(url('admin/products/' . $p['id'])) ?>"><?= e($p['name']) ?></a></td><td class="right"><?= (int)$p['stock_qty'] ?></td></tr><?php endforeach ?>
      <?php if (!$lowStock): ?><tr><td class="muted">—</td></tr><?php endif ?></tbody></table></div>
  </div>
</div>
