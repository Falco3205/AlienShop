<h1><?= e(__('Checkout')) ?></h1>
<?php if (!empty($errors)): ?><div class="alert alert-error" role="alert"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form action="<?= e(url('checkout')) ?>" method="post" data-refresh="<?= e(url('checkout/refresh')) ?>" class="layout-checkout" novalidate>
  <?= csrf_field() ?>
  <div>
    <fieldset><legend><?= e(__('Contatti')) ?></legend>
      <div class="field"><label for="email"><?= e(__('Email')) ?></label><input id="email" type="email" name="email" required autocomplete="email" value="<?= e(old('email', $user['email'] ?? '')) ?>"></div>
      <div class="row"><div class="field"><label for="name"><?= e(__('Nome e cognome')) ?></label><input id="name" name="name" required autocomplete="name" value="<?= e(old('name', $user['name'] ?? '')) ?>"></div>
      <div class="field"><label for="phone"><?= e(__('Telefono')) ?></label><input id="phone" name="phone" autocomplete="tel" value="<?= e(old('phone', $user['phone'] ?? '')) ?>"></div></div>
    </fieldset>
    <fieldset><legend><?= e(__('Indirizzo di spedizione')) ?></legend>
      <div class="field"><label for="address"><?= e(__('Indirizzo')) ?></label><input id="address" name="address" required autocomplete="street-address" value="<?= e(old('address')) ?>"></div>
      <div class="row"><div class="field"><label for="city"><?= e(__('Città')) ?></label><input id="city" name="city" required autocomplete="address-level2" value="<?= e(old('city')) ?>"></div>
      <div class="field"><label for="zip"><?= e(__('CAP')) ?></label><input id="zip" name="zip" required autocomplete="postal-code" value="<?= e(old('zip')) ?>"></div></div>
      <div class="row"><div class="field"><label for="state"><?= e(__('Provincia')) ?></label><input id="state" name="state" autocomplete="address-level1" value="<?= e(old('state')) ?>"></div>
      <div class="field"><label for="country"><?= e(__('Paese')) ?></label><select id="country" name="country" data-autosubmit-refresh><?php foreach ($countries as $code => $label): ?><option value="<?= $code ?>" <?= old('country', setting('default_country', 'IT')) === $code ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select></div></div>
      <div class="field"><label for="note"><?= e(__('Note per il corriere (opzionale)')) ?></label><textarea id="note" name="note" rows="2"><?= e(old('note')) ?></textarea></div>
    </fieldset>
    <?php if ($totals['shipping_methods']): ?>
    <fieldset><legend><?= e(__('Spedizione')) ?></legend>
      <?php foreach ($totals['shipping_methods'] as $m): $price = Alien\Services\Shipping::price($m, $totals['subtotal'] - $totals['discount'], !empty($totals['coupon']['free_shipping'])); ?>
        <label class="ship-option"><input type="radio" name="shipping_method" value="<?= (int)$m['id'] ?>" <?= (int)($totals['shipping_method']['id'] ?? 0) === (int)$m['id'] ? 'checked' : '' ?>><span><?= e($m['name']) ?> — <?= $price ? e(money($price)) : e(__('Gratuita')) ?></span></label>
      <?php endforeach ?></fieldset>
    <?php endif ?>
    <fieldset><legend><?= e(__('Pagamento')) ?></legend>
      <?php foreach ($gateways as $i => $g): ?>
        <label class="pay-option"><input type="radio" name="payment_method" value="<?= e($g->id()) ?>" <?= (old('payment_method', array_key_first($gateways)) === $g->id()) ? 'checked' : '' ?> required><span><?= e($g->title()) ?><?php if ($g->description()): ?><small><?= nl2br(e($g->description())) ?></small><?php endif ?></span></label>
      <?php endforeach ?>
      <?php if (!$gateways): ?><p class="alert alert-error"><?= e(__('Nessun metodo di pagamento attivo.')) ?></p><?php endif ?>
    </fieldset>
    <?php if (!$user): ?><fieldset><legend><?= e(__('Crea un account (opzionale)')) ?></legend><div class="field"><label for="password"><?= e(__('Password')) ?></label><input id="password" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="<?= e(__('Lascia vuoto per ordinare come ospite')) ?>"></div></fieldset><?php endif ?>
    <?php if (Alien\Services\Modules::on('newsletter')): ?><label style="display:flex;gap:10px;align-items:flex-start;font-weight:400;margin-bottom:10px"><input type="checkbox" name="newsletter" value="1" style="width:auto;margin-top:5px"><span><?= e(__('Voglio ricevere novità e offerte via email (puoi annullare quando vuoi)')) ?></span></label><?php endif ?>
    <label style="display:flex;gap:10px;align-items:flex-start;font-weight:400"><input type="checkbox" name="terms" value="1" required style="width:auto;margin-top:5px"><span><?= __('Accetto i <a href="%s" target="_blank">termini e condizioni</a> e la <a href="%s" target="_blank">privacy policy</a>', e(url('pages/termini-e-condizioni')), e(url('pages/privacy-policy'))) ?></span></label>
  </div>
  <aside class="summary">
    <h2><?= e(__('Riepilogo')) ?></h2>
    <?php foreach ($lines as $l): ?><div style="display:flex;gap:12px;align-items:center;margin-bottom:12px"><img src="<?= e(upload_url($l['image'], 400)) ?>" alt="" width="52" height="52" style="width:52px;height:52px;object-fit:cover;border-radius:6px"><div style="flex:1;font-size:.9rem"><?= e($l['name']) ?><?php if ($l['label']): ?><small style="display:block;color:var(--muted)"><?= e($l['label']) ?></small><?php endif ?>× <?= (int)$l['qty'] ?></div><strong><?= e(money($l['total'])) ?></strong></div><?php endforeach ?>
    <?= partial('totals', ['totals' => $totals]) ?>
    <button class="btn btn-block" type="submit" style="margin-top:16px" <?= !$gateways ? 'disabled' : '' ?>><?= e(__('Conferma ordine')) ?> · <?= e(money($totals['total'])) ?></button>
  </aside>
</form>
