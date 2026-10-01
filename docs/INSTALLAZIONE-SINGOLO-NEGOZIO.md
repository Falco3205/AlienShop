# Installare un singolo negozio

Guida per installare **un solo negozio** (senza hub): server con Hestia, cPanel, Plesk, hosting con FTP, VPS generica o computer locale. Per gestire più negozi di clienti con due VPS vedi il [README](../README.md).

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

## Installazione per tipo di hosting

Regole comuni: PHP **8.1+** con le estensioni `pdo_sqlite` o `pdo_mysql`, `mbstring`, `gd`, `curl`, `openssl`, `sodium`, `dom` (e `zip`, `intl` consigliate), HTTPS attivo, e il document root sul dominio. Il wizard `/install` segnala cosa manca. Le procedure sotto sono indicative: i nomi dei menu cambiano tra versioni dei pannelli e non sono state provate su ciascuno.

### Hestia Control Panel
Installazione completamente automatica: vedi la sezione "Hestia Control Panel" più sotto (`docs/hestia/install-alienshop.sh`).

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
