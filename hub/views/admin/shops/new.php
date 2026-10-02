<?php if (!empty($errors)): ?><div class="alert error"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php if (!$backends): ?><div class="alert info">Prima collega un server backend da <a href="<?= e(url('nodes/new')) ?>">Server</a>.</div><?php endif ?>
<form method="post" action="<?= e(url('shops/new')) ?>"><?= csrf_field() ?>
<div class="grid2"><div>
<div class="card"><h2>Il negozio</h2>
<?= a_input('name', 'Nome del negozio', $in['name'], 'text', ['required' => true]) ?>
<?php if ($hestiaDomains): ?>
<?php $opts = ['' => '— scegli un dominio già presente in Hestia —']; foreach ($hestiaDomains as $d) { $opts[$d['node_id'] . '|' . $d['user'] . '|' . $d['domain']] = $d['domain'] . ' — utente ' . $d['user'] . ' — ' . $d['root']; } ?>
<?= a_select('hestia_domain', 'Dominio di Hestia', $opts, $_POST['hestia_domain'] ?? '', 'Elenco letto da Hestia sul server frontend. Con una sottocartella, il resto del sito resta quello di Hestia; solo la cartella scelta va al negozio sul backend.') ?>
<?php endif ?>
<?= a_input('domain', $hestiaDomains ? 'Oppure un dominio nuovo' : 'Dominio', $in['domain'], 'text', ['placeholder' => 'negozio.cliente.it'], 'Un dominio nuovo deve puntare al frontend (record A) e viene creato in Hestia dall\'agente.') ?>
<div class="field"><label>Dove installarlo</label>
<label class="check"><input type="radio" name="location" value="root" <?= $in['location'] === 'root' ? 'checked' : '' ?>> Nella cartella principale del dominio (https://dominio/)</label>
<label class="check"><input type="radio" name="location" value="sub" <?= $in['location'] === 'sub' ? 'checked' : '' ?>> In una sottocartella (https://dominio/<strong>cartella</strong>) — il resto del sito resta com'è</label></div>
<?= a_input('path', 'Nome della sottocartella', $in['path'], 'text', ['placeholder' => 'negozio'], 'Usata solo se scegli la sottocartella.') ?>
</div>
<div class="card"><h2>Amministratore</h2>
<?= a_input('admin_email', 'Email dell\'amministratore del negozio', $in['admin_email'], 'email', ['required' => true]) ?>
<p class="muted">La password iniziale viene generata e mostrata nella scheda del negozio.</p>
<?= a_select('lang', 'Lingua', ['it' => 'Italiano', 'en' => 'English'], $in['lang']) ?>
<label class="check"><input type="checkbox" name="demo" value="1" <?= $in['demo'] ? 'checked' : '' ?>> Inserisci prodotti di esempio</label></div>
</div><div>
<div class="card"><h2>Server</h2>
<?= a_select('node_id', 'Server backend (ospita il negozio)', ['' => '— scegli —'] + array_column($backends, 'name', 'id'), $in['node_id']) ?>
<div class="field"><label>Pubblicazione</label>
<label class="check"><input type="radio" name="mode" value="direct" <?= $in['mode'] === 'direct' ? 'checked' : '' ?>> Diretta: il dominio punta al backend</label>
<label class="check"><input type="radio" name="mode" value="edge" <?= $in['mode'] === 'edge' ? 'checked' : '' ?>> Tramite frontend: il dominio punta al frontend, che serve la cache e inoltra al backend</label></div>
<?= a_select('edge_node_id', 'Server frontend', ['' => '— scegli —'] + array_column($edges, 'name', 'id'), $in['edge_node_id'], 'Solo per la pubblicazione tramite frontend.') ?>
</div>
</div></div>
<div class="card"><h2>Tema</h2><p class="muted">Clicca un'anteprima per scegliere il tema del negozio. Potrai cambiarlo in seguito dall'admin del negozio.</p>
<div class="theme-grid">
<?php foreach ($themes as $slug => $t): ?>
<?php $style = ''; foreach ($t['vars'] as $k => $val) { $style .= $k . ':' . $val . ';'; } $l = $t['layout'] + ['header' => 'left', 'grid' => 4, 'card' => 'border', 'hero' => 'split']; ?>
<label class="theme-opt"><input type="radio" name="theme" value="<?= e($slug) ?>" <?= $in['theme'] === $slug ? 'checked' : '' ?>>
<span class="theme-pick">
<span class="tp" style="<?= e($style) ?>">
<span class="tp-head tp-h-<?= e((string)$l['header']) ?>"><b>Logo</b><i></i><i></i><i></i></span>
<span class="tp-hero tp-hero-<?= e((string)$l['hero']) ?>"><em>Titolo</em><u>Acquista</u></span>
<span class="tp-cards tp-c-<?= e((string)$l['card']) ?>" style="--cols:<?= min(4, max(2, (int)$l['grid'])) ?>"><s></s><s></s><s></s><?= (int)$l['grid'] >= 4 ? '<s></s>' : '' ?></span>
</span>
<span class="tp-meta"><strong><?= e($t['name']) ?></strong><small><?= e($t['description']) ?></small>
<span class="tp-dots"><?php foreach (['--bg', '--primary', '--accent', '--surface'] as $c): if (isset($t['vars'][$c])): ?><i style="background:<?= e($t['vars'][$c]) ?>"></i><?php endif; endforeach ?></span></span>
</span></label>
<?php endforeach ?>
</div></div>
<button class="btn" type="submit" <?= $backends ? '' : 'disabled' ?>>Crea il negozio</button>
</form>
