<?php if (!empty($errors)): ?><div class="alert error"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<?php if (!$backends): ?><div class="alert info">Prima collega un server backend da <a href="<?= e(url('nodes/new')) ?>">Server</a>.</div><?php endif ?>
<form method="post" action="<?= e(url('shops/new')) ?>"><?= csrf_field() ?>
<div class="grid2"><div>
<div class="card"><h2>Il negozio</h2>
<?= a_input('name', 'Nome del negozio', $in['name'], 'text', ['required' => true]) ?>
<?= a_input('domain', 'Dominio', $in['domain'], 'text', ['required' => true, 'placeholder' => 'negozio.cliente.it'], 'Il dominio deve già puntare al server (record A). Se non esiste in Hestia lo crea l\'agente.') ?>
<div class="field"><label>Dove installarlo</label>
<label class="check"><input type="radio" name="location" value="root" <?= $in['location'] === 'root' ? 'checked' : '' ?>> Nella cartella principale del dominio (https://dominio/)</label>
<label class="check"><input type="radio" name="location" value="sub" <?= $in['location'] === 'sub' ? 'checked' : '' ?>> In una sottocartella (https://dominio/<strong>cartella</strong>) — il resto del sito resta com'è</label></div>
<?= a_input('path', 'Nome della sottocartella', $in['path'], 'text', ['placeholder' => 'negozio'], 'Usata solo se scegli la sottocartella.') ?>
</div>
<div class="card"><h2>Amministratore</h2>
<?= a_input('admin_email', 'Email dell\'amministratore del negozio', $in['admin_email'], 'email', ['required' => true]) ?>
<p class="muted">La password iniziale viene generata e mostrata nella scheda del negozio.</p>
<div class="row"><?= a_select('theme', 'Tema', array_combine($themes, $themes), $in['theme']) ?><?= a_select('lang', 'Lingua', ['it' => 'Italiano', 'en' => 'English'], $in['lang']) ?></div>
<label class="check"><input type="checkbox" name="demo" value="1" <?= $in['demo'] ? 'checked' : '' ?>> Inserisci prodotti di esempio</label></div>
</div><div>
<div class="card"><h2>Server</h2>
<?= a_select('node_id', 'Server backend (ospita il negozio)', ['' => '— scegli —'] + array_column($backends, 'name', 'id'), $in['node_id']) ?>
<div class="field"><label>Pubblicazione</label>
<label class="check"><input type="radio" name="mode" value="direct" <?= $in['mode'] === 'direct' ? 'checked' : '' ?>> Diretta: il dominio punta al backend</label>
<label class="check"><input type="radio" name="mode" value="edge" <?= $in['mode'] === 'edge' ? 'checked' : '' ?>> Tramite frontend: il dominio punta al frontend, che serve la cache e inoltra al backend</label></div>
<?= a_select('edge_node_id', 'Server frontend', ['' => '— scegli —'] + array_column($edges, 'name', 'id'), $in['edge_node_id'], 'Solo per la pubblicazione tramite frontend.') ?>
<button class="btn" type="submit" <?= $backends ? '' : 'disabled' ?>>Crea il negozio</button></div>
</div></div></form>
