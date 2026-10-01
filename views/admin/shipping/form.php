<?php $m = $m ?? []; $v = static fn($k, $d = '') => $m[$k] ?? $d; $isNew = empty($m['id']); ?>
<form method="post" action="<?= e(url('admin/shipping/' . ($isNew ? 'new' : $m['id']))) ?>"><?= csrf_field() ?>
<div class="card" style="max-width:640px">
<?= a_input('name', __('Nome'), $v('name'), 'text', ['required' => true]) ?>
<div class="row"><?= a_input('price', __('Prezzo'), Alien\Core\Money::input((int)$v('price', 0)), 'text', ['inputmode' => 'decimal']) ?>
<?= a_input('free_over', __('Gratuita per ordini oltre (0 = mai)'), Alien\Core\Money::input((int)$v('free_over', 0)), 'text', ['inputmode' => 'decimal']) ?></div>
<?= a_input('countries', __('Paesi (codici ISO separati da virgola, vuoto = tutti)'), $v('countries'), 'text', ['placeholder' => 'IT,DE,FR']) ?>
<?= a_input('position', __('Posizione'), $v('position', 0), 'number') ?>
<?= a_check('active', __('Attivo'), $isNew ? true : $v('active')) ?>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button> <a class="btn sec" href="<?= e(url('admin/shipping')) ?>"><?= e(__('Annulla')) ?></a>
<?php if (!$isNew): ?><button class="btn danger" type="submit" form="del" style="float:right"><?= e(__('Elimina')) ?></button><?php endif ?>
</div></form>
<?php if (!$isNew): ?><form id="del" method="post" action="<?= e(url('admin/shipping/' . $m['id'] . '/delete')) ?>" data-confirm="<?= e(__('Eliminare il metodo?')) ?>"><?= csrf_field() ?></form><?php endif ?>
