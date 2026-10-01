<?php $p = $p ?? []; $v = static fn($k, $d = '') => $p[$k] ?? $d; $isNew = empty($p['id']); ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('admin/pages/' . ($isNew ? 'new' : $p['id']))) ?>"><?= csrf_field() ?>
<div class="grid2"><div>
<div class="card"><?= a_input('title', __('Titolo'), $v('title'), 'text', ['required' => true]) ?>
<?= a_textarea('content', __('Contenuto (HTML)'), $v('content'), 16, '', true) ?>
<?= a_textarea('excerpt', __('Riassunto'), $v('excerpt'), 2) ?></div>
<div class="card"><h2>SEO</h2><?= a_input('slug', 'Slug', $v('slug')) ?><?= a_input('seo_title', __('Titolo SEO'), $v('seo_title')) ?><?= a_textarea('seo_description', __('Meta description'), $v('seo_description'), 2) ?></div></div>
<div><div class="card">
<?= a_select('type', __('Tipo'), ['page' => __('Pagina'), 'post' => __('Articolo del blog')], $v('type', $newType ?? 'page')) ?>
<?= a_check('is_active', __('Pubblicata'), $isNew ? true : $v('is_active')) ?>
<?= a_check('show_in_menu', __('Mostra nel menu'), $v('show_in_menu')) ?>
<?= a_check('show_in_footer', __('Mostra nel footer'), $v('show_in_footer')) ?>
<div class="field"><label><?= e(__('Immagine')) ?></label><?php if ($v('image')): ?><div class="imgs"><img src="<?= e(upload_url($v('image'), 400)) ?>" alt=""></div><?php endif ?><input type="file" name="image" accept="image/*"></div>
<?php if (!$isNew): ?><button class="btn danger sm" type="submit" form="del"><?= e(__('Elimina')) ?></button><?php endif ?>
</div></div></div>
<div class="sticky-save"><button class="btn" type="submit"><?= e(__('Salva')) ?></button><a class="btn sec" href="<?= e(url('admin/pages')) ?>"><?= e(__('Annulla')) ?></a></div></form>
<?php if (!$isNew): ?><form id="del" method="post" action="<?= e(url('admin/pages/' . $p['id'] . '/delete')) ?>" data-confirm="<?= e(__('Eliminare la pagina?')) ?>"><?= csrf_field() ?></form><?php endif ?>
