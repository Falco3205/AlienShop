# AlienShop

E-commerce leggero e veloce in PHP, senza dipendenze, con un **pannello (Hub)** per installare e controllare i negozi dei tuoi clienti su due VPS:

- **Backend** — server Debian/Ubuntu "nudo" (Nginx, PHP-FPM, MariaDB), **senza Hestia e senza porte pubbliche**: ospita i negozi e l'hub.
- **Frontend** — l'unica VPS con **Hestia**: pubblica i domini dei clienti (HTTPS, cache) e inoltra al backend.

Le due VPS parlano dentro **Tailscale**; domini e DNS sono su **Cloudflare**.

- Funzionalità del negozio: [docs/FUNZIONALITA.md](docs/FUNZIONALITA.md)
- Installare **un solo** negozio (senza hub, cPanel, Hestia, XAMPP…): [docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md](docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md)
- Sicurezza (cosa è protetto, rischi che restano): [SECURITY.md](SECURITY.md)
- Note tecniche per chi sviluppa: [DEVELOPMENT.md](DEVELOPMENT.md)

## Come è fatto

```
 visitatore ──HTTPS──▶ Cloudflare (DNS, WAF) ──▶ VPS FRONTEND (Hestia) ──── Tailscale ────▶ VPS BACKEND (senza Hestia)
                                                 Nginx: HTTPS, cache 60 s,                  Nginx + PHP-FPM + MariaDB
                                                 serve la cache se il backend è giù         un utente, un database e un pool PHP
                                                                                            separati per ogni negozio + l'hub
 tu (portatile con Tailscale) ───────────────▶ http://hub.falconefabio.it  (raggiungibile solo dalla tailnet)
```

- **Hub**: crea i negozi e mostra incassi, ordini, errori, versioni. Non ha password né accessi SSH ai server: sono gli **agenti** sulle VPS a contattarlo ogni minuto ed eseguire i lavori.
- **Backend**: per ogni negozio crea utente di sistema, database, pool PHP isolato e configurazione Nginx. Nessun certificato qui: HTTPS lo termina il frontend.
- **Frontend**: Hestia gestisce domini e certificati; un template proxy inoltra al backend.
- Ogni negozio va nella **cartella principale** del dominio o in una **sottocartella**.

Nomi usati nei comandi (cambiali con i tuoi): hub `hub.falconefabio.it`, utente Hestia del frontend `falco3205`. Valori da annotare: **IP_FRONTEND** (pubblico) e **TS_BACKEND** (IP Tailscale del backend, `tailscale ip -4`).

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
3. DNS dell'hub: record `A` **`hub`** → **TS_BACKEND**, **DNS only** (nuvola grigia). Un indirizzo 100.x è raggiungibile solo dalla tailnet: l'hub non esiste per il resto di Internet.

## 4 · Backend: installa l'hub

Come root sul **backend** (lo script installa Nginx, PHP-FPM e MariaDB, ascoltando solo sull'IP Tailscale):

```bash
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/hub/install.sh -o /root/install-hub.sh
bash /root/install-hub.sh --domain hub.falconefabio.it --admin-email tu@tuamail.it
```

Stampa la password dell'amministratore. Dal portatile (con Tailscale acceso) apri `http://hub.falconefabio.it`, accedi e **subito**: *Sicurezza → Verifica in due passaggi → Attiva*; conserva i codici di recupero. Il traffico nella tailnet è cifrato da WireGuard, quindi qui non serve un certificato.

Aggiornare l'hub in futuro: `runuser -u alienhub -- php /opt/alienshop-hub/hub/bin/hub update`.

## 5 · Collega il backend all'hub

Hub → **Server → Collega un server**:

| Campo | Valore |
|---|---|
| Nome | `VPS backend` |
| Ruolo | Backend |
| IP pubblico | IP pubblico del backend (facoltativo) |
| Indirizzo del backend visto dal frontend | `http://TS_BACKEND:80` |
| Utente Hestia | vuoto (il backend non usa Hestia) |

Copia il comando mostrato (contiene il token, visibile una sola volta) ed eseguilo **come root sul backend**: installa l'agente e prepara il server (la parte di Nginx/PHP/MariaDB è già a posto dal passo 4). Verifica:

```bash
tail -n 5 /var/log/alienshop-node.log      # nessun errore
systemctl status nginx php*-fpm mariadb --no-pager | grep Active
```

Nel pannello il server deve risultare **online** entro un minuto.

## 6 · Collega il frontend

Hub → **Server → Collega un server** → Ruolo **Frontend**, IP pubblico **IP_FRONTEND**, **Utente Hestia** `falco3205`. Esegui il comando mostrato **come root sul frontend**: installa l'agente, il template Hestia `alienshop-edge` (proxy con cache) e legge l'IP vero dei visitatori da Cloudflare. L'agente raggiunge l'hub dalla tailnet (`hub.falconefabio.it` → TS_BACKEND).

## 7 · Crea un negozio per un cliente

1. **DNS del dominio del cliente** (su Cloudflare): `A` `@` → **IP_FRONTEND**, *Proxied* (nuvola arancione); `www` `CNAME` → `@`, *Proxied*. Fallo **prima**: serve per emettere il certificato sul frontend.
2. Hub → **Negozi → Nuovo negozio**: dominio, nome, email dell'amministratore, tema, lingua; **dove installarlo** (cartella principale o sottocartella); **Server backend** `VPS backend`; **Pubblicazione** *Tramite frontend* → `VPS frontend`.
3. Entro un minuto il backend crea utente, database, pool PHP e sito, e installa il negozio; poi il frontend lo pubblica. La scheda mostra esito, registro e **password iniziale**: salvala e premi *Le ho salvate*.
4. **Apri l'admin del negozio** dalla scheda (accesso monouso) e attiva la 2FA (*Admin → Profilo → Sicurezza*).

Sottocartella: il negozio risponde su `dominio.it/cartella`; il resto del dominio mostra una pagina segnaposto che puoi sostituire con i file del sito del cliente in `/var/www/alienshop/sites/dominio.it/` sul backend.

Se Cloudflare mostra errore 526, il certificato sul frontend non c'è (il dominio non puntava ancora a Cloudflare quando è partita la pubblicazione): crea un **certificato di origine** (Cloudflare: *SSL/TLS → Origin Server → Create Certificate*), salva `dominio.it.crt` e `dominio.it.key` in `/root/ssl/dominio.it/` sul frontend e lancia:

```bash
v-add-web-domain-ssl falco3205 dominio.it /root/ssl/dominio.it
```

## 8 · Sicurezza: regole e come muoversi

**Regole fisse**
- 2FA su GitHub, Cloudflare, Tailscale, Hestia, **hub** e **ogni admin di negozio**.
- Backend senza porte pubbliche; frontend aperto solo a Cloudflare; SSH e pannello Hestia (8083) **solo via Tailscale**.
- Hub solo in tailnet, su un dominio dedicato.
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
   tar czf /root/negozi-$(date +%F).tgz /var/www/alienshop /opt/alienshop-hub/hub/config /opt/alienshop-hub/hub/storage
   ```
5. Se sospetti una violazione: sospendi il negozio dall'hub, rigenera il token del server, cambia le password, controlla *Attività* e `/var/log/alienshop-node.log`, e leggi [SECURITY.md](SECURITY.md).

## 9 · Se qualcosa non funziona

| Sintomo | Dove guardare |
|---|---|
| Server "offline" nell'hub | `tail -f /var/log/alienshop-node.log`; `cat /etc/alienshop/node.json`; `tailscale status`; `curl -I http://hub.falconefabio.it` dalla VPS |
| Negozio in errore | Hub → **Attività** → apri il lavoro: c'è il registro dei comandi |
| 502 sul dominio del cliente | dal frontend: `curl -I -H 'Host: dominio.it' http://TS_BACKEND:80/`; `tailscale ping TS_BACKEND`; ACL di Tailscale; sul backend `nginx -t` e `tail /var/log/nginx/error.log` |
| 526 / errore certificato | certificato di origine sul frontend (passo 7) |
| 500 dentro il negozio | `tail` del log PHP-FPM sul backend (`/var/log/php*-fpm.log`) e `storage/logs/error.log` del negozio (`/var/www/alienshop/shops/<slug>/app/storage/logs/`) |
| Tutti i visitatori con lo stesso IP, login bloccati per tutti | manca `--cloudflare` sul frontend, o il campo *IP da cui il backend vede arrivare il frontend* (con Tailscale lo imposta il pannello) |

## Limiti noti (leggi prima di usarlo con clienti veri)

Gli script che creano utenti, database, pool PHP e siti Nginx sul backend, il template Hestia del frontend, Tailscale, UFW, Cloudflare, i pagamenti reali, la posta e il Sistema di Interscambio non sono stati provati su server reali: i test coprono hub, agente (in modalità prova) e negozio in locale. Alla prima installazione fai una prova con un dominio tuo e tieni d'occhio la pagina *Attività* dell'hub. I negozi sul backend non possono eseguire comandi di sistema dal web (per sicurezza): si aggiornano dal pannello con il metodo ZIP.

## Test

```bash
php tests/run.php     # negozio
php tests/hub.php     # hub
php tests/node.php    # agente (modalità prova: non esegue nulla sul server)
```
