<?php $c = $c ?? []; $v = static fn($k, $d = '') => $c[$k] ?? $d; $isNew = empty($c['id']); ?>
<form method="post" enctype="multipart/form-data" action="<?= e(url('admin/categories/' . ($isNew ? 'new' : $c['id']))) ?>"><?= csrf_field() ?>
<div class="grid2"><div>
  <div class="card">
    <?= a_input('name', __('Nome'), $v('name'), 'text', ['required' => true]) ?>
    <?= a_select('parent_id', __('Categoria padre'), [0 => '— ' . __('Nessuna') . ' —'] + $options, $v('parent_id', 0)) ?>
    <?= a_textarea('description', __('Descrizione (HTML)'), $v('description'), 6) ?>
    <div class="field"><label><?= e(__('Immagine')) ?></label><?php if ($v('image')): ?><div class="imgs"><img src="<?= e(upload_url($v('image'), 400)) ?>" alt=""></div><label class="check"><input type="checkbox" name="remove_image" value="1"> <?= e(__('Rimuovi immagine')) ?></label><?php endif ?><input type="file" name="image" accept="image/*"></div>
  </div>
  <div class="card"><h2>SEO</h2>
    <?= a_input('slug', 'Slug', $v('slug'), 'text', [], e(__('Se lo cambi, viene creato un redirect 301 automatico.'))) ?>
    <?= a_input('seo_title', __('Titolo SEO'), $v('seo_title')) ?>
    <?= a_textarea('seo_description', __('Meta description'), $v('seo_description'), 2) ?>
  </div></div>
<div><div class="card">
  <?= a_check('is_active', __('Attiva'), $isNew ? true : $v('is_active')) ?>
  <?= a_check('show_in_menu', __('Mostra nel menu'), $isNew ? true : $v('show_in_menu')) ?>
  <?= a_input('position', __('Posizione'), $v('position', 0), 'number') ?>
  <?php if (!$isNew): ?><button class="btn danger sm" type="submit" form="del"><?= e(__('Elimina categoria')) ?></button><?php endif ?>
</div></div></div>
<div class="sticky-save"><button class="btn" type="submit"><?= e(__('Salva')) ?></button><a class="btn sec" href="<?= e(url('admin/categories')) ?>"><?= e(__('Annulla')) ?></a></div>
</form>
<?php if (!$isNew): ?><form id="del" method="post" action="<?= e(url('admin/categories/' . $c['id'] . '/delete')) ?>" data-confirm="<?= e(__('Eliminare la categoria? I prodotti non verranno eliminati.')) ?>"><?= csrf_field() ?></form><?php endif ?>
