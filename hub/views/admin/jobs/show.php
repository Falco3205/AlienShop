<p><a href="<?= e(url('jobs')) ?>">← Tutte le attività</a></p>
<div class="grid2"><div class="card"><h2>Registro</h2><pre style="background:#111827;color:#e5e7eb;padding:14px;border-radius:8px;overflow:auto;white-space:pre-wrap;max-height:520px"><?= e($job['log'] ?: 'Nessun output.') ?></pre></div>
<div><div class="card"><h2>Esito</h2><p><span class="pill <?= $job['status'] === 'ok' ? 'ok' : ($job['status'] === 'error' ? 'bad' : 'info') ?>"><?= e($job['status']) ?></span></p>
<?php if (!empty($result['error'])): ?><div class="alert error"><?= e($result['error']) ?></div><?php endif ?>
<p class="muted">Creata <?= e(a_dt($job['created_at'])) ?><?= $job['finished_at'] ? ' · conclusa ' . e(a_dt($job['finished_at'])) : '' ?></p></div>
<div class="card"><h2>Parametri</h2><pre style="white-space:pre-wrap;margin:0"><?= e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></div></div></div>
