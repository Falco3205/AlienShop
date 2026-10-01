<div class="card" style="padding:0"><table><thead><tr><th>#</th><th>Attività</th><th>Negozio</th><th>Server</th><th>Stato</th><th>Quando</th></tr></thead><tbody>
<?php foreach ($jobs as $j): ?><tr><td><a href="<?= e(url('jobs/' . $j['id'])) ?>"><?= (int)$j['id'] ?></a></td><td><?= e(Hub\Jobs::TYPES[$j['type']] ?? $j['type']) ?></td>
<td><?= $j['shop_id'] ? '<a href="' . e(url('shops/' . $j['shop_id'])) . '">' . e($shops[$j['shop_id']] ?? '#' . $j['shop_id']) . '</a>' : '—' ?></td><td><?= e($nodes[$j['node_id']] ?? '—') ?></td>
<td><span class="pill <?= $j['status'] === 'ok' ? 'ok' : ($j['status'] === 'error' ? 'bad' : 'info') ?>"><?= e($j['status']) ?></span></td><td><?= e(h_ago($j['created_at'])) ?></td></tr><?php endforeach ?>
<?php if (!$jobs): ?><tr><td colspan="6" class="muted">Nessuna attività.</td></tr><?php endif ?></tbody></table></div>
