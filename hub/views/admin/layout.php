<?php
use Alien\Core\Csrf;
$nav = [
    'dashboard' => ['', 'Panoramica', 'M3 12l9-9 9 9M5 10v10h5v-6h4v6h5V10'],
    'shops' => ['shops', 'Negozi', 'M6 6h15l-1.6 9H7.6L6 3H3M9 20a1 1 0 100-2 1 1 0 000 2zM18 20a1 1 0 100-2 1 1 0 000 2z'],
    'nodes' => ['nodes', 'Server', 'M3 5h18v6H3zM3 13h18v6H3zM7 8h.01M7 16h.01'],
    'jobs' => ['jobs', 'Attività', 'M12 8v4l3 2M12 21a9 9 0 100-18 9 9 0 000 18z'],
    'settings' => ['settings', 'Impostazioni', 'M12 15a3 3 0 100-6 3 3 0 000 6zM12 2v3M12 19v3M2 12h3M19 12h3'],
];
?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= e($title ?? 'Hub') ?> — AlienShop Hub</title>
<link rel="icon" href="<?= e(url('assets/favicon.svg')) ?>" type="image/svg+xml">
<link rel="stylesheet" href="<?= e(url('assets/admin.css')) ?>?v=<?= ALIEN_VERSION ?>">
<meta name="csrf" content="<?= e(Csrf::token()) ?>">
</head>
<body>
<aside class="side" id="side">
  <a class="brand" href="<?= e(url()) ?>"><svg width="26" height="26" viewBox="0 0 32 32"><circle cx="16" cy="16" r="16" fill="#6c4cf5"/><path d="M16 6c4 0 7 3 7 7 0 5-7 13-7 13S9 18 9 13c0-4 3-7 7-7z" fill="#fff"/><circle cx="16" cy="13" r="2.6" fill="#6c4cf5"/></svg> AlienShop Hub</a>
  <nav>
  <?php foreach ($nav as $key => [$href, $label, $icon]): ?>
    <a href="<?= e(url($href)) ?>" class="<?= ($active ?? '') === $key ? 'on' : '' ?>"><svg viewBox="0 0 24 24"><path d="<?= $icon ?>"/></svg><?= e($label) ?></a>
  <?php endforeach ?>
  </nav>
  <div class="side-foot"><form method="post" action="<?= e(url('logout')) ?>"><?= csrf_field() ?><button type="submit">Esci</button></form></div>
</aside>
<div class="main">
  <header class="top"><button class="burger" type="button" onclick="document.getElementById('side').classList.toggle('open')" aria-label="menu">☰</button>
    <div class="top-title"><h1><?= e($title ?? '') ?></h1><?php if (!empty($subtitle)): ?><p><?= e($subtitle) ?></p><?php endif ?></div>
    <div class="top-actions"><?= $actions ?? '' ?></div></header>
  <div class="content">
    <?php foreach (pull_flash() as $f): ?><div class="alert <?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach ?>
    <?= $content ?>
  </div>
</div>
<script src="<?= e(url('assets/admin.js')) ?>?v=<?= ALIEN_VERSION ?>" defer></script>
</body>
</html>
