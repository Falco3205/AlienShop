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

Password con `password_hash`, token CSRF su checkout/account/admin e controllo dell'origine sulle azioni del carrello, query preparate, escape dell'output, sanitizzazione dell'HTML, upload ricodificati con GD (nessun file originale servito), blocco SSRF nel download immagini, limite ai tentativi di login, header di sicurezza, cartelle `app/`, `config/`, `storage/` non esposte.

## Pagamenti: configurazione

`Admin → Pagamenti`. Stripe: chiave segreta e segreto del webhook (`/webhooks/stripe`). PayPal: Client ID, Secret, Webhook ID (`/webhooks/paypal`). Gli importi vengono sempre verificati lato server prima di segnare un ordine come pagato.
