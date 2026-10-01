# Sicurezza di AlienShop

Questo documento dice cosa è stato controllato, cosa fa il software per proteggerti, cosa devi fare tu prima di andare online e quali rischi restano. Non sostituisce un test di penetrazione professionale: nessun audit esterno è stato fatto.

## Cosa protegge il software

**Accesso e sessioni**
- Password con `password_hash` (bcrypt); minimo 10 caratteri per gli amministratori, 8 per i clienti.
- **Verifica in due passaggi (TOTP)** per gli amministratori del negozio e dell'hub (app tipo Google Authenticator), con codici di recupero monouso cifrati a riposo; il codice non può essere riusato.
- Blocco dei tentativi di accesso **per IP e per account** (anche se l'attacco arriva da molti IP); anche i codici 2FA, i cambi password e le azioni sensibili hanno limiti.
- Gli amministratori non possono entrare dal login dei clienti (che aggirerebbe la 2FA).
- Sessioni: cookie `HttpOnly`, `Secure` su HTTPS, `SameSite=Lax`, rigenerazione all'accesso, legate al browser, scadenza per inattività (admin 4 ore, massimo 12 ore) e cookie isolati per cartella (più negozi sullo stesso dominio non si scambiano la sessione).
- Reimpostazione password con token firmato (HMAC) legato alla password attuale: vale una volta sola e scade in un'ora.

**Richieste e contenuti**
- Query SQL sempre preparate; nessun `eval`, `unserialize` o esecuzione di comandi con dati dell'utente.
- Escape di tutto l'output e **sanitizzazione HTML con lista di tag/attributi ammessi** (niente script, `javascript:`, iframe, handler `on*`).
- **CSRF**: token su admin, checkout e account; sul carrello controllo di `Origin`/`Referer`/`Sec-Fetch-Site`; redirect solo verso il proprio sito.
- **Content-Security-Policy**: rigida con nonce per l'admin (nessuno script inline); sul negozio limita framing, `<base>`, oggetti e sorgenti (puoi autorizzare domini di terzi da *Admin → Sicurezza*). In più `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `COOP`, HSTS.
- Upload: solo JPEG/PNG/GIF/WebP, ricodificati con GD (il file originale non viene mai servito), limite di dimensioni (anti bomba di decompressione); niente SVG; PHP non eseguibile in `uploads/`.
- Fatture elettroniche ricevute: XML senza DOCTYPE/ENTITY (anti XXE), limiti su ZIP e dimensioni.
- Esportazioni CSV protette dall'iniezione di formule (Excel).
- Download di file da URL (import immagini, aggiornamenti) con blocco SSRF: solo indirizzi pubblici, IP "fissato" e redirect ricontrollati.
- Email: indirizzi con ritorni a capo rifiutati, oggetti codificati (nessuna iniezione di intestazioni).
- Pagamenti: firme dei webhook verificate (Stripe con tolleranza di 5 minuti, PayPal via API), importo/valuta/ordine verificati lato server; chiavi dei gateway e password SMTP cifrate a riposo (libsodium).
- Scorte decrementate in modo atomico (nessuna vendita oltre la disponibilità).

**Aggiornamenti**
- Aggiornamenti da GitHub con backup del database, controllo anti zip-slip, verifica che il pacchetto corrisponda al commit richiesto, nessuna sovrascrittura di configurazione, dati e immagini.

**Hub e server**
- L'hub **non conserva** password o chiavi di Hestia e non entra in SSH: sono gli agenti sui server a contattare l'hub.
- Richieste hub → negozio firmate con HMAC-SHA256 con nonce monouso, timestamp e segreto per negozio (anti replay); token per server (solo l'hash è nel database, 192 bit); limiti sui tentativi falliti.
- Segreti dei lavori (password iniziali, segreti) cifrati nel database dell'hub e cancellati a lavoro concluso; password e segreti oscurati nei registri dell'agente.
- L'agente esegue solo operazioni fisse e valida ogni parametro (dominio, utente Hestia ammesso, cartella, repository, URL…) con espressioni ancorate; le regole a stringa non lasciano passare ritorni a capo.
- Accesso a un negozio dall'hub con token monouso da 90 secondi.
- Wizard di installazione protetto da una chiave (`storage/install.key`) quando l'installazione è guidata dall'hub.

## Prima di andare online (checklist)

1. **HTTPS ovunque** e indirizzo del sito in `https://` nelle impostazioni.
2. **Attiva la 2FA** su ogni amministratore (*Admin → Profilo → Sicurezza*) e sull'hub (obbligatoria di fatto: l'hub controlla le VPS).
3. **Installa da riga di comando** (`bin/console install`) o con l'hub, non lasciando `/install` aperto su un dominio pubblico.
4. **Document root su `public/`** (template Hestia/Nginx inclusi). Se usi Apache dalla radice, verifica che `https://tuodominio/config/config.php` e `/storage/` rispondano 403/404.
5. **Hub su un sottodominio dedicato** (es. `hub.tuodominio.it`, `--root` nello script di installazione). Se sta sotto un percorso di un sito con altro software (WordPress…), una falla in quel sito, che ha la stessa origine, può agire sul pannello.
6. **GitHub**: attiva la 2FA sul tuo account, proteggi il branch `main` (richiedi PR o almeno blocca il force-push) e tieni **spento l'aggiornamento automatico** mentre sviluppi: chi può scrivere su `main` può eseguire codice su tutti i negozi.
7. Backup regolari fuori dal server e **PHP aggiornato** (8.2+).
8. Con frontend/tunnel imposta correttamente i **proxy fidati** (*IP da cui il backend vede arrivare il frontend*): senza, tutti i visitatori sembrano avere lo stesso IP e i limiti di accesso colpiscono tutti. Se Cloudflare sta davanti al frontend, configura `set_real_ip_from` con i suoi intervalli.
9. Permessi: `config/config.php` e `storage/` leggibili solo dall'utente PHP (`chmod 640` / `750`).
10. Se aggiungi script di terzi (pixel, chat) autorizza il dominio in *Admin → Sicurezza* (CSP) invece di disattivarla.

## Rischi che restano (onestà)
- **Gli amministratori sono fidati**: possono inserire codice nelle impostazioni (`head`/`footer`), CSS e import. Dai l'accesso admin solo a chi ne ha bisogno.
- **CSP del negozio** ammette script inline (le pagine sono in cache e i codici di terzi inseriti dall'admin sono inline): riduce i danni ma non è una difesa completa contro l'XSS; per questo l'output è sempre escapato e l'HTML sanitizzato alla scrittura.
- **Il chi controlla l'hub o l'account GitHub controlla i server**: gli agenti, che girano come root, eseguono ciò che l'hub chiede e i negozi scaricano il codice da GitHub.
- **Tempo di installazione**: tra il clone e l'installazione di un nuovo negozio (pochi secondi) il wizard è protetto dalla chiave di installazione, ma i file del negozio sono già sul server.
- I comandi `v-*` di Hestia, i template Nginx e il tunnel non sono stati provati su un server reale; i pagamenti, la posta e il Sistema di Interscambio non sono stati provati con servizi reali.
- Non c'è un WAF, né rilevamento di intrusioni: affianca Fail2ban/CrowdSec e, se puoi, Cloudflare.

## Segnalare una vulnerabilità
Scrivi in privato al titolare del repository (non aprire una issue pubblica) descrivendo come riprodurre il problema.
