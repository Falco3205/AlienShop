<div class="themes">
<?php foreach ($themes as $slug => $t): ?>
  <div class="theme <?= $slug === $active ? 'active' : '' ?>">
    <div class="sw"><?php foreach ($t['colors'] ?? [] as $c): ?><span style="background:<?= e($c) ?>"></span><?php endforeach ?></div>
    <div class="b"><h3><?= e($t['name']) ?> <?= $slug === $active ? '<span class="pill ok">' . e(__('Attivo')) . '</span>' : '' ?></h3><p><?= e($t['description'] ?? '') ?></p>
      <div style="display:flex;gap:8px"><a class="btn sec sm" href="<?= e(url('?preview_theme=' . $slug)) ?>" target="_blank" rel="noopener"><?= e(__('Anteprima')) ?></a>
      <?php if ($slug !== $active): ?><form method="post" action="<?= e(url('admin/themes/activate')) ?>"><?= csrf_field() ?><input type="hidden" name="theme" value="<?= e($slug) ?>"><button class="btn sm" type="submit"><?= e(__('Attiva')) ?></button></form><?php endif ?></div></div>
  </div>
<?php endforeach ?>
</div>

<h2 style="margin:32px 0 14px"><?= e(__('Personalizza')) ?></h2>
<form method="post" enctype="multipart/form-data" action="<?= e(url('admin/themes/customize')) ?>"><?= csrf_field() ?>
<div class="grid2"><div>
<div class="card"><h2><?= e(__('Home page')) ?></h2>
<?= a_input('hero_title', __('Titolo principale'), setting('hero_title', '')) ?>
<?= a_textarea('hero_subtitle', __('Sottotitolo'), setting('hero_subtitle', ''), 2) ?>
<div class="row"><?= a_input('hero_cta', __('Testo del pulsante'), setting('hero_cta', '')) ?><?= a_input('hero_link', __('Link del pulsante'), setting('hero_link', 'collections/all')) ?></div>
<?= a_input('announcement', __('Barra annunci in alto (vuoto = nascosta)'), setting('announcement', '')) ?>
<?php foreach (['logo' => __('Logo'), 'favicon' => __('Favicon'), 'hero_image' => __('Immagine hero')] as $k => $l): ?>
<div class="field"><label><?= e($l) ?></label><?php if (setting($k)): ?><div class="imgs"><img src="<?= e(upload_url(setting($k), 400)) ?>" alt=""></div><label class="check"><input type="checkbox" name="remove_<?= $k ?>" value="1"> <?= e(__('Rimuovi')) ?></label><?php endif ?><input type="file" name="<?= $k ?>" accept="image/*"></div>
<?php endforeach ?></div>
<div class="card"><h2><?= e(__('CSS personalizzato')) ?></h2><?= a_textarea('custom_css', '', setting('custom_css', ''), 6) ?></div>
</div><div>
<div class="card"><h2><?= e(__('Colori')) ?></h2><p class="help"><?= e(__('Sovrascrivono i colori del tema attivo. Lascia disattivato per usare quelli originali.')) ?></p>
<?php foreach (['theme_primary' => [__('Colore primario'), '#4f46e5'], 'theme_accent' => [__('Colore accento'), '#f43f5e'], 'theme_bg' => [__('Sfondo'), '#ffffff'], 'theme_text' => [__('Testo'), '#111111']] as $k => [$l, $def]): $cur = (string)setting($k, ''); ?>
<div class="field"><label class="check"><input type="checkbox" name="<?= $k ?>_on" value="1" <?= $cur !== '' ? 'checked' : '' ?>> <?= e($l) ?></label><input type="color" name="<?= $k ?>" value="<?= e($cur ?: $def) ?>"></div>
<?php endforeach ?></div>
<button class="btn" type="submit"><?= e(__('Salva personalizzazione')) ?></button></div></div>
</form>
