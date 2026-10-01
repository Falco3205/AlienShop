<?php
use Alien\Core\Lang;
use Alien\Services\Seo;
use Alien\Services\Themes;

$themeSlug = Alien\Core\View::theme();
$assets = Themes::assets($previewTheme ?? null);
$store = (string)setting('store_name', 'Shop');
$logo = setting('logo');
?>
<!doctype html>
<html lang="<?= e(Lang::locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?= Seo::head() ?>
<link rel="preload" href="<?= e(asset($assets['css'])) ?>" as="style">
<link rel="stylesheet" href="<?= e(asset($assets['css'])) ?>">
<?php if ($fav = setting('favicon')): ?><link rel="icon" href="<?= e(upload_url($fav)) ?>"><?php else: ?><link rel="icon" href="<?= e(asset('favicon.svg')) ?>" type="image/svg+xml"><?php endif ?>
<?= Alien\Services\Analytics::render() ?>
<?= setting('head_code', '') ?>
</head>
<body class="<?= e(Themes::layoutClasses($themeSlug)) ?>">
<a class="skip" href="#main"><?= e(__('Vai al contenuto')) ?></a>
<?php if ($bar = setting('announcement')): ?><div class="announce"><?= e($bar) ?></div><?php endif ?>
<header class="site-header">
  <div class="container header-inner">
    <button class="icon-btn menu-toggle" type="button" aria-label="<?= e(__('Menu')) ?>"><svg viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
    <a class="logo" href="<?= e(url()) ?>">
      <?php if ($logo): ?><img src="<?= e(upload_url($logo)) ?>" alt="<?= e($store) ?>" height="44"><?php else: ?><?= e($store) ?><?php endif ?>
    </a>
    <nav class="nav" aria-label="<?= e(__('Principale')) ?>">
      <?php foreach ($nav['categories'] as $c): ?>
        <?php if ($c['children']): ?>
          <div class="has-sub"><a href="<?= e(url('collections/' . $c['slug'])) ?>"><?= e($c['name']) ?></a>
            <div class="sub"><?php foreach ($c['children'] as $s): ?><a href="<?= e(url('collections/' . $s['slug'])) ?>"><?= e($s['name']) ?></a><?php endforeach ?></div></div>
        <?php else: ?><a href="<?= e(url('collections/' . $c['slug'])) ?>"><?= e($c['name']) ?></a><?php endif ?>
      <?php endforeach ?>
      <?php foreach ($nav['pages'] as $p): ?><a href="<?= e(url('pages/' . $p['slug'])) ?>"><?= e($p['title']) ?></a><?php endforeach ?>
      <?php if ($nav['blog']): ?><a href="<?= e(url('blog')) ?>"><?= e(__('Blog')) ?></a><?php endif ?>
    </nav>
    <div class="actions">
      <button class="icon-btn" type="button" data-search-toggle aria-label="<?= e(__('Cerca')) ?>"><svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg></button>
      <?php if (Alien\Services\Modules::on('wishlist')): ?><a class="icon-btn" href="<?= e(url('wishlist')) ?>" aria-label="<?= e(__('Preferiti')) ?>"><svg viewBox="0 0 24 24"><path d="M12 21s-7-4.4-9.3-9A5.2 5.2 0 0112 6a5.2 5.2 0 019.3 6c-2.3 4.6-9.3 9-9.3 9z"/></svg><span class="badge wish-badge"></span></a><?php endif ?>
      <a class="icon-btn" href="<?= e(url('account')) ?>" aria-label="<?= e(__('Account')) ?>"><svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-7 8-7s8 2.6 8 7"/></svg></a>
      <a class="icon-btn" href="<?= e(url('cart')) ?>" aria-label="<?= e(__('Carrello')) ?>"><svg viewBox="0 0 24 24"><path d="M6 6h15l-1.6 9H7.6L6 3H3"/><circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/></svg><span class="badge cart-badge"></span></a>
    </div>
  </div>
  <div class="search-form"><div class="container"><form action="<?= e(url('search')) ?>" method="get" role="search"><input type="search" name="q" placeholder="<?= e(__('Cerca prodotti…')) ?>" aria-label="<?= e(__('Cerca')) ?>"><button class="btn" type="submit"><?= e(__('Cerca')) ?></button></form></div></div>
</header>
<main id="main"><div class="container">
<?php foreach (pull_flash() as $f): ?><div class="alert alert-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endforeach ?>
<?= $content ?>
</div></main>
<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div><h4><?= e($store) ?></h4><p><?= e(setting('footer_text', setting('store_description', ''))) ?></p>
        <?php foreach (array_filter(array_map('trim', explode(',', (string)setting('social_links', '')))) as $s): ?><a href="<?= e($s) ?>" rel="noopener me" target="_blank"><?= e(parse_url($s, PHP_URL_HOST) ?: $s) ?></a> <?php endforeach ?></div>
      <div><h4><?= e(__('Negozio')) ?></h4><ul>
        <li><a href="<?= e(url('collections/all')) ?>"><?= e(__('Tutti i prodotti')) ?></a></li>
        <?php foreach (array_slice($nav['categories'], 0, 5) as $c): ?><li><a href="<?= e(url('collections/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></li><?php endforeach ?></ul></div>
      <div><h4><?= e(__('Informazioni')) ?></h4><ul>
        <?php foreach ($nav['footer_pages'] as $p): ?><li><a href="<?= e(url('pages/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></li><?php endforeach ?>
        <li><a href="<?= e(url('account')) ?>"><?= e(__('Il mio account')) ?></a></li>
        <?php if (Alien\Services\Analytics::enabled() && Alien\Services\Analytics::consentRequired()): ?><li><a href="#" data-cookie-settings><?= e(__('Preferenze cookie')) ?></a></li><?php endif ?></ul></div>
    </div>
    <?php if (Alien\Services\Modules::on('newsletter')): ?>
    <form id="newsletter-form" class="newsletter" method="post" action="<?= e(url('newsletter/subscribe')) ?>"><strong><?= e(__('Iscriviti alla newsletter')) ?></strong>
      <input type="email" name="email" required placeholder="<?= e(__('La tua email')) ?>" aria-label="Email"><div style="position:absolute;left:-9999px" aria-hidden="true"><input name="website" tabindex="-1" autocomplete="off"></div><button class="btn btn-sm" type="submit"><?= e(__('Iscrivimi')) ?></button><span id="newsletter-msg" role="status"></span></form>
    <?php endif ?>
    <div class="copyright"><span>© <?= date('Y') ?> <?= e($store) ?></span><span><?= e(setting('footer_note', '')) ?></span></div>
  </div>
</footer>
<?php if (Alien\Services\Analytics::enabled() && Alien\Services\Analytics::consentRequired()): ?>
<div id="cookie-banner" class="cookie" role="dialog" aria-label="<?= e(__('Cookie')) ?>" hidden>
  <p><?= e(__('Usiamo cookie di analisi anonimi per migliorare il negozio.')) ?> <a href="<?= e(url('pages/privacy-policy')) ?>"><?= e(__('Privacy policy')) ?></a></p>
  <div><button type="button" class="btn btn-outline btn-sm" data-consent="denied"><?= e(__('Rifiuta')) ?></button> <button type="button" class="btn btn-sm" data-consent="granted"><?= e(__('Accetta')) ?></button></div></div>
<?php endif ?>
<div id="toast" class="toast" role="status" aria-live="polite"></div>
<script src="<?= e(asset($assets['js'])) ?>" defer></script>

<?= setting('footer_code', '') ?>
</body>
</html>
