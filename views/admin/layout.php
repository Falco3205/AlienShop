<?php
use Alien\Core\Csrf;
$nav = [
    'dashboard' => ['admin', __('Dashboard'), 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
    'orders' => ['admin/orders', __('Ordini'), 'M6 6h15l-1.6 9H7.6L6 3H3'],
    'products' => ['admin/products', __('Prodotti'), 'M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8'],
    'categories' => ['admin/categories', __('Categorie'), 'M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z'],
    'customers' => ['admin/customers', __('Clienti'), 'M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2M9 11a4 4 0 100-8 4 4 0 000 8z'],
    'coupons' => ['admin/coupons', __('Sconti'), 'M20 12l-8 8-9-9V3h8zM7.5 7.5h.01'],
    'pages' => ['admin/pages', __('Pagine e blog'), 'M14 3H6a2 2 0 00-2 2v14a2 2 0 002 2h12a2 2 0 002-2V9zM14 3v6h6'],
    'import' => ['admin/import', __('Import / Export'), 'M12 3v12M7 10l5 5 5-5M5 21h14'],
    'themes' => ['admin/themes', __('Temi'), 'M12 3a9 9 0 100 18c1.7 0 2-1 1.5-2-.6-1.2.2-2.5 1.6-2.5H17a4 4 0 004-4c0-5-4-9.5-9-9.5z'],
    'payments' => ['admin/payments', __('Pagamenti'), 'M2 7h20v12H2zM2 11h20'],
    'shipping' => ['admin/shipping', __('Spedizioni'), 'M1 3h15v13H1zM16 8h4l3 3v5h-7zM6 19.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3zM18 19.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z'],
    'redirects' => ['admin/redirects', __('Redirect 301'), 'M5 12h14M13 6l6 6-6 6'],
    'settings' => ['admin/settings', __('Impostazioni'), 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 11-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 110-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 114 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 110 4h-.1a1.7 1.7 0 00-1.5 1z'],
];
?>
<!doctype html>
<html lang="<?= e(Alien\Core\Lang::locale()) ?>">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? __('Admin')) ?> — <?= e(setting('store_name', 'AlienShop')) ?></title>
<link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>?v=<?= ALIEN_VERSION ?>">
<meta name="csrf" content="<?= e(Csrf::token()) ?>">
</head>
<body>
<aside class="side" id="side">
  <a class="brand" href="<?= e(url('admin')) ?>"><svg width="26" height="26" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#6c4cf5"/><path d="M16 6c4 0 7 3 7 7 0 5-7 13-7 13S9 18 9 13c0-4 3-7 7-7z" fill="#fff"/><circle cx="16" cy="13" r="2.6" fill="#6c4cf5"/></svg> AlienShop</a>
  <nav>
  <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
    <a href="<?= e(url($href)) ?>" class="<?= ($active ?? '') === $key ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="<?= $icon ?>"/></svg><?= e($label) ?></a>
  <?php endforeach ?>
  </nav>
  <div class="side-foot"><a href="<?= e(url()) ?>" target="_blank" rel="noopener">↗ <?= e(__('Vedi il negozio')) ?></a>
    <a href="<?= e(url('admin/profile')) ?>"><?= e(__('Profilo')) ?></a>
    <form method="post" action="<?= e(url('admin/logout')) ?>"><?= csrf_field() ?><button type="submit"><?= e(__('Esci')) ?></button></form></div>
</aside>
<div class="main">
  <header class="top"><button class="burger" type="button" onclick="document.getElementById('side').classList.toggle('open')" aria-label="menu">☰</button><h1><?= e($title ?? '') ?></h1><div class="top-actions"><?= $actions ?? '' ?></div></header>
  <div class="content">
    <?php foreach (pull_flash() as $f): ?><div class="alert <?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach ?>
    <?= $content ?>
  </div>
</div>
<script src="<?= e(asset('admin.js')) ?>?v=<?= ALIEN_VERSION ?>" defer></script>
</body>
</html>
