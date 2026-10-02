# AlienShop

E-commerce leggero e veloce in PHP, senza dipendenze, con un **pannello (Hub)** per installare e controllare i negozi dei tuoi clienti su due VPS:

- **Backend** — server Debian/Ubuntu "nudo" (Nginx, PHP-FPM, MariaDB), **senza Hestia e senza porte pubbliche**: ospita i negozi.
- **Frontend** — l'unica VPS con **Hestia**: pubblica i domini dei clienti (HTTPS, cache), inoltra al backend e ospita l'**hub** in una cartella di un tuo dominio (`https://falconefabio.it/alienshop/`, nessun sottodominio).

Le due VPS parlano dentro **Tailscale**; domini e DNS sono su **Cloudflare**.

- Funzionalità del negozio: [docs/FUNZIONALITA.md](docs/FUNZIONALITA.md)
- Installare **un solo** negozio (senza hub, cPanel, Hestia, XAMPP…): [docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md](docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md)
- Sicurezza (cosa è protetto, rischi che restano): [SECURITY.md](SECURITY.md)
- Note tecniche per chi sviluppa: [DEVELOPMENT.md](DEVELOPMENT.md)

## Come è fatto

```
 visitatore ──HTTPS──▶ Cloudflare (DNS, WAF) ──▶ VPS FRONTEND (Hestia) ──── Tailscale ────▶ VPS BACKEND (senza Hestia)
                                                 Nginx: HTTPS, cache 60 s,                  Nginx + PHP-FPM + MariaDB
                                                 HUB su falconefabio.it/alienshop           un utente, un database e un pool PHP
                                                                                            separati per ogni negozio
 tu ──HTTPS + 2FA obbligatoria──▶ https://falconefabio.it/alienshop/   (l'hub vede e gestisce tutti i domini)
```

- **Hub**: sta sul frontend in `/alienshop/`. Mostra tutti i domini con AlienShop, incassi, ordini, errori, versioni, e crea/aggiorna/sospende i negozi. Non ha accessi SSH ai server: sono gli **agenti** (uno per VPS) a contattarlo ogni minuto ed eseguire i lavori; per le statistiche interroga i negozi dentro Tailscale.
- **Backend**: per ogni negozio crea utente di sistema, database, pool PHP isolato e configurazione Nginx. Nessun certificato qui: HTTPS lo termina il frontend.
- **Frontend**: Hestia gestisce domini e certificati; il template `alienshop-edge` inoltra al backend.
- **Installazione automatica da Hestia**: quando crei un dominio in Hestia scegliendo il template web `alienshop-edge`, un gancio avvisa l'hub, che installa il negozio sul **backend** e poi lo pubblica sul **frontend**, senza altri passaggi.
- Ogni negozio va nella **cartella principale** del dominio o in una **sottocartella** (le sottocartelle si scelgono dall'hub).

Nomi usati nei comandi (cambiali con i tuoi): dominio dell'hub `falconefabio.it` (cartella `alienshop`), utente Hestia del frontend `falco3205`. Valori da annotare: **IP_FRONTEND** e **IP_BACKEND** (pubblici) e **TS_BACKEND** (IP Tailscale del backend, `tailscale ip -4`).

## La tua infrastruttura oggi e come ci si inserisce

| Oggi | Con questa guida |
|---|---|
| Le porte dei servizi backend (8010, 8080, 8095, 8096, 8097, 8765, 3002) non sono raggiungibili da Internet: UFW le blocca e accetta solo dall'IP della vecchia VPS | Stessa idea, più stretta: **backend senza porte pubbliche**, tutto da **Tailscale** (`ufw allow in on tailscale0`); il frontend aperto solo a Cloudflare |
| I database non sono mai esposti in rete | Resta così: MariaDB ascolta solo su `127.0.0.1`, un utente e un database per negozio |
| Traffico pubblico in HTTPS, con WAF/DDoS dove c'è Cloudflare | I domini dei clienti passano da Cloudflare (nuvola arancione) verso il frontend |
| Le app hanno la propria autenticazione | Anche qui (2FA, limiti di tentativi, firme HMAC): è il **secondo** livello, non sostituisce le porte chiuse |
| Stai passando a Tailscale | È la base di questa guida |

Due controlli da fare sempre dopo aver cambiato il firewall:

1. **Scansione da fuori**: `nmap -Pn IP_PUBBLICO_BACKEND` e `nmap -Pn IP_FRONTEND` da un altro computer. Sul backend nessuna porta aperta; sul frontend solo 80/443.
2. **Docker e UFW**: le porte pubblicate da Docker (`-p 8080:8080`) **aggirano UFW**. Se i servizi 8010, 8080, 8095, 8096, 8097, 8765, 3002 girano in container, pubblicali su `127.0.0.1:PORTA:PORTA` o regola la catena `DOCKER-USER`.

## Prima di cominciare

1. **Backend**: una VPS Debian/Ubuntu **pulita**, senza Hestia né altri pannelli e **senza altri siti** (lo script sostituisce il sito predefinito di Nginx e scarta gli host sconosciuti). Se la tua vecchia VPS ospita già altri servizi, usane una nuova.
2. **Frontend**: una VPS con **Hestia** e **PHP CLI 8.1+**, con un utente Hestia (`falco3205`): `v-add-user falco3205 PASSWORD email@dominio.it` come root, se manca.
3. Account Tailscale, Cloudflare e GitHub, tutti con **2FA** (anche su Hestia): chi scrive su `main` esegue codice su tutti i negozi.
4. Snapshot/backup delle VPS prima di toccare il firewall.

## 1 · Tailscale su entrambe le VPS

**Prima** nel pannello Tailscale (*Access controls*) dichiara i tag e limita chi parla con chi (i tag vanno dichiarati in `tagOwners` prima di usarli):

```json
{
  "tagOwners": { "tag:backend": ["autogroup:admin"], "tag:frontend": ["autogroup:admin"] },
  "acls": [
    { "action": "accept", "src": ["autogroup:member"], "dst": ["tag:backend:22,80", "tag:frontend:22,8083"] },
    { "action": "accept", "src": ["tag:frontend"],     "dst": ["tag:backend:80"] }
  ]
}
```

Il frontend entra nel backend **solo** sulla porta 80; tu, dal portatile, su SSH, siti e pannello Hestia. Poi, come root:

```bash
curl -fsSL https://tailscale.com/install.sh | sh
tailscale up --ssh=false --advertise-tags=tag:backend     # sul backend
tailscale up --ssh=false --advertise-tags=tag:frontend    # sul frontend
tailscale ip -4                                           # annota l'IP 100.x.y.z di ciascuna
```

Installa Tailscale anche sul portatile.

## 2 · Firewall (UFW)

Un solo firewall per VPS. Sul **frontend**, che ha Hestia, disattiva il firewall di Hestia (*Server → Configure → Firewall*) se usi UFW.

**Backend** (nessuna porta pubblica; installa prima `ufw` se manca: `apt install -y ufw`):

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow in on tailscale0                 # SSH e siti solo dalla tailnet
# ripiego SSH pubblico (solo da casa): ufw allow from IL_TUO_IP to any port 22 proto tcp
ufw enable
```

**Frontend** (80/443 solo da Cloudflare, il resto dalla tailnet):

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow in on tailscale0
for ip in $(curl -fsS https://www.cloudflare.com/ips-v4 https://www.cloudflare.com/ips-v6); do
  ufw allow from "$ip" to any port 80,443 proto tcp comment 'Cloudflare'
done
ufw enable
```

Tieni una seconda sessione SSH aperta mentre abiliti UFW, poi verifica da fuori con `nmap` (vedi sopra).

## 3 · Cloudflare (una volta)

1. **SSL/TLS → Overview → Full (strict)**; *Always Use HTTPS* attivo; *Minimum TLS Version* 1.2.
2. **Limite di richieste al login** (*Security → WAF → Rate limiting rules*): URI che contiene `/login`, metodo POST, più di 10 richieste in 10 secondi per IP → *Block*.
3. **IP Access Rules** (*Security → WAF → Tools*): se limiti l'hub con `--allow`, ricordati che l'IP che l'hub vede è quello reale del visitatore (Cloudflare lo passa nell'intestazione `CF-Connecting-IP`).
4. Il dominio dell'hub (`falconefabio.it`) è già a posto: nessun record DNS da aggiungere.

## 4 · Frontend: installa l'hub in /alienshop/

Come root sul **frontend**, dopo aver aggiunto in Hestia il dominio `falconefabio.it` (utente `falco3205`) e averlo fatto puntare a Cloudflare:

```bash
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/hub/install.sh -o /root/install-hub.sh
bash /root/install-hub.sh --user falco3205 --domain falconefabio.it --admin-email tu@tuamail.it \
  --allow IP_CASA,IP_BACKEND
```

Lo script copia l'hub in `/home/falco3205/web/falconefabio.it/public_html/alienshop`, aggiunge le regole Nginx di Hestia, un cron di raccolta dati e stampa la password dell'amministratore. Il resto del sito `falconefabio.it` non viene toccato.

- `--allow` limita il pannello a quegli IP (il tuo e quello **pubblico del backend**, da cui l'agente contatta l'hub): consigliato. Se lo ometti, proteggi `/alienshop/` con **Cloudflare Access**.
- Apri `https://falconefabio.it/alienshop/` e accedi: la **verifica in due passaggi è obbligatoria** e ti viene chiesta subito (*Sicurezza → Attiva*); conserva i codici di recupero.

Aggiornare l'hub in futuro: `runuser -u falco3205 -- php /home/falco3205/web/falconefabio.it/public_html/alienshop/hub/bin/hub update`.

## 5 · Collega il backend all'hub

Hub → **Server → Collega un server**:

| Campo | Valore |
|---|---|
| Nome | `VPS backend` |
| Ruolo | Backend |
| IP pubblico | IP_BACKEND |
| Indirizzo del backend visto dal frontend | `http://TS_BACKEND:80` |
| Utente Hestia | vuoto (il backend non usa Hestia) |

Prima, **come root sul backend**, prepara lo stack (Nginx, PHP-FPM, MariaDB, in ascolto solo sull'IP Tailscale):

```bash
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/node/stack.sh -o /root/stack.sh && bash /root/stack.sh
```

Poi copia il comando mostrato dal pannello (contiene il token, visibile una sola volta) ed eseguilo come root sul backend. Verifica:

```bash
tail -n 5 /var/log/alienshop-node.log      # nessun errore
systemctl status nginx php*-fpm mariadb --no-pager | grep Active
```

Il server deve risultare **online** entro un minuto. L'agente raggiunge l'hub su `https://falconefabio.it/alienshop`.

## 6 · Collega il frontend

Hub → **Server → Collega un server** → Ruolo **Frontend**, IP pubblico **IP_FRONTEND**, **Utente Hestia** `falco3205`. Esegui il comando mostrato **come root sul frontend**: installa l'agente, il template Hestia `alienshop-edge` (proxy con cache e gancio di installazione automatica) e legge l'IP vero dei visitatori da Cloudflare.

Poi, in **Impostazioni** dell'hub, scegli il **Server backend per i domini creati da Hestia** (basta se ne hai uno solo).

## 7 · Crea un negozio per un cliente

**A · Automatico, da Hestia (consigliato)**

1. **DNS** su Cloudflare: `A` `@` → **IP_FRONTEND**, *Proxied*; `www` `CNAME` → `@`, *Proxied*. Fallo **prima**: serve per il certificato.
2. In Hestia: *Web → Aggiungi dominio*, utente `falco3205`, e nelle opzioni avanzate **Web Template (PHP-FPM)** → `alienshop-edge`; abilita SSL con Let's Encrypt.
3. Entro un minuto il gancio avvisa l'hub: il **backend** crea utente, database, pool PHP e sito e installa il negozio, poi il **frontend** lo pubblica. Il negozio compare nell'hub con esito, registro e **password iniziale**: salvala e premi *Le ho salvate*.
4. **Apri l'admin del negozio** dalla scheda (accesso monouso) e attiva la 2FA.

L'elenco "Quick Install App" di Hestia (quello con WordPress) **non è stato implementato**: richiede di modificare i file di Hestia, che un aggiornamento sovrascrive. Il template web fa la stessa cosa in modo stabile.

**B · Manuale, dall'hub (anche per sottocartelle)**

Hub → **Negozi → Nuovo negozio**: scegli il **dominio dall'elenco letto da Hestia** (con utente e cartella dei file, es. `/home/falco3205/web/onlyslow.it/public_html`) oppure scrivine uno nuovo (lo crea l'agente in Hestia); nome, email dell'amministratore, tema (con anteprima), lingua; **dove installarlo** (cartella principale o sottocartella); **Server backend**; **Pubblicazione** *Tramite frontend*.

- **Cartella principale**: il dominio passa al template `alienshop-edge` e tutto va al negozio.
- **Sottocartella**: il template di Hestia **non cambia**. Il sito esistente in `public_html` (WordPress, pagine…) resta servito da Hestia; solo `dominio.it/cartella` viene inoltrato al negozio sul backend, con due file di configurazione `nginx.conf_alienshop_*` e `nginx.ssl.conf_alienshop_*` nella cartella `conf/web/dominio` dell'utente Hestia.
- Il negozio vero e proprio (codice, database) sta sempre sul backend; su Hestia resta solo la configurazione del proxy.

Se un lavoro fallisce, nella scheda del negozio compare il tasto per riprovare solo il passaggio fallito; ripetere un'installazione già completata riallinea soltanto la configurazione del server, senza toccare file e database.

Se Cloudflare mostra errore 526, il certificato sul frontend non c'è (il dominio non puntava ancora a Cloudflare quando è partita la pubblicazione): crea un **certificato di origine** (Cloudflare: *SSL/TLS → Origin Server → Create Certificate*), salva `dominio.it.crt` e `dominio.it.key` in `/root/ssl/dominio.it/` sul frontend e lancia:

```bash
v-add-web-domain-ssl falco3205 dominio.it /root/ssl/dominio.it
```

## 8 · Sicurezza: regole e come muoversi

**Regole fisse**
- 2FA su GitHub, Cloudflare, Tailscale, Hestia, **hub** e **ogni admin di negozio**.
- Backend senza porte pubbliche; frontend aperto solo a Cloudflare; SSH e pannello Hestia (8083) **solo via Tailscale**.
- Hub in `/alienshop/` con 2FA obbligatoria e `--allow` (o Cloudflare Access); il suo dominio non ospita altro di non fidato.
- Branch `main` protetto su GitHub; **aggiornamento automatico dei negozi spento** finché non sei sicuro di ciò che pubblichi.
- Password lunghe e diverse (gestore di password); quelle iniziali dei negozi si cambiano al primo accesso.
- Il token dei server non si condivide: se esce, *Server → Genera un nuovo token* e rilancia il comando.
- Ogni negozio ha **utente, database e pool PHP propri** sul backend: non toccare a mano `/var/www/alienshop` e `/etc/nginx/alienshop.d`.

**Nel quotidiano**
1. Lavora su un branch, prova (`php tests/run.php && php tests/hub.php && php tests/node.php`), poi unisci in `main`.
2. **Aggiorna prima un negozio di prova** dall'hub (*scheda → Aggiorna il software*), controlla, poi gli altri. Ogni aggiornamento salva una copia del database e si può annullare.
3. Guarda ogni giorno la **Panoramica**: negozi che non rispondono, errori, aggiornamenti, ordini da spedire.
4. **Backup del backend** (non sono centralizzati). Esempio da mettere in cron ogni notte e da copiare fuori dalla VPS:

   ```bash
   mysqldump --all-databases --single-transaction | gzip > /root/backup-$(date +%F).sql.gz
   tar czf /root/negozi-$(date +%F).tgz /var/www/alienshop /home/falco3205/web/falconefabio.it/public_html/alienshop/hub/config /home/falco3205/web/falconefabio.it/public_html/alienshop/hub/storage
   ```
5. Se sospetti una violazione: sospendi il negozio dall'hub, rigenera il token del server, cambia le password, controlla *Attività* e `/var/log/alienshop-node.log`, e leggi [SECURITY.md](SECURITY.md).

## 9 · Se qualcosa non funziona

| Sintomo | Dove guardare |
|---|---|
| Server "offline" nell'hub | `tail -f /var/log/alienshop-node.log`; `cat /etc/alienshop/node.json`; `tailscale status`; `curl -I https://falconefabio.it/alienshop/` dalla VPS |
| Il dominio creato in Hestia non compare nell'hub | `cat /var/log/alienshop-claim.log` sul frontend; template `alienshop-edge` scelto? utente Hestia incluso nell'agente? backend predefinito impostato? |
| Negozio in errore | Hub → **Attività** → apri il lavoro: c'è il registro dei comandi |
| 502 sul dominio del cliente | dal frontend: `curl -I -H 'Host: dominio.it' http://TS_BACKEND:80/`; `tailscale ping TS_BACKEND`; ACL di Tailscale; sul backend `nginx -t` e `tail /var/log/nginx/error.log` |
| 526 / errore certificato | certificato di origine sul frontend (passo 7) |
| 500 dentro il negozio | `tail` del log PHP-FPM sul backend (`/var/log/php*-fpm.log`) e `storage/logs/error.log` del negozio (`/var/www/alienshop/shops/<slug>/app/storage/logs/`) |
| `nginx -t` dice «real_ip_header directive is duplicate» sul frontend | Hestia o un altro file lo definisce già. Aggiorna lo script: `curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/node/alienshop-cloudflare-ips -o /usr/local/bin/alienshop-cloudflare-ips && chmod 755 /usr/local/bin/alienshop-cloudflare-ips && /usr/local/bin/alienshop-cloudflare-ips` (non scrive il duplicato e, se Nginx rifiuta la configurazione, ripristina la precedente). Finché `nginx -t` fallisce, nessuna modifica di Hestia o di AlienShop viene applicata e le pagine rispondono 403 |
| Tutti i visitatori con lo stesso IP, login bloccati per tutti | manca `--cloudflare` sul frontend, o il campo *IP da cui il backend vede arrivare il frontend* (con Tailscale lo imposta il pannello) |

## Limiti noti (leggi prima di usarlo con clienti veri)

La parte **nativa del backend** (utente, database, pool PHP-FPM, Nginx, installazione, sottocartelle, sospensione, isolamento) e l'hub sono stati provati per davvero in un container Debian con Nginx, PHP-FPM e MariaDB veri: installazione dell'hub, lavoro creato dall'hub, eseguito dall'agente, negozio attivo e interrogato. **Non provati** su server reali: il template e il gancio Hestia del frontend, l'installazione dell'hub sotto Hestia, Tailscale, UFW, Cloudflare, i pagamenti, la posta e il Sistema di Interscambio. Alla prima installazione fai una prova con un dominio tuo e tieni d'occhio la pagina *Attività* dell'hub. I negozi sul backend non possono eseguire comandi di sistema dal web (per sicurezza): si aggiornano dal pannello con il metodo ZIP.

## Test

```bash
php tests/run.php     # negozio
php tests/hub.php     # hub
php tests/node.php    # agente (modalità prova: non esegue nulla sul server)
```
