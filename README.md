# AlienShop

E-commerce leggero e veloce in PHP, installabile con un wizard, con le funzionalità essenziali di Shopify:
catalogo con attributi e varianti di prezzo, 10 temi, import/export WooCommerce e Shopify, pagamenti Stripe/PayPal/bonifico/contrassegno e SEO tecnico completo.

Nessuna dipendenza (niente Composer, niente Node): si carica via FTP o si clona su qualsiasi hosting con PHP 8.1+.

## Requisiti

PHP 8.1+ con `pdo_sqlite` **o** `pdo_mysql`, `mbstring`, `gd` (con WebP), `curl`, `openssl`; consigliata `intl`. Apache (mod_rewrite) o Nginx.

## Installazione

1. Carica i file sul server. Il document root deve puntare a `public/` (con Apache su hosting condiviso funziona anche dalla radice grazie al `.htaccess` incluso). Per Nginx vedi `docs/nginx.conf`.
2. Apri `https://tuodominio/install` e segui il wizard: requisiti → database (SQLite o MySQL) → negozio → amministratore → tema.
3. Accedi a `/admin`.

Dopo l'installazione `/install` non è più raggiungibile (`storage/installed.lock`).

## Installazione automatica (riga di comando) e Hestia Control Panel

Il wizard ha un equivalente non interattivo, utile per script e pannelli:

```bash
php bin/console install --url=https://shop.example.com --admin-email=tu@example.com \
  --store-name="Il mio negozio" --theme=aurora --demo=0 \
  --db=mysql --db-host=localhost --db-name=alienshop --db-user=alienshop
# password e segreti via variabili d'ambiente (non restano nella cronologia dei comandi):
ALIEN_ADMIN_PASSWORD='password-lunga' ALIEN_DB_PASS='password-db' php bin/console install ...
```

Senza `--db=mysql` usa SQLite. Altre opzioni: `--lang=it|en`, `--currency=EUR`, `--country=IT`, `--tax-rate=22`, `--prices-include-tax=1`, `--tagline`, `--store-email`, `--admin-name`.

### Hestia Control Panel

`docs/hestia/install-alienshop.sh` automatizza tutto su un server con Hestia (come root): crea il dominio web se manca, scarica AlienShop da GitHub nella cartella del sito, crea il database MySQL, imposta un template Nginx con document root su `public/`, blocca l'esecuzione di PHP in `uploads/`, emette il certificato Let's Encrypt, esegue `bin/console install` e aggiunge il cron.

```bash
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/docs/hestia/install-alienshop.sh -o install-alienshop.sh
sudo bash install-alienshop.sh UTENTE_HESTIA shop.example.com tu@example.com "Il mio negozio"
```

La password dell'amministratore viene generata e stampata a fine installazione (oppure imposti `ALIEN_ADMIN_PASSWORD`). Il dominio deve già puntare al server per il certificato; con `SKIP_SSL=1` salti HTTPS. Lo script si ferma se la cartella del sito contiene già dei file. È pensato per Hestia con Nginx + PHP-FPM e PHP 8.1+ come CLI predefinito; lo script non è stato provato su un server Hestia reale, quindi al primo uso controlla l'output.

### Aggiornamenti automatici da GitHub

Ogni modifica pubblicata su GitHub arriva al negozio dal pannello (**Admin → Aggiornamenti**, un clic su "Aggiorna ora") o da riga di comando:

```bash
php bin/console update --check     # controlla soltanto
php bin/console update             # aggiorna (backup del database, migrazioni, cache)
php bin/console update:rollback    # torna alla versione precedente
```

- Funziona con **Git** (se il sito è stato clonato) oppure scaricando lo **ZIP** da GitHub (hosting senza Git, richiede l'estensione `zip`).
- Non toccano mai `config/config.php`, `storage/`, `public/uploads/` (dati, immagini, configurazione). Le modifiche fatte a mano ai file tracciati bloccano l'aggiornamento Git per non perderle.
- **Automatico**: attiva "Aggiorna automaticamente" e il negozio controlla GitHub ogni giorno. Per l'aggiornamento **immediato** a ogni push, su GitHub aggiungi un webhook (Settings → Webhooks) con URL `https://tuodominio/webhooks/github`, content type `application/json` e il secret mostrato nella pagina Aggiornamenti.
- Repository privato: inserisci un token GitHub con accesso in sola lettura. Il sito, in Git, usa invece le credenziali del `git remote`.
- Il server web deve poter scrivere nei file del sito (su Hestia/XAMPP è già così). Su un VPS con file di proprietà di un altro utente, esegui `update` da CLI con quell'utente.

## Più negozi e due VPS: AlienShop Hub

Per gestire i negozi dei tuoi clienti da un unico pannello, con backend e frontend su VPS diverse.

```
                       ┌──────────────────────── Hub (pannello principale) ───────────────────────┐
                       │  falconefabio.it/alienshop : crea negozi, vede incassi, ordini, errori    │
                       └───────▲──────────────────────────────▲──────────────────────▲────────────┘
                               │ lavori (ogni minuto)          │ lavori               │ dati firmati (HMAC)
   clienti ──HTTPS──▶  VPS FRONTEND (agente) ──tunnel──▶  VPS BACKEND (agente + Hestia)  ◀── l'hub interroga ogni negozio
                       Nginx: TLS, cache 60 s HTML,         PHP + database + admin di ogni negozio
                       cache asset, serve la cache se       (cartella principale del dominio
                       il backend è giù                      oppure sottocartella)
```

- **Hub**: app a parte (`hub/`) con il suo database. Mostra incassi di oggi/7/30 giorni di tutti i negozi, ordini da spedire, scorte, errori, versioni; segnala i negozi che non rispondono; apre l'admin di un negozio con un clic (accesso monouso) e lo aggiorna.
- **Agente** (`node/alienshop-node`): gira su ogni VPS ogni minuto, chiede all'hub se ci sono lavori e li esegue con i comandi di Hestia (crea dominio, database, certificato, clona il negozio, lo installa). L'hub **non** ha password né API key di Hestia e non si collega in SSH: sono i server a collegarsi a lui. L'agente esegue solo un elenco fisso di operazioni e controlla ogni parametro (dominio, utente, cartella, repository…).
- **Frontend**: è un proxy Nginx (template Hestia `alienshop-edge`) davanti al backend: certificato HTTPS, cache dei file statici e delle pagine pubbliche (60 s, mai per amministratori, carrelli o account), e se il backend è irraggiungibile continua a servire l'ultima pagina in cache. Il negozio riceve l'IP vero del visitatore tramite i "proxy fidati". Non è una riscrittura "headless": il negozio resta un'unica applicazione, quindi tutto ciò che c'è già continua a funzionare.

### 1. Installa l'hub (una volta)
Sul server dove vuoi il pannello (consigliato: la VPS backend), con il dominio già creato in Hestia:

```bash
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/hub/install.sh -o install-hub.sh
sudo bash install-hub.sh --user falco3205 --domain falconefabio.it --path alienshop --admin-email tu@example.com
```
Installa l'hub in `/home/falco3205/web/falconefabio.it/public_html/alienshop`, lo rende raggiungibile su `https://falconefabio.it/alienshop/` (senza esporre gli altri file) e stampa la password.

> **Consigliato per la sicurezza**: un sottodominio dedicato (`--domain hub.falconefabio.it --root`, dopo averlo creato in Hestia). Un pannello in una cartella dello stesso dominio di un altro sito (WordPress, ecc.) ha la stessa "origine": una falla in quel sito potrebbe agire sul pannello. Subito dopo il primo accesso attiva la verifica in due passaggi (*Sicurezza*).

### 2. Collega le VPS
Nel pannello: **Server → Collega un server**. Per il backend indica anche l'indirizzo con cui il frontend lo raggiunge attraverso il tunnel (es. `http://10.0.0.2:80`: IP privato o hostname che arriva a Nginx del backend). Il pannello mostra un comando da incollare **come root** sulla VPS: installa l'agente e i template Hestia. Fai lo stesso per il frontend.

### 3. Crea un negozio
**Negozi → Nuovo negozio**: dominio, nome, email dell'amministratore, tema e lingua; scegli se installarlo nella **cartella principale** del dominio o in una **sottocartella** (il resto del sito resta com'è), e se pubblicarlo **direttamente** o **tramite il frontend**. Entro un minuto l'agente lo installa; il pannello mostra l'esito, il registro dell'installazione e la password iniziale. Con la pubblicazione tramite frontend il dominio deve puntare (record A) all'IP del frontend.

**Dominio creato direttamente in Hestia**: sul backend, scegli il template web `alienshop` quando crei il dominio: dopo pochi secondi il negozio viene installato da solo e compare nell'hub (con l'email amministratore predefinita delle Impostazioni).

### Aggiornamenti e sicurezza
- Ogni negozio si aggiorna dall'hub (pulsante nella scheda) o dal proprio pannello; l'hub stesso con `php hub/bin/hub update`.
- L'hub parla ai negozi con richieste firmate (HMAC-SHA256 con segreto per negozio, finestra di 5 minuti); gli agenti si autenticano con un token per server (nel database solo l'hash). Chi controlla l'hub può eseguire quelle operazioni sulle VPS: proteggilo con una password lunga e HTTPS.
- Certificati: sul frontend Let's Encrypt funziona se il dominio punta direttamente al frontend (se usi Cloudflare come proxy "arancione" davanti al frontend, usa il certificato Cloudflare o la modalità DNS-only per il rilascio).
- Test: `php tests/run.php` (negozio), `php tests/hub.php` (hub), `php tests/node.php` (agente, in modalità prova: non esegue nulla sul server).

**Non provato su un server Hestia reale**: i comandi `v-*`, i template Nginx e il tunnel Cloudflare sono scritti dalla documentazione di Hestia e verificati solo in modalità prova (comandi generati e controllati). Il resto è provato con un'installazione locale completa (hub, negozio, agente simulato): creazione negozio, lavori, raccolta dati, accesso con un clic, aggiornamento, firme e proxy fidati. Alla prima installazione reale guarda il registro dell'attività nel pannello (**Attività**).

## Installazione per tipo di hosting

Regole comuni: PHP **8.1+** con le estensioni `pdo_sqlite` o `pdo_mysql`, `mbstring`, `gd`, `curl`, `openssl`, `sodium`, `dom` (e `zip`, `intl` consigliate), HTTPS attivo, e il document root sul dominio. Il wizard `/install` segnala cosa manca. Le procedure sotto sono indicative: i nomi dei menu cambiano tra versioni dei pannelli e non sono state provate su ciascuno.

### Hestia Control Panel
Installazione completamente automatica: vedi la sezione "Hestia Control Panel" qui sopra (`docs/hestia/install-alienshop.sh`).

### cPanel
1. **PHP**: *Select PHP Version* (o *MultiPHP Manager*) → scegli 8.1+ e attiva le estensioni elencate sopra.
2. **File**, due strade:
   - *Con Git*: *Git™ Version Control* → *Create* → clone URL `https://github.com/Falco3205/AlienShop.git`, percorso `public_html/alienshop` (o la cartella del dominio). Gli aggiornamenti si fanno poi da *Admin → Aggiornamenti* o con *Update from Remote*.
   - *Senza Git*: scarica lo ZIP da GitHub (*Code → Download ZIP*), caricalo con *File Manager* nella cartella del dominio e usa *Extract*; sposta il contenuto della cartella `AlienShop-main` nella cartella del dominio.
3. **Document root**: la soluzione migliore è puntare il dominio a `.../alienshop/public` (*Domains → Manage → Document Root*). Se non puoi cambiarlo, lascia i file nella radice del dominio: il `.htaccess` incluso instrada tutto verso `public/` e blocca `app/`, `config/`, `storage/`.
4. **Database** (facoltativo, altrimenti SQLite): *MySQL® Database Wizard* → crea database e utente con tutti i privilegi. Nel wizard di AlienShop: host `localhost`, nome e utente completi (con il prefisso dell'account, es. `account_shop`).
5. Apri `https://tuodominio/install`. Poi *Cron Jobs* (facoltativo): `* * * * * /usr/local/bin/php /home/ACCOUNT/public_html/alienshop/bin/console cron:run`.

### Plesk
1. *Websites & Domains → PHP Settings*: versione 8.1+ ed estensioni.
2. *Git*: *Add Repository* → URL del repository, cartella nel dominio; oppure *File Manager* con lo ZIP come per cPanel.
3. *Hosting Settings → Document root*: imposta `alienshop/public`.
4. *Databases → Add Database* (facoltativo). Poi `/install`. Cron: *Scheduled Tasks → Run a PHP script* con `bin/console` e argomento `cron:run`.

### DirectAdmin
*PHP Version Selector* per versione ed estensioni; carica lo ZIP con *File Manager* (o via SSH `git clone`); *Domain Setup* per il document root su `public`; *MySQL Management* per il database; poi `/install`.

### CloudPanel, aaPanel, CyberPanel e simili (VPS)
Hanno tutti il document root configurabile e PHP-FPM: crea un sito PHP 8.1+ con root su `.../public`, esegui `git clone https://github.com/Falco3205/AlienShop.git` nella cartella del sito (come utente del sito, così gli aggiornamenti da pannello possono scrivere), crea il database dal pannello e apri `/install`. Con Nginx usa le regole di `docs/nginx.conf` (`try_files $uri /index.php?$query_string;`); con OpenLiteSpeed/Apache basta il `.htaccess` incluso.

### Hosting condiviso con solo FTP
Scompatta lo ZIP in locale, carica tutto via FTP nella cartella del dominio (con il `.htaccess` incluso funziona anche dalla radice) e apri `/install`. Scegli SQLite se l'hosting non offre MySQL. Per gli aggiornamenti usa *Admin → Aggiornamenti* (metodo ZIP, serve l'estensione `zip`) oppure ricarica i file da FTP.

### XAMPP / MAMP / Laragon (test in locale)
Clona il repository in `htdocs` e crea un virtual host che punta a `public/` (non usare una sottocartella tipo `localhost/alienshop`). Con Laragon basta mettere la cartella in `www`: crea da solo `alienshop.test`. Abilita le estensioni in `php.ini`, scegli SQLite nel wizard e imposta le email su "Solo log" (*Admin → Impostazioni → Email*), perché `mail()` in locale non invia.

### Docker
Non è incluso un `Dockerfile`: l'applicazione non ha dipendenze, quindi basta un'immagine `php:8.3-apache` con le estensioni `gd`, `intl`, `zip`, `pdo_mysql`, `sodium` e `DocumentRoot` su `public/`.

### Dopo l'installazione, su qualunque pannello
- Imposta le email (SMTP) in *Admin → Impostazioni → Email* e prova l'invio.
- Attiva HTTPS e verifica che l'indirizzo del sito nelle impostazioni sia quello `https://`.
- Se `config/config.php` o `storage/` non risultano scrivibili, dai i permessi all'utente PHP (`chmod -R u+rwX storage config public/uploads`).

## Installazione su VPS da GitHub (Ubuntu/Debian + Nginx)

Comandi da eseguire sul server come utente con `sudo`. Sostituisci `shop.example.com` con il tuo dominio.

```bash
# 1. Pacchetti (PHP 8.3 con le estensioni richieste, Nginx, Git; MySQL è facoltativo, SQLite funziona senza)
sudo apt update
sudo apt install -y nginx git unzip php-fpm php-cli php-sqlite3 php-mysql php-mbstring php-gd php-curl php-intl php-xml php-zip
# opzionale: sudo apt install -y mariadb-server

# 2. Scarica il pacchetto da GitHub
sudo git clone https://github.com/Falco3205/AlienShop.git /var/www/alienshop
sudo chown -R www-data:www-data /var/www/alienshop

# 3. Nginx: copia la configurazione inclusa e adatta dominio e versione di PHP
sudo cp /var/www/alienshop/docs/nginx.conf /etc/nginx/sites-available/alienshop
sudo sed -i 's/example.com/shop.example.com/' /etc/nginx/sites-available/alienshop
ls /run/php/            # controlla il nome del socket e correggi php8.3-fpm.sock se diverso
sudo ln -s /etc/nginx/sites-available/alienshop /etc/nginx/sites-enabled/alienshop
sudo nginx -t && sudo systemctl reload nginx

# 4. HTTPS gratuito con Let's Encrypt
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d shop.example.com
```

Poi apri `https://shop.example.com/install` e completa il wizard. Se usi MySQL, crea prima database e utente:

```bash
sudo mysql -e "CREATE DATABASE alienshop CHARACTER SET utf8mb4; CREATE USER 'alienshop'@'localhost' IDENTIFIED BY 'una-password-forte'; GRANT ALL ON alienshop.* TO 'alienshop'@'localhost';"
```

Nel wizard scegli MySQL, host `localhost`, database/utente/password appena creati.

Per caricare file grandi (immagini, CSV di import) alza i limiti in `/etc/php/8.3/fpm/php.ini` (`upload_max_filesize = 64M`, `post_max_size = 64M`) e in Nginx (`client_max_body_size 64M;` nel blocco `server`), poi `sudo systemctl restart php8.3-fpm nginx`.

### Importare i prodotti da WooCommerce o Shopify sul VPS

Dal pannello: **Admin → Import / Export**. Per cataloghi grandi usa la riga di comando (nessun limite di tempo del browser):

```bash
scp prodotti.csv utente@shop.example.com:/tmp/prodotti.csv          # dal tuo computer
cd /var/www/alienshop
sudo -u www-data php bin/console import:woocommerce /tmp/prodotti.csv   # oppure import:shopify
sudo -u www-data php bin/console cache:clear
```

Le immagini vengono scaricate dagli URL del CSV; aggiungi `--no-images` per saltarle e `--no-update` per non aggiornare i prodotti già presenti. L'import è idempotente: rilanciarlo aggiorna invece di duplicare. Per esportare: `php bin/console export:woocommerce > woo.csv` (o `export:shopify`).

### Aggiornare all'ultima versione

```bash
cd /var/www/alienshop
sudo -u www-data git pull
sudo -u www-data php bin/console migrate
sudo -u www-data php bin/console cache:clear
```

`config/config.php`, `storage/` e `public/uploads/` non sono tracciati da Git e non vengono toccati. Fai un backup prima (`sudo -u www-data php bin/console backup:create`).

### Attività pianificate (facoltativo)

Funzionano anche senza cron; per più precisione: `echo '* * * * * www-data php /var/www/alienshop/bin/console cron:run' | sudo tee /etc/cron.d/alienshop`.

## Funzionalità

- **Contatti e tracciamento ordine**: pagine `/contact` e `/track` con anti-spam e limite di richieste.
- **Prodotti**: semplici o variabili, immagini multiple (ottimizzate in WebP, 3 dimensioni, `srcset`), categorie annidate, tag, marca, SKU, scorte, bozze, in evidenza.
- **Attributi e variazioni di prezzo**: per ogni attributo si elencano i valori con delta di prezzo (`XL|+2.00`). Le varianti sono generate da tutte le combinazioni; il prezzo è `prezzo base + delta` oppure un override per singola variante, con scorte e SKU per variante.
- **10 temi preinstallati**: Aurora, Midnight, Boutique, Minimal, Vivid, Nature, Tech, Luxe, Pastel, Brutalist. Anteprima, attivazione, colori, logo, hero e CSS personalizzati dal pannello.
- **Import/Export**: CSV WooCommerce e Shopify (anche da CLI, `php bin/console import:woocommerce file.csv`). Import idempotente: un secondo import aggiorna invece di duplicare.
- **Pagamenti**: Stripe Checkout (+ webhook firmato), PayPal Orders v2 (+ webhook verificato), Mollie (carte, iDEAL, Bancontact, Satispay…), bonifico, contrassegno. Nuovi gateway: aggiungi `app/Payments/XxxGateway.php` estendendo `Gateway`, viene rilevato da solo.
- **Ordini**: stati, tracking, storico eventi, email transazionali (PHP mail o SMTP), ripristino scorte su annullo/rimborso, coupon (percentuale/fisso/spedizione gratuita), metodi e costi di spedizione per paese, IVA inclusa o esclusa.
- **Clienti**: checkout ospite o con account, area ordini, profilo, recupero password via email.
- **Rimborsi**: dal pannello ordine, con rimborso automatico su Stripe e PayPal e ripristino delle scorte. Stampa packing slip.
- **Estensioni gratuite attivabili** (Admin → Estensioni, un interruttore ciascuna):
  - **Recensioni**: stelle e commenti con moderazione, badge "acquisto verificato", stelle su Google (`AggregateRating`), richiesta automatica di recensione dopo la spedizione, anti-spam (honeypot, limite per IP).
  - **Newsletter**: iscrizione con doppia conferma (footer e checkout), campagne in coda inviate a gruppi con link di disiscrizione, esportazione CSV.
  - **Carrelli abbandonati**: cattura l'email al checkout, promemoria automatico con link che ripristina il carrello e sconto facoltativo, statistiche di recupero.
  - **Fatture e ricevute PDF**: numerazione annuale progressiva, PDF generato internamente (nessuna libreria), invio automatico al cliente, esportazione CSV per il commercialista. Non sostituisce la fattura elettronica SDI.
  - **Lista dei desideri**, **Avvisami quando torna disponibile**, **Statistiche interne**.
- **Fatturazione elettronica italiana (SdI) e contabilità**, gratis e senza intermediari (Admin → Estensioni → "Fatturazione elettronica"; si configura con un wizard in 5 passi):
  - **Emissione**: genera la fattura XML FatturaPA 1.2.2 (TD01) e la nota di credito (TD04), **validata con lo schema XSD ufficiale** dell'Agenzia delle Entrate, numerazione progressiva condivisa con i PDF, IVA a un'aliquota, sconti, spedizione, bollo virtuale, natura per IVA 0%, clienti italiani ed esteri, privati e aziende (P.IVA, CF, codice destinatario, PEC). Il cliente la richiede al checkout ("Desidero la fattura") con controllo di P.IVA e codice fiscale.
  - **Invio**: via **PEC** (SMTP della tua casella) all'indirizzo di SdI, oppure manuale (scarichi l'XML). Copia di cortesia PDF al cliente. Nota di credito automatica quando un ordine viene rimborsato.
  - **Ricezione**: legge la PEC (client IMAP integrato, nessuna estensione PHP necessaria) ogni 10 minuti, aggiorna lo stato delle fatture con le notifiche di SdI (consegna, scarto con errori, mancata consegna, esito committente, decorrenza termini) e importa le **fatture dei fornitori** (XML e P7M firmati, anche dentro le buste PEC); in alternativa le carichi a mano (XML, P7M, ZIP).
  - **Contabilità interna**: registro vendite (fatture + note di credito + corrispettivi degli ordini senza fattura), registro acquisti (fatture fornitori + spese manuali), riepilogo IVA per aliquota con detraibilità, saldo IVA stimato, margine lordo, andamento annuale, scadenzario pagamenti (da pagare ai fornitori / da incassare dai clienti), classifica fornitori, esportazioni CSV e archivio ZIP degli XML per la conservazione.
  - Non sostituisce il commercialista: i dati contabili sono indicativi e la conservazione a norma (10 anni) va garantita, ad esempio con il servizio gratuito dell'Agenzia delle Entrate.
- **Backup** con un clic (database o database + immagini in ZIP) e ripristino da riga di comando; **Collaboratori** (più amministratori); filtri per attributo nelle categorie; esportazione ordini CSV.
- **Attività automatiche senza cron**: invio email in coda, promemoria, richieste di recensione e scadenza degli ordini online non pagati partono da sole durante le visite (opzionale: `* * * * * php bin/console cron:run`).
- **Admin semplice**: menu raggruppato, ricerca globale, dashboard con saluto, checklist di primi passi, "da fare" (ordini da spedire, scorte basse), andamento con confronto sul periodo precedente, stati ordine con azioni rapide ("Segna come spedito e avvisa il cliente"), stati vuoti con pulsanti guida, anteprima Google e contatori di caratteri nel SEO.
- **Statistiche interne** (senza cookie né dati personali): imbuto visite → carrello → checkout → ordini, prodotti più cliccati, più venduti, più messi nel carrello, nel carrello ma non comprati, visti ma mai comprati, ricerche dei clienti e ricerche senza risultati.
- **Wizard Google Analytics 4**: guida passo passo, validazione dell'ID, eventi e-commerce (`view_item`, `add_to_cart`, `begin_checkout`, `purchase`), banner di consenso cookie (Analytics parte solo dopo l'accettazione) e verifica dell'installazione.
- **Wizard pagine legali gratuito**: privacy policy (GDPR), cookie policy, termini e condizioni, resi e diritto di recesso (con modulo tipo), spedizioni, informazioni legali, in italiano o inglese, personalizzati con i tuoi dati e gli strumenti attivi (Stripe, PayPal, Analytics). Sono modelli generici: vanno riletti, non sostituiscono un legale.
- **Admin**: duplica prodotto, immagine principale, editor HTML con barra strumenti, profilo e cambio password, IVA per paese.
- **Pagine e blog**, redirect 301 manuali e CSV.

## SEO e performance

- HTML pre-renderizzato in cache su file per home, prodotti, categorie, pagine e blog (invalidata a ogni modifica dal pannello), con `ETag`/`304` e gzip: le pagine in cache non toccano il database.
- Un solo CSS e un solo JS (~3 KB) minificati con hash nel nome e cache di 1 anno; nessun framework, nessun font esterno.
- Immagini WebP responsive con `width`/`height`, `loading="lazy"` e `fetchpriority` sull'immagine principale.
- Title/meta description/canonical/Open Graph, JSON-LD `Product` (`Offer`/`AggregateOffer`), `BreadcrumbList`, `Organization`, `WebSite`+`SearchAction`, `BlogPosting`.
- `sitemap.xml` (indice con sitemap per pagine, categorie e prodotti con immagini), `robots.txt`, feed Google Merchant (`/feeds/google.xml`), codici di verifica Google/Bing, GA4, **IndexNow** per notificare Bing a ogni modifica di prodotto.
- **Log dei 404**: le pagine inesistenti più richieste compaiono in `Admin → Redirect 301` con creazione del redirect in un click.
- Paginazione `prev/next`, `noindex` automatico su filtri, ricerca, carrello e account.
- **Redirect 301 automatici**: cambiando lo slug di prodotti, categorie e pagine, o eliminandoli. Gli URL Shopify (`/products/handle`, `/collections/handle`, `/pages/handle`) restano identici; gli URL WooCommerce `/product/slug`, `/product-category/...`, `/shop` sono reindirizzati. Slash finale normalizzato con 301.

## Manutenzione

```
php bin/console orders:expire 48   # annulla gli ordini Stripe/PayPal non pagati dopo 48h (cron)
php bin/console cache:clear
php bin/console backup:create / backup:restore file.zip
php bin/console cron:run   # facoltativo, per più precisione
php bin/console migrate    # dopo un aggiornamento (avviene anche in automatico)
php bin/console user:password email@dominio.it nuova-password   # recupero accesso admin
php tests/run.php                  # suite di test
```

## Sicurezza

Verifica in due passaggi per gli amministratori, blocco dei tentativi per IP e per account, sessioni con scadenza, CSRF, Content-Security-Policy (rigida e con nonce nell'admin), query preparate, escape e sanitizzazione dell'HTML, upload ricodificati, anti-SSRF, firme HMAC con nonce tra hub e negozi, segreti cifrati a riposo, esportazioni CSV protette. **Leggi `SECURITY.md`**: elenca cosa è protetto, la checklist da seguire prima di andare online e i rischi che restano (ad esempio: installa l'hub su un sottodominio dedicato e attiva la 2FA su ogni admin).

## Pagamenti: configurazione

`Admin → Pagamenti`. Stripe: chiave segreta e segreto del webhook (`/webhooks/stripe`). PayPal: Client ID, Secret, Webhook ID (`/webhooks/paypal`). Gli importi vengono sempre verificati lato server prima di segnare un ordine come pagato.
