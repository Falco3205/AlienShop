<?php $c = $m['currency'] ?? 'EUR'; $url = Hub\Shops::url($shop); ?>
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;align-items:center"><?= h_status($shop) ?>
  <a class="btn sec sm" href="<?= e($url) ?>" target="_blank" rel="noopener">↗ Vedi il negozio</a>
  <?php if ($shop['status'] === 'active'): ?>
  <form method="post" action="<?= e(url('shops/' . $shop['id'] . '/open')) ?>"><?= csrf_field() ?><button class="btn sm" type="submit">Apri l'admin del negozio</button></form>
  <form method="post" action="<?= e(url('shops/' . $shop['id'] . '/poll')) ?>"><?= csrf_field() ?><button class="btn sec sm" type="submit">Aggiorna i dati</button></form>
  <form method="post" action="<?= e(url('shops/' . $shop['id'] . '/update')) ?>" onsubmit="return confirm('Aggiornare questo negozio all\'ultima versione?')"><?= csrf_field() ?><button class="btn sec sm" type="submit">Aggiorna il software<?= !empty($m['update_available']) ? ' ●' : '' ?></button></form>
  <form method="post" action="<?= e(url('shops/' . $shop['id'] . '/suspend')) ?>" onsubmit="return confirm('Sospendere il sito? Non sarà più raggiungibile.')"><?= csrf_field() ?><button class="btn sec sm" type="submit">Sospendi</button></form>
  <?php elseif ($shop['status'] === 'suspended'): ?>
  <form method="post" action="<?= e(url('shops/' . $shop['id'] . '/unsuspend')) ?>"><?= csrf_field() ?><button class="btn sm" type="submit">Riattiva</button></form>
  <?php elseif ($shop['status'] === 'error'): ?>
  <form method="post" action="<?= e(url('shops/' . $shop['id'] . '/retry')) ?>"><?= csrf_field() ?><button class="btn sm" type="submit">Riprova l'installazione</button></form>
  <?php endif ?></div>
<?php if ($shop['last_error']): ?><div class="alert error"><?= e($shop['last_error']) ?></div><?php endif ?>
<?php if ($password): ?><div class="alert info"><strong>Credenziali iniziali dell'admin del negozio</strong><br>Email: <code><?= e($shop['admin_email']) ?></code> · Password: <code><?= e($password) ?></code>
  <form method="post" action="<?= e(url('shops/' . $shop['id'] . '/forget-password')) ?>" style="margin-top:8px"><?= csrf_field() ?><button class="btn sec sm" type="submit">Le ho salvate: rimuovi dal pannello</button></form></div><?php endif ?>
<?php if ($m): ?>
<div class="grid4" style="margin-bottom:20px">
  <div class="stat"><small>Incassi oggi</small><strong><?= e(h_money((int)$m['revenue_today'], $c)) ?></strong><small><?= (int)$m['orders_today'] ?> ordini</small></div>
  <div class="stat"><small>Incassi 7 giorni</small><strong><?= e(h_money((int)$m['revenue_7d'], $c)) ?></strong><small><?= (int)$m['orders_7d'] ?> ordini</small></div>
  <div class="stat"><small>Incassi 30 giorni</small><strong><?= e(h_money((int)$m['revenue_30d'], $c)) ?></strong><?= h_trend((int)$m['revenue_30d'], (int)$m['revenue_prev_30d']) ?></div>
  <div class="stat"><small>Da spedire</small><strong><?= (int)$m['to_ship'] ?></strong><small><?= (int)$m['awaiting_payment'] ?> in attesa di pagamento</small></div>
</div>
<?php endif ?>
<div class="grid2"><div>
<?php if ($m): ?>
<div class="card"><h2>Incassi giornalieri</h2><?= h_bars(array_column($m['daily'] ?? [], 't', 'd'), $c) ?></div>
<div class="card" style="padding:0"><table><thead><tr><th>Prodotti più venduti (30 g)</th><th>Pezzi</th><th>Incasso</th></tr></thead><tbody>
<?php foreach ($m['top'] ?? [] as $t): ?><tr><td><?= e($t['name']) ?></td><td><?= (int)$t['qty'] ?></td><td><?= e(h_money((int)$t['total'], $c)) ?></td></tr><?php endforeach ?>
<?php if (empty($m['top'])): ?><tr><td colspan="3" class="muted">Ancora nessuna vendita.</td></tr><?php endif ?></tbody></table></div>
<?php else: ?><div class="card"><p class="muted">I dati compaiono appena il negozio è installato e risponde.</p></div><?php endif ?>
<div class="card"><h2>Note</h2><form method="post" action="<?= e(url('shops/' . $shop['id'] . '/notes')) ?>"><?= csrf_field() ?>
<?= a_input('name', 'Nome', $shop['name']) ?><?= a_textarea('notes', 'Note interne (cliente, contratto, scadenze…)', $shop['notes'], 4) ?><button class="btn sm" type="submit">Salva</button></form></div>
</div><div>
<?php if ($m): ?>
<div class="card"><h2>Stato del negozio</h2><table><tbody>
<tr><td>Prodotti attivi</td><td class="right"><?= (int)$m['products'] ?></td></tr>
<tr><td>Esauriti / scorta bassa</td><td class="right"><?= (int)$m['out_of_stock'] ?> / <?= (int)$m['low_stock'] ?></td></tr>
<tr><td>Clienti registrati</td><td class="right"><?= (int)$m['customers'] ?></td></tr>
<tr><td>Carrelli abbandonati</td><td class="right"><?= (int)$m['abandoned_carts'] ?></td></tr>
<tr><td>Pagamenti online</td><td class="right"><?= $m['payments_on'] ? e(implode(', ', $m['payments_on'])) : '<span class="pill warn">nessuno</span>' ?></td></tr>
<tr><td>Errori nel log (24 h)</td><td class="right"><?= (int)$m['errors_24h'] ?></td></tr>
<tr><td>Attività automatiche</td><td class="right"><?= e(h_ago($m['cron_last'] ?: null)) ?></td></tr>
<tr><td>Versione</td><td class="right"><code><?= e($shop['version']) ?></code><?= !empty($m['update_available']) ? ' <span class="pill info">aggiornabile</span>' : '' ?></td></tr>
<tr><td>PHP · database</td><td class="right"><?= e($m['php']) ?> · <?= e($m['db']) ?></td></tr>
<tr><td>Ultimo contatto</td><td class="right"><?= e(h_ago($shop['last_ok'])) ?></td></tr></tbody></table></div>
<?php endif ?>
<div class="card"><h2>Infrastruttura</h2><p style="line-height:1.8;margin:0">Backend: <strong><?= e($node['name'] ?? '—') ?></strong><br>Frontend: <strong><?= e($edge['name'] ?? 'nessuno (pubblicazione diretta)') ?></strong><br>Utente Hestia: <code><?= e($shop['hestia_user']) ?></code><br>Creato: <?= e(a_dt($shop['created_at'])) ?></p></div>
</div></div>
<div class="card" style="padding:0"><table><thead><tr><th>Attività</th><th>Stato</th><th>Quando</th></tr></thead><tbody>
<?php foreach ($jobs as $j): ?><tr><td><a href="<?= e(url('jobs/' . $j['id'])) ?>"><?= e(Hub\Jobs::TYPES[$j['type']] ?? $j['type']) ?></a></td><td><span class="pill <?= $j['status'] === 'ok' ? 'ok' : ($j['status'] === 'error' ? 'bad' : 'info') ?>"><?= e($j['status']) ?></span></td><td><?= e(h_ago($j['created_at'])) ?></td></tr><?php endforeach ?>
<?php if (!$jobs): ?><tr><td colspan="3" class="muted">Nessuna attività.</td></tr><?php endif ?></tbody></table></div>
