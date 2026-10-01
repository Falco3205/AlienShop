<?php if ($token): ?>
<div class="card"><h2>Collega il server</h2>
<p>Esegui questo comando <strong>come root</strong> sulla VPS "<?= e($node['name']) ?>". Il token viene mostrato una sola volta.</p>
<pre style="background:#111827;color:#e5e7eb;padding:14px;border-radius:8px;overflow:auto;white-space:pre-wrap;word-break:break-all"><?= e($command) ?></pre></div>
<?php endif ?>
<div class="grid2"><div class="card"><h2>Stato</h2><table><tbody>
<tr><td>Stato</td><td class="right"><span class="pill <?= Hub\Nodes::online($node) ? 'ok' : 'bad' ?>"><?= Hub\Nodes::online($node) ? 'online' : 'offline' ?></span></td></tr>
<tr><td>Ultimo contatto</td><td class="right"><?= e(h_ago($node['last_seen'])) ?></td></tr>
<tr><td>IP pubblico</td><td class="right"><?= e($node['address'] ?: '—') ?></td></tr>
<?php if ($node['role'] === 'backend'): ?><tr><td>Raggiungibile dal frontend come</td><td class="right"><code><?= e($node['upstream']) ?></code><?= $node['tunnel_host'] !== '' ? ' (tunnel)' : '' ?></td></tr><?php endif ?>
<tr><td>Utente Hestia</td><td class="right"><code><?= e($node['hestia_user']) ?></code></td></tr>
<?php foreach (['hostname' => 'Host', 'os' => 'Sistema', 'hestia' => 'Hestia', 'php' => 'PHP', 'agent' => 'Agente', 'load' => 'Carico', 'disk_used_pct' => 'Disco usato (%)', 'mem_used_pct' => 'RAM usata (%)', 'ip' => 'IP da cui contatta l\'hub'] as $k => $label): if (isset($info[$k])): ?>
<tr><td><?= e($label) ?></td><td class="right"><?= e($info[$k]) ?></td></tr><?php endif; endforeach ?></tbody></table></div>
<div><div class="card"><h2>Negozi su questo server</h2>
<?php foreach ($shops as $s): ?><p style="margin:0 0 8px"><a href="<?= e(url('shops/' . $s['id'])) ?>"><?= e($s['name']) ?></a> <span class="muted"><?= e($s['domain'] . ($s['path'] ? '/' . $s['path'] : '')) ?></span></p><?php endforeach ?>
<?php if (!$shops): ?><p class="muted">Nessuno.</p><?php endif ?></div>
<div class="card"><h2>Token</h2><p class="muted">Se hai perso il token o vuoi sostituirlo, generane uno nuovo: l'agente smetterà di funzionare finché non lo aggiorni.</p>
<form method="post" action="<?= e(url('nodes/' . $node['id'] . '/token')) ?>" data-confirm="Generare un nuovo token?"><?= csrf_field() ?><button class="btn sec sm" type="submit">Genera un nuovo token</button></form></div></div></div>
