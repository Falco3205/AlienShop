<?php if (!empty($errors)): ?><div class="alert error"><ul style="margin:0;padding-left:18px"><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach ?></ul></div><?php endif ?>
<div class="grid2"><div class="card"><form method="post" action="<?= e(url('nodes/new')) ?>"><?= csrf_field() ?>
<?= a_input('name', 'Nome del server', $in['name'], 'text', ['required' => true, 'placeholder' => 'VPS backend']) ?>
<?= a_select('role', 'Ruolo', $roles, $in['role']) ?>
<?= a_input('address', 'Indirizzo IP pubblico', $in['address'], 'text', ['placeholder' => '203.0.113.10'], 'Per un frontend è l\'IP a cui i domini dei clienti devono puntare. Per un backend, se serve ai proxy fidati.') ?>
<?= a_input('upstream', 'Indirizzo del backend visto dal frontend', $in['upstream'], 'text', ['placeholder' => 'http://10.0.0.2:80'], 'Solo per i backend: URL con cui il frontend raggiunge Nginx del backend attraverso il tunnel (IP privato o hostname).') ?>
<?= a_input('trusted', 'IP da cui il backend vede arrivare il frontend', $in['trusted'], 'text', ['placeholder' => '10.0.0.0/24'], 'Solo backend. Serve per leggere l\'IP vero dei visitatori (limiti di accesso, statistiche). Con un tunnel è l\'indirizzo del tunnel, non l\'IP pubblico del frontend. Se vuoto si usa l\'IP pubblico del frontend.') ?>
<?= a_input('hestia_user', 'Utente Hestia che ospita i siti', $in['hestia_user'], 'text', ['required' => true, 'placeholder' => 'falco3205']) ?>
<button class="btn" type="submit">Continua</button></form></div>
<div class="card"><h2>Come funziona</h2><ul style="line-height:1.8;padding-left:18px"><li>Ottieni un comando da incollare sul server (come root).</li><li>Il comando installa l'agente, che ogni minuto chiede all'hub se ci sono lavori da fare.</li><li>Nessuna porta da aprire e nessuna password di Hestia nell'hub.</li></ul></div></div>
