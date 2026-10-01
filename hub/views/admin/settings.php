<?php if (!empty($error)): ?><div class="alert error"><?= e($error) ?></div><?php endif ?>
<div class="grid2"><div class="card"><h2>Valori predefiniti per i nuovi negozi</h2><form method="post" action="<?= e(url('settings')) ?>"><?= csrf_field() ?>
<?= a_input('shop_repo', 'Repository GitHub di AlienShop', setting('shop_repo', 'Falco3205/AlienShop'), 'text', [], 'Da qui i server scaricano e aggiornano i negozi.') ?>
<?= a_input('shop_branch', 'Branch', setting('shop_branch', 'main')) ?>
<?= a_input('default_admin_email', 'Email amministratore predefinita', setting('default_admin_email', ''), 'email', [], 'Usata per i domini creati direttamente da Hestia con il template AlienShop.') ?>
<div class="row"><?= a_input('default_theme', 'Tema predefinito', setting('default_theme', 'aurora')) ?><?= a_select('default_lang', 'Lingua predefinita', ['it' => 'Italiano', 'en' => 'English'], setting('default_lang', 'it')) ?></div>
<?= a_input('new_password', 'Nuova password dell\'hub (lascia vuoto per non cambiarla)', '', 'password', ['autocomplete' => 'new-password']) ?>
<button class="btn" type="submit">Salva</button></form></div>
<div class="card"><h2>Aggiorna l'hub</h2><p class="muted">Versione <?= e(ALIEN_VERSION) ?>. <?= $git ? 'Scarica le novità da GitHub.' : 'Installato senza Git: aggiornalo riscaricando i file.' ?></p>
<?php if ($git): ?><form method="post" action="<?= e(url('settings/update')) ?>"><?= csrf_field() ?><button class="btn sec" type="submit">Aggiorna ora</button></form><?php endif ?></div></div>
