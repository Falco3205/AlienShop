<?php if ($tab !== 'payments'): ?>
<div class="tabs"><?php foreach (['general' => [__('Negozio'), 'admin/settings/general'], 'seo' => ['SEO', 'admin/settings/seo'], 'mail' => ['Email', 'admin/settings/mail']] as $k => [$l, $href]): ?><a class="<?= $tab === $k ? 'on' : '' ?>" href="<?= e(url($href)) ?>"><?= e($l) ?></a><?php endforeach ?></div>
<?php endif ?>
<form method="post" action="<?= e(url($tab === 'payments' ? 'admin/payments' : 'admin/settings/' . $tab)) ?>"><?= csrf_field() ?>
<?php foreach ($groups as $label => $fields): ?>
  <div class="card" style="max-width:820px"><h2><?= e($label) ?></h2>
  <?php foreach ($fields as $f):
      [$key, $lab, $type] = $f; $opts = $f[3] ?? []; $help = $f[4] ?? '';
      $val = setting($key, $key === 'prices_include_tax' || $key === 'indexnow_enabled' ? '1' : '');
      if ($type === 'checkbox') { echo a_check($key, $lab, (string)setting($key, in_array($key, ['prices_include_tax', 'indexnow_enabled'], true) ? '1' : '0') === '1'); }
      elseif ($type === 'select') { echo a_select($key, $lab, $opts, setting($key, array_key_first($opts))); }
      elseif ($type === 'textarea') { echo a_textarea($key, $lab, (string)$val, 3, $help); }
      elseif ($type === 'password') { echo a_input($key, $lab, '', 'password', ['autocomplete' => 'new-password', 'placeholder' => setting($key) ? '••••••••' : ''], $help); }
      else { echo a_input($key, $lab, (string)$val, $type, is_array($opts) ? $opts : [], $help); }
  endforeach ?></div>
<?php endforeach ?>
<div class="sticky-save"><button class="btn" type="submit"><?= e(__('Salva impostazioni')) ?></button></div>
</form>
<?= $extra ?>
