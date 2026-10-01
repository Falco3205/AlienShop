<div class="grid2"><div>
<div class="card"><h2>💾 <?= e(__('Scarica un backup')) ?></h2>
  <p class="muted"><?= e($last ? __('Ultimo backup: %s', a_dt($last)) : __('Non hai ancora scaricato nessun backup.')) ?></p>
  <div style="display:grid;gap:10px;max-width:480px">
    <form method="post" action="<?= e(url('admin/backup/download')) ?>"><?= csrf_field() ?><input type="hidden" name="type" value="db"><button class="btn sec" style="width:100%"><?= e(__('Solo database (prodotti, ordini, clienti, impostazioni)')) ?></button></form>
    <?php if ($zip): ?><form method="post" action="<?= e(url('admin/backup/download')) ?>"><?= csrf_field() ?><input type="hidden" name="type" value="full"><button class="btn" style="width:100%">📦 <?= e(__('Backup completo (database + immagini)')) ?></button></form>
    <?php else: ?><div class="alert info"><?= e(__('L\'estensione PHP "zip" non è attiva: puoi scaricare solo il database.')) ?></div><?php endif ?></div></div>
<div class="card"><h2><?= e(__('Come ripristinare')) ?></h2>
  <p>1. <?= e(__('Installa AlienShop sul nuovo spazio e carica i file.')) ?><br>2. <?= e(__('Carica il file di backup sul server.')) ?><br>3. <code>php bin/console backup:restore backup.zip</code></p>
  <p class="muted" style="font-size:.85rem"><?= e(__('Il backup completo contiene anche config/config.php (password del database e chiave del sito): conservalo in un posto sicuro e non condividerlo.')) ?></p></div></div>
<div class="card"><h2><?= e(__('Buone abitudini')) ?></h2><ul style="line-height:1.8;padding-left:18px"><li><?= e(__('Fai un backup prima di importare un grosso catalogo.')) ?></li><li><?= e(__('Scaricane uno ogni settimana e conservalo fuori dal tuo hosting.')) ?></li><li><?= e(__('Molti hosting offrono anche backup automatici: attivali.')) ?></li></ul></div></div>
