<?php $c = $c ?? []; $v = static fn($k, $d = '') => $c[$k] ?? $d; $isNew = empty($c['id']); $pct = ($v('type', 'percent') === 'percent'); ?>
<form method="post" action="<?= e(url('admin/coupons/' . ($isNew ? 'new' : $c['id']))) ?>"><?= csrf_field() ?>
<div class="card" style="max-width:640px">
<?= a_input('code', __('Codice'), $v('code'), 'text', ['required' => true, 'style' => 'text-transform:uppercase']) ?>
<div class="row"><?= a_select('type', __('Tipo'), ['percent' => __('Percentuale (%)'), 'fixed' => __('Importo fisso')], $v('type', 'percent')) ?>
<?= a_input('value', __('Valore'), $pct ? $v('value', 10) : Alien\Core\Money::input((int)$v('value', 0)), 'text', ['inputmode' => 'decimal']) ?></div>
<div class="row"><?= a_input('min_subtotal', __('Ordine minimo'), Alien\Core\Money::input((int)$v('min_subtotal', 0)), 'text', ['inputmode' => 'decimal']) ?>
<?= a_input('max_uses', __('Utilizzi massimi (0 = illimitati)'), $v('max_uses', 0), 'number') ?></div>
<div class="row"><?= a_input('starts_at', __('Valido dal'), $v('starts_at') ? substr($v('starts_at'), 0, 10) : '', 'date') ?>
<?= a_input('expires_at', __('Valido fino al'), $v('expires_at') ? substr($v('expires_at'), 0, 10) : '', 'date') ?></div>
<?= a_check('free_shipping', __('Spedizione gratuita'), $v('free_shipping')) ?>
<?= a_check('active', __('Attivo'), $isNew ? true : $v('active')) ?>
<button class="btn" type="submit"><?= e(__('Salva')) ?></button> <a class="btn sec" href="<?= e(url('admin/coupons')) ?>"><?= e(__('Annulla')) ?></a>
<?php if (!$isNew): ?><button class="btn danger" type="submit" form="del" style="float:right"><?= e(__('Elimina')) ?></button><?php endif ?>
</div></form>
<?php if (!$isNew): ?><form id="del" method="post" action="<?= e(url('admin/coupons/' . $c['id'] . '/delete')) ?>" data-confirm="<?= e(__('Eliminare il codice?')) ?>"><?= csrf_field() ?></form><?php endif ?>
