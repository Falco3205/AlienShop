<div class="hub">
<?php foreach ($modules as $id => $m): $on = Alien\Services\Modules::on($id); ?>
  <div class="card" style="margin:0;display:flex;flex-direction:column;gap:8px;<?= $on ? 'border-color:var(--primary)' : '' ?>">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:10px"><strong style="font-size:1.05rem"><?= $m['icon'] ?> <?= e($m['name']) ?></strong>
      <form method="post" action="<?= e(url('admin/modules/' . $id . '/toggle')) ?>"><?= csrf_field() ?><button class="switch <?= $on ? 'on' : '' ?>" type="submit" role="switch" aria-checked="<?= $on ? 'true' : 'false' ?>" aria-label="<?= e($m['name']) ?>"><i></i></button></form></div>
    <span class="muted" style="font-size:.88rem;flex:1"><?= e($m['text']) ?></span>
    <?php if ($on && $m['link']): ?><a class="btn sec sm" style="align-self:flex-start" href="<?= e(url($m['link'])) ?>"><?= e(__('Apri')) ?> →</a><?php endif ?>
  </div>
<?php endforeach ?>
</div>
<div class="card" style="margin-top:20px"><h2>⏱ <?= e(__('Attività automatiche')) ?></h2>
  <p class="muted"><?= e(__('Invio email, promemoria e scadenze si eseguono da soli mentre il sito è visitato: non devi configurare nulla sul server.')) ?> <?= e($cronLast ? __('Ultima esecuzione: %s', a_dt($cronLast)) : __('Non ancora eseguite.')) ?>
  <br><?= e(__('Per più precisione puoi usare il cron del tuo hosting:')) ?> <code>* * * * * php <?= e(ROOT) ?>/bin/console cron:run</code></p></div>
