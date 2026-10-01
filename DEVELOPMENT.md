# AlienShop — note di sviluppo

Registro di ciò che è stato scritto e di come è organizzato.

## Stack e scelte

PHP 8.1+ senza dipendenze, PDO con SQLite o MySQL. Il front controller è `public/index.php`. Gli importi sono interi in centesimi. Lo schema in `database/schema.sql` usa i segnaposto `{PK}` e `{ENGINE}` sostituiti da `Installer::createSchema()` in base al driver. Non ci sono chiavi esterne: le cascate sono gestite nel codice.

## Struttura

| Percorso | Contenuto |
|---|---|
| `app/Core` | App (routing, cache pagine, errori), Config, DB, Request/Response/Router, Session, Csrf, Auth, Settings, View (con fallback tema → `_base`), Lang, Money, Str, ImageProcessor (GD→WebP), Http (cURL + anti-SSRF), Mailer (mail/SMTP), Cache |
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

**Redirect.** `Redirects::add()` appiattisce le catene. Ogni cambio slug in `Catalog::save/saveCategory` e nelle pagine crea un 301; una pagina 404 per un URL sconosciuto consulta la tabella prima di rispondere.

**Cache.** `App::run()` serve le pagine pubbliche da `storage/cache/pages` prima di aprire il database. Il carrello è mostrato via cookie `as_cart` letto da JS, quindi l'HTML è identico per tutti. Gli admin (cookie `as_admin`) la saltano; `Cache::flush()` parte dopo ogni POST admin.

**Temi.** `Themes::build()` concatena `base.css` + `style.css` del tema + override del pannello in un file `theme-<hash>.css` (minificato) e `app-<hash>.js` in `public/assets`; i nomi sono salvati in `settings.asset_css/asset_js`. I temi differiscono per token CSS e per classi di layout derivate da `theme.json` (`h-*`, `g-*`, `c-*`, `hero-*`).

**Pagamenti.** `Gateway::start()` restituisce `redirect`, `offline` o `error`. Il carrello si svuota solo a pagamento confermato (online) o subito (offline). Conferma da return URL e webhook, sempre con verifica di importo, valuta e token ordine.

**Import.** Gli alias delle colonne WooCommerce coprono le intestazioni in inglese e italiano. Match dei prodotti esistenti: ID esterno → SKU → slug. Con un solo attributo i prezzi delle variazioni diventano delta sugli attributi; con più attributi restano override per variante. Le combinazioni assenti nel file vengono disattivate.

## Verifica eseguita

`php tests/run.php` (27 test: formati monetari, prezzi variante, redirect, carrello, scorte, coupon, firme Stripe, flusso Stripe/PayPal con HTTP simulato, SEO, import/export con round trip, anti-SSRF). Test manuali via browser: wizard, tutte le pagine admin, flusso carrello→checkout→ordine, cache/304/gzip, redirect, anteprima dei 10 temi.

**Non verificato con servizi reali**: chiamate a Stripe, PayPal, SMTP e IndexNow (nessuna credenziale/rete nel test); sono coperte solo con risposte simulate e verifica delle firme.

## Estendere

- Nuovo gateway: `app/Payments/MioGateway.php` (classe `MioGateway extends Gateway`).
- Nuovo tema: cartella in `themes/` con `theme.json` e `style.css`; sovrascrivi solo le viste che servono.
- Nuova lingua: `lang/<codice>.php` e voce in `Lang::available()`.
