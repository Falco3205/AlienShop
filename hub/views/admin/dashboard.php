<?php $cur = $fleet['currency']; ?>
<?php if (!$nodes): ?>
  <div class="alert info"><strong>Primo passo:</strong> collega la tua VPS backend (e, se vuoi, quella frontend) da <a href="<?= e(url('nodes/new')) ?>">Server → Collega un server</a>. Poi crea il primo negozio.</div>
<?php endif ?>
<div class="grid4" style="margin-bottom:20px">
  <div class="stat"><small>Negozi attivi</small><strong><?= (int)$fleet['active'] ?></strong><?= $fleet['offline'] ? '<small style="color:#9c1c30">' . (int)$fleet['offline'] . ' non rispondono</small>' : '<small>tutti raggiungibili</small>' ?></div>
  <div class="stat"><small>Incassi oggi</small><strong><?= e(h_money($fleet['revenue_today'], $cur)) ?></strong><small><?= (int)$fleet['orders_today'] ?> ordini</small></div>
  <div class="stat"><small>Incassi 30 giorni</small><strong><?= e(h_money($fleet['revenue_30d'], $cur)) ?></strong><?= h_trend($fleet['revenue_30d'], $fleet['revenue_prev_30d']) ?></div>
  <div class="stat"><small>Da spedire</small><strong><?= (int)$fleet['to_ship'] ?></strong><small><?= (int)$fleet['orders_30d'] ?> ordini negli ultimi 30 giorni</small></div>
</div>
<?php if ($fleet['mixed_currencies']): ?><p class="muted">I totali sommano solo i negozi in <?= e($cur) ?>.</p><?php endif ?>
<div class="grid2"><div>
<div class="card"><h2>Incassi giornalieri (tutti i negozi)</h2><?= $fleet['daily'] ? h_bars($fleet['daily'], $cur) : '<p class="muted">I dati compaiono dopo il primo aggiornamento dei negozi.</p>' ?></div>
<div class="card" style="padding:0"><table><thead><tr><th>Negozio</th><th>Stato</th><th>Oggi</th><th>30 giorni</th><th>Andamento</th><th></th></tr></thead><tbody>
<?php foreach ($shops as $s): $m = Hub\Shops::metrics($s); $c = $m['currency'] ?? 'EUR'; ?>
<tr><td><a href="<?= e(url('shops/' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a><div class="muted"><?= e($s['domain'] . ($s['path'] ? '/' . $s['path'] : '')) ?></div></td>
<td><?= h_status($s) ?></td>
<td><?= $m ? e(h_money((int)$m['revenue_today'], $c)) . '<div class="muted">' . (int)$m['orders_today'] . ' ordini</div>' : '—' ?></td>
<td><?= $m ? e(h_money((int)$m['revenue_30d'], $c)) . '<div class="muted">' . (int)$m['orders_30d'] . ' ordini</div>' : '—' ?></td>
<td><?= h_spark(Hub\Fleet::series($s)) ?></td>
<td class="right"><?= !empty($m['to_ship']) ? '<span class="pill warn">' . (int)$m['to_ship'] . ' da spedire</span>' : '' ?></td></tr>
<?php endforeach ?>
<?php if (!$shops): ?><tr><td colspan="6"><?= a_empty('🛍️', 'Nessun negozio', 'Crea il primo negozio per un tuo cliente.', url('shops/new'), 'Nuovo negozio') ?></td></tr><?php endif ?></tbody></table></div>
</div>
<div>
<div class="card"><h2>Da guardare</h2>
<?php if (!$alerts): ?><p class="muted">Tutto a posto ✔</p><?php endif ?>
<ul style="list-style:none;padding:0;margin:0;display:grid;gap:10px">
<?php foreach (array_slice($alerts, 0, 12) as $a): ?><li><span class="pill <?= $a['level'] === 'bad' ? 'bad' : ($a['level'] === 'warn' ? 'warn' : 'info') ?>"><?= $a['level'] === 'bad' ? 'Urgente' : ($a['level'] === 'warn' ? 'Attenzione' : 'Info') ?></span>
<?= $a['shop'] ? '<a href="' . e(url('shops/' . $a['shop'])) . '">' . e($a['text']) . '</a>' : e($a['text']) ?></li><?php endforeach ?></ul></div>
<div class="card"><h2>Server</h2>
<?php foreach ($nodes as $n): $i = Hub\Nodes::info($n); ?><p style="margin:0 0 10px"><a href="<?= e(url('nodes/' . $n['id'])) ?>"><strong><?= e($n['name']) ?></strong></a> <span class="pill <?= Hub\Nodes::online($n) ? 'ok' : 'bad' ?>"><?= Hub\Nodes::online($n) ? 'online' : 'offline' ?></span>
<span class="muted"><?= e($n['role'] === 'edge' ? 'frontend' : 'backend') ?><?= isset($i['load']) ? ' · carico ' . e($i['load']) : '' ?><?= isset($i['disk_used_pct']) ? ' · disco ' . (int)$i['disk_used_pct'] . '%' : '' ?></span></p><?php endforeach ?>
<?php if (!$nodes): ?><p class="muted">Nessun server collegato.</p><?php endif ?>
<form method="post" action="<?= e(url('poll')) ?>" style="margin-top:12px"><?= csrf_field() ?><button class="btn sec sm" type="submit">Aggiorna i dati adesso</button> <span class="muted">ultimo: <?= $lastPoll ? e(h_ago(date('Y-m-d H:i:s', $lastPoll))) : 'mai' ?></span></form></div>
</div></div>
