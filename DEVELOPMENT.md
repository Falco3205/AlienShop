# AlienShop — note di sviluppo

Registro di ciò che è stato scritto e di come è organizzato.

## Stack e scelte

PHP 8.1+ senza dipendenze, PDO con SQLite o MySQL. Il front controller è `public/index.php`. Gli importi sono interi in centesimi. Lo schema in `database/schema.sql` usa i segnaposto `{PK}` e `{ENGINE}` sostituiti da `Installer::createSchema()` in base al driver. Non ci sono chiavi esterne: le cascate sono gestite nel codice.

## Struttura

| Percorso | Contenuto |
|---|---|
| `app/Core` | App (routing, cache pagine, errori), Config, DB, Request/Response/Router, Session, Csrf, Auth, Settings, View (con fallback tema → `_base`), Lang, Money, Str, ImageProcessor (GD→WebP), Http (cURL + anti-SSRF, reset password, rimborsi, IVA per paese, log 404, immagine principale, Analytics, pagine legali, statistiche, recensioni, newsletter, carrelli abbandonati, fatture, avvisi disponibilità, Mollie, filtri, backup, fatturazione elettronica con server PEC simulato, contabilità), Mailer (mail/SMTP), Cache |
| `app/Services` | Catalog (prodotti, attributi, varianti, categorie), Cart, Coupons, Shipping, Orders, Redirects, Seo, Sitemap, IndexNow, Themes, Installer, Demo |
| `app/Payments` | `Gateway` astratto, Stripe, PayPal, Bank, Cod, `Registry` (auto-discovery di `*Gateway.php`) |
| `app/Import` | CsvReader, Writer, WooImporter, ShopifyImporter, Exporter, Result |
| `app/Controllers` | Shop, Cart, Checkout, Account, Seo, Webhook, Install |
| `app/Controllers/Admin` | `Routes` (guardia admin + CSRF + flush cache dopo ogni POST) e un controller per sezione |
| `themes/_base` | Viste, `base.css`, `app.js` condivisi |
| `themes/<slug>` | `theme.json` (nome, colori, layout) + `style.css` con token CSS; eventuali `views/` sovrascrivono quelle base |
| `views/admin`, `views/install` | Template del pannello e del wizard |
| `lang/en.php` | Traduzione inglese (le chiavi sono le stringhe italiane passate a `__()`) |
| `bin/console` | Import/export CLI, scadenza ordini, cache |
| `tests/run.php` | Test su un DB SQLite temporaneo |

## Come funzionano le parti principali

**Prezzi e varianti.** `product_attributes` + `attribute_values.price_delta` definiscono i valori e il loro delta. `Catalog::save()` rigenera le varianti come prodotto cartesiano preservando SKU/prezzo/scorte di quelle esistenti (chiave `options_key`, hash delle opzioni). Prezzo variante = `variants.price` se valorizzato, altrimenti prezzo base + somma dei delta (`Catalog::variantPrice`). `products.price_min/max/in_stock` sono denormalizzati da `refreshDerived()` per liste, ordinamento e JSON-LD.

**Recupero password.** Token senza stato (`Auth::resetToken`): HMAC con `app.key` su id, scadenza e un frammento dell'hash della password, quindi diventa invalido dopo l'uso. Risposta identica per email esistenti e non (niente enumerazione) e limite ai tentativi.

**Statistiche.** `product_stats` (contatori giornalieri per prodotto: visite, aggiunte al carrello, checkout) e `search_terms`. Le visite arrivano da un `sendBeacon` su `/t` (le pagine sono in cache statica); carrello e checkout sono contati lato server in `Cart::add` e `CheckoutController::show`. Bot e admin (cookie `as_admin`) sono esclusi. Le vendite vengono da `order_items`; "nel carrello ma non comprati" = aggiunte − unità vendute.

**Google Analytics.** `Analytics::render()` emette `window.ASGA` (ID, consenso, eventi della pagina); `app.js` carica gtag solo dopo il consenso (localStorage `as_consent`), accoda gli eventi e deduplica `purchase` per transazione. Il wizard (`/admin/analytics`) valida `G-XXXXXXXXXX` e verifica la home.

**Pagine legali.** `LegalTemplates::build($profilo, $lingua)` genera 6 pagine da un profilo salvato in `settings.legal_profile`; `publish()` le crea o aggiorna per slug. Tutti i valori inseriti sono escapati.

**Estensioni.** `Modules` (registro con id, testo, default e link) legge `settings.mod_<id>`; ogni funzione controlla `Modules::on()`. Gli interruttori sono in `/admin/modules`; le estensioni attive compaiono nel menu.

**Migrazioni.** `Migrator` (versione schema in `settings.schema_version`) riesegue lo schema tollerando "già esistente" e aggiunge le colonne nuove; parte da solo al primo avvio dopo un aggiornamento (`bin/console migrate` per farlo a mano).

**Cron senza cron.** `Cron::maybeRun()` dopo l'invio di ogni pagina dinamica, al massimo una volta al minuto (file `storage/cron.lock`, `fastcgi_finish_request` quando c'è): scade gli ordini online non pagati, svuota `mail_queue` (30 mail/minuto, usata da campagne, promemoria, avvisi, richieste recensione), manda i promemoria dei carrelli e le richieste di recensione.

**Pagine in cache e form.** Le pagine cacheabili sono HTML statico: recensioni, newsletter e avvisi disponibilità usano `fetch` con risposta JSON (con ripiego su una pagina non in cache), non c'è CSRF ma controllo di origine, honeypot e limiti. I flash message non vengono mai messi in cache (`as_flash_shown`).

**PDF.** `Core\Pdf` scrive PDF 1.4 con Helvetica standard (WinAnsi, tabelle di larghezze incluse): testo, linee, rettangoli, a capo e pagine multiple. `Invoices::pdf()` compone il documento; `Mailer::send()` supporta allegati.

**Fatturazione elettronica.** `EInvoice\InvoiceData` converte un ordine in dati fattura (righe nette, sconto come riga negativa, spedizione, arrotondamento riportato in `DatiRiepilogo`, aliquota da `orders.tax_rate`); `XmlBuilder` scrive il FatturaPA 1.2.2 e lo valida con `schema/FatturaPA_v1.2.2.xsd` (copia locale dell'XSD ufficiale + xmldsig, nessun accesso di rete). Tutti i testi passano da `Fiscal::basicLatin` perché lo schema ammette solo Basic Latin e Latin-1. `Services\EInvoices` emette (tabella `einvoices`, XML in `storage/einvoice/out`, nome file `IT<P.IVA>_<progressivo base36>.xml`), invia, rigenera dopo uno scarto (stesso numero, nuovo progressivo) e emette note di credito. `Services\Sdi` usa `Mailer::sendSmtp` per l'invio alla PEC di SdI e un client `EInvoice\Imap` su socket per leggere la casella; `EInvoice\Mime` apre le buste PEC (anche `postacert.eml` annidato) e `EInvoice\P7m` estrae l'XML dai file firmati (openssl, con ripiego sulla ricerca dei byte). `XmlParser` legge le fatture ricevute in `purchase_invoices` (dedupe per fornitore/numero/data/tipo, XML in `storage/einvoice/in`). Le credenziali PEC sono cifrate con `Core\Secret` (libsodium, chiave derivata da `app.key`). `Services\Accounting` costruisce registri, IVA, scadenzario e report; le note di credito (TD04/TD08) entrano con segno negativo.

**Rimborsi.** `Gateway::refund()` (Stripe `/v1/refunds` sul `payment_intent`, PayPal `/captures/{id}/refund`); l'ordine passa a `refunded` solo se il gateway conferma.

**Redirect.** `Redirects::add()` appiattisce le catene. Ogni cambio slug in `Catalog::save/saveCategory` e nelle pagine crea un 301; una pagina 404 per un URL sconosciuto consulta la tabella prima di rispondere.

**Cache.** `App::run()` serve le pagine pubbliche da `storage/cache/pages` prima di aprire il database. Il carrello è mostrato via cookie `as_cart` letto da JS, quindi l'HTML è identico per tutti. Gli admin (cookie `as_admin`) la saltano; `Cache::flush()` parte dopo ogni POST admin.

**Temi.** `Themes::build()` concatena `base.css` + `style.css` del tema + override del pannello in un file `theme-<hash>.css` (minificato) e `app-<hash>.js` in `public/assets`; i nomi sono salvati in `settings.asset_css/asset_js`. I temi differiscono per token CSS e per classi di layout derivate da `theme.json` (`h-*`, `g-*`, `c-*`, `hero-*`).

**Pagamenti.** `Gateway::start()` restituisce `redirect`, `offline` o `error`. Il carrello si svuota solo a pagamento confermato (online) o subito (offline). Conferma da return URL e webhook, sempre con verifica di importo, valuta e token ordine.

**Import.** Gli alias delle colonne WooCommerce coprono le intestazioni in inglese e italiano. Match dei prodotti esistenti: ID esterno → SKU → slug. Con un solo attributo i prezzi delle variazioni diventano delta sugli attributi; con più attributi restano override per variante. Le combinazioni assenti nel file vengono disattivate.

**Revisione di sicurezza e robustezza.** `Str::sanitizeHtml` è un sanitizzatore DOM con allowlist di tag/attributi e controllo degli schemi URL. I campi `password` delle impostazioni (chiavi dei gateway, SMTP) sono salvati cifrati con `Core\Secret` e riletti da `Gateway::setting` / `Mailer`. `Http::download` risolve l'host, rifiuta IP privati/riservati, fissa l'IP con `CURLOPT_RESOLVE` e segue a mano al massimo 3 redirect rivalidando ogni salto. Lo scarico scorte in `Orders::adjustStock` è un `UPDATE ... WHERE stock >= qty` atomico: se non basta, la transazione salta con `OutOfStock` e il checkout mostra l'errore. Le ricerche `LIKE` usano `DB::like` con `ESCAPE '!'` (compatibile con MySQL). `Core\RateLimit` riusa `login_attempts` con chiave `bucket:ip`. Nuove pagine: `/contact` (honeypot + rate limit, mail a `store_email` con Reply-To) e `/track` (numero ordine + email → pagina ordine). Cambi di disponibilità svuotano la cache pagine. L'installer richiede anche sodium e dom (zip facoltativo).

## Verifica eseguita

`php tests/run.php` (61 test: formati monetari, prezzi variante, redirect, carrello, scorte, coupon, firme Stripe, flusso Stripe/PayPal con HTTP simulato, SEO, import/export con round trip, anti-SSRF, reset password, rimborsi, IVA per paese, log 404, immagine principale, Analytics, pagine legali, statistiche, recensioni, newsletter, carrelli abbandonati, fatture, avvisi disponibilità, Mollie, filtri, backup, fatturazione elettronica con server PEC simulato, contabilità). Test manuali via browser: wizard, tutte le pagine admin, flusso carrello→checkout→ordine, cache/304/gzip, redirect, anteprima dei 10 temi.

Crawl automatico di tutte le pagine pubbliche e admin (382 URL, tutti i moduli attivi) su SQLite e su MariaDB 10.11: nessun errore 5xx né voce nel log. Ordine e scarico atomico verificati anche su MariaDB.

**Non verificato con servizi reali**: chiamate a Stripe, PayPal, SMTP e IndexNow (nessuna credenziale/rete nel test); sono coperte solo con risposte simulate e verifica delle firme.

## Estendere

- Nuovo gateway: `app/Payments/MioGateway.php` (classe `MioGateway extends Gateway`).
- Nuovo tema: cartella in `themes/` con `theme.json` e `style.css`; sovrascrivi solo le viste che servono.
- Nuova lingua: `lang/<codice>.php` e voce in `Lang::available()`.
