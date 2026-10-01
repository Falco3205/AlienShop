<div class="card" style="padding:0"><table><thead><tr><th>Negozio</th><th>Indirizzo</th><th>Stato</th><th>Ordini 30 g</th><th>Incassi 30 g</th><th>Versione</th><th>Ultimo contatto</th></tr></thead><tbody>
<?php foreach ($shops as $s): $m = Hub\Shops::metrics($s); $c = $m['currency'] ?? 'EUR'; ?>
<tr><td><a href="<?= e(url('shops/' . $s['id'])) ?>"><strong><?= e($s['name']) ?></strong></a></td>
<td><?= e($s['domain'] . ($s['path'] ? '/' . $s['path'] : '')) ?><div class="muted"><?= $s['edge_node_id'] ? 'tramite frontend' : 'diretto' ?></div></td>
<td><?= h_status($s) ?></td>
<td><?= $m ? (int)$m['orders_30d'] : '—' ?></td>
<td><?= $m ? e(h_money((int)$m['revenue_30d'], $c)) : '—' ?></td>
<td><code><?= e($s['version'] ?: '—') ?></code><?= !empty($m['update_available']) ? ' <span class="pill info">aggiornabile</span>' : '' ?></td>
<td><?= e(h_ago($s['last_ok'])) ?></td></tr>
<?php endforeach ?>
<?php if (!$shops): ?><tr><td colspan="7"><?= a_empty('🛍️', 'Nessun negozio', 'Crea il primo negozio per un tuo cliente.', url('shops/new'), 'Nuovo negozio') ?></td></tr><?php endif ?></tbody></table></div>
