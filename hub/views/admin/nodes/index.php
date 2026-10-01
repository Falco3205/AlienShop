<div class="card" style="padding:0"><table><thead><tr><th>Server</th><th>Ruolo</th><th>Stato</th><th>Risorse</th><th>Negozi</th></tr></thead><tbody>
<?php foreach ($nodes as $n): $i = Hub\Nodes::info($n); $cnt = (int)Alien\Core\DB::val('SELECT COUNT(*) FROM shops WHERE node_id = ? OR edge_node_id = ?', [$n['id'], $n['id']]); ?>
<tr><td><a href="<?= e(url('nodes/' . $n['id'])) ?>"><strong><?= e($n['name']) ?></strong></a><div class="muted"><?= e($n['address']) ?></div></td>
<td><?= e($n['role'] === 'edge' ? 'Frontend' : 'Backend') ?></td>
<td><span class="pill <?= Hub\Nodes::online($n) ? 'ok' : 'bad' ?>"><?= Hub\Nodes::online($n) ? 'online' : 'offline' ?></span><div class="muted">visto <?= e(h_ago($n['last_seen'])) ?></div></td>
<td class="muted"><?= isset($i['load']) ? 'carico ' . e($i['load']) : '' ?><?= isset($i['disk_used_pct']) ? ' · disco ' . (int)$i['disk_used_pct'] . '%' : '' ?><?= isset($i['mem_used_pct']) ? ' · RAM ' . (int)$i['mem_used_pct'] . '%' : '' ?></td>
<td><?= $cnt ?></td></tr>
<?php endforeach ?>
<?php if (!$nodes): ?><tr><td colspan="5"><?= a_empty('🖥️', 'Nessun server collegato', 'Collega la VPS backend e, se la usi, la VPS frontend.', url('nodes/new'), 'Collega un server') ?></td></tr><?php endif ?></tbody></table></div>
