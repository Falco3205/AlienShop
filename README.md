# AlienShop

E-commerce leggero e veloce in PHP, senza dipendenze, con un **pannello (Hub)** per installare e controllare i negozi dei tuoi clienti su due VPS: una **backend** che ospita i negozi e una **frontend** che li pubblica. Domini e DNS su Cloudflare, siti gestiti con Hestia, VPS collegate con **Tailscale**.

- Funzionalità del negozio: [docs/FUNZIONALITA.md](docs/FUNZIONALITA.md)
- Installare **un solo** negozio (senza hub, cPanel, XAMPP…): [docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md](docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md)
- Collegare le VPS con un tunnel Cloudflare invece di Tailscale: [docs/TUNNEL-CLOUDFLARE.md](docs/TUNNEL-CLOUDFLARE.md)
- Sicurezza (cosa è protetto, rischi che restano): [SECURITY.md](SECURITY.md)
- Note tecniche per chi sviluppa: [DEVELOPMENT.md](DEVELOPMENT.md)

## Come è fatto

```
 visitatore ──HTTPS──▶ Cloudflare (DNS, WAF) ──▶ VPS FRONTEND ──── Tailscale (WireGuard) ────▶ VPS BACKEND
                                                 Nginx: HTTPS, cache pagine 60 s,             Hestia: PHP, database,
                                                 serve la cache anche se il backend è giù     negozi dei clienti e hub

 tu (portatile con Tailscale) ───────────────▶ hub.falconefabio.it  (raggiungibile solo dalla tailnet)
```

- **Hub**: pannello che crea i negozi e mostra incassi, ordini, errori, versioni. Non ha password né chiavi di Hestia: sono gli **agenti** sulle VPS a contattarlo ogni minuto ed eseguire i lavori (dominio, database, certificato, installazione).
- **Backend**: ospita negozi, database, immagini e hub. **Nessuna porta pubblica**: si raggiunge solo dalla tailnet.
- **Frontend**: l'unica VPS esposta a Internet, e solo a Cloudflare (80/443). Inoltra al backend dentro Tailscale.
- Ogni negozio va nella **cartella principale** del dominio o in una **sottocartella**.

Nomi usati nei comandi (cambiali con i tuoi): utente Hestia `falco3205`, hub `hub.falconefabio.it`. Valori da annotare: **IP_FRONTEND** (pubblico), **TS_BACKEND** (IP Tailscale del backend, `tailscale ip -4`).

## La tua infrastruttura oggi e come ci si inserisce

Situazione di partenza (dalle tue note) e cosa cambia con questa guida:

| Oggi | Con AlienShop Hub |
|---|---|
| Le porte dei servizi backend (8010, 8080, 8095, 8096, 8097, 8765, 3002) non sono raggiungibili da Internet: UFW le blocca e accetta solo dall'IP della vecchia VPS | Stessa idea, più stretta: backend senza porte pubbliche, tutto passa da **Tailscale** (`ufw allow in on tailscale0`); il frontend è aperto solo agli IP di Cloudflare |
| I database non sono mai esposti in rete (restano locali alla VPS che li usa) | Resta così: ogni negozio usa MySQL **locale** del backend (`localhost`), mai raggiungibile da fuori |
| Il traffico pubblico è HTTPS, con WAF/DDoS dove c'è Cloudflare | Tutti i domini dei clienti passano da Cloudflare (nuvola arancione) verso il frontend |
| Le app hanno la propria autenticazione (JWT, sessioni) | Anche qui: password robuste, **2FA** su hub e admin dei negozi, limiti di tentativi, firme HMAC tra hub e negozi. Non è un'alternativa alle porte chiuse: è il secondo livello |
| Stai passando a Tailscale | È la scelta di base di questa guida (tailnet tra frontend, backend e il tuo portatile) |

Due controlli da fare sempre dopo aver cambiato il firewall:

1. **Scansione da fuori** (da un altro computer o rete): `nmap -Pn IP_PUBBLICO_BACKEND` e `nmap -Pn IP_FRONTEND`. Sul backend non deve risultare nessuna porta aperta; sul frontend solo 80/443 e, se hai un dominio dietro Cloudflare, solo da Cloudflare.
2. **Docker e UFW**: le porte pubblicate da Docker (`-p 8080:8080`) **aggirano UFW**. Se i servizi 8010, 8080, 8095, 8096, 8097, 8765, 3002 girano in container, pubblicali su `127.0.0.1:PORTA:PORTA` (oppure nel compose `ports: ["127.0.0.1:8080:8080"]`) o regola la catena `DOCKER-USER`, altrimenti UFW "chiuso" non basta.

## Prima di cominciare

1. Due VPS (Ubuntu/Debian) con **Hestia** e **PHP CLI 8.1+** (`php -v`), e un utente Hestia (`falco3205`) su entrambe. Se manca: `v-add-user falco3205 PASSWORD email@dominio.it`.
2. Account Tailscale, Cloudflare e GitHub, tutti con **2FA attiva** (anche su Hestia): chi scrive su `main` esegue codice su tutti i negozi.
3. Fai un backup/snapshot delle VPS prima di cambiare il firewall.

## 1 · Tailscale su entrambe le VPS

**Prima** nel pannello Tailscale (*Access controls*) dichiara i tag e limita chi parla con chi (i tag vanno dichiarati in `tagOwners` prima di usarli):

```json
{
  "tagOwners": { "tag:backend": ["autogroup:admin"], "tag:frontend": ["autogroup:admin"] },
  "acls": [
    { "action": "accept", "src": ["autogroup:member"], "dst": ["tag:backend:22,80,8083", "tag:frontend:22,8083"] },
    { "action": "accept", "src": ["tag:frontend"],     "dst": ["tag:backend:80"] }
  ]
}
```

Così il frontend può entrare nel backend **solo** sulla porta 80, e tu (dal portatile) su SSH, siti e pannello Hestia. Poi, come root su **backend e frontend**:

```bash
curl -fsSL https://tailscale.com/install.sh | sh
tailscale up --ssh=false --advertise-tags=tag:backend     # sul backend
tailscale up --ssh=false --advertise-tags=tag:frontend    # sul frontend
tailscale ip -4                                           # annota l'IP 100.x.y.z di ciascuna
```

Installa Tailscale anche sul tuo portatile.

## 2 · Firewall (UFW)

Usa **un solo** firewall: se scegli UFW, disattiva quello di Hestia (pannello *Server → Configure → Firewall*) per evitare regole in conflitto.

**Backend** (nessuna porta pubblica):

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow in on tailscale0                 # SSH, siti e pannello Hestia solo dalla tailnet
# ripiego SSH pubblico (solo da casa): ufw allow from IL_TUO_IP to any port 22 proto tcp
ufw enable
```

**Frontend** (80/443 solo da Cloudflare, tutto il resto dalla tailnet):

```bash
ufw default deny incoming && ufw default allow outgoing
ufw allow in on tailscale0
for ip in $(curl -fsS https://www.cloudflare.com/ips-v4 https://www.cloudflare.com/ips-v6); do
  ufw allow from "$ip" to any port 80,443 proto tcp comment 'Cloudflare'
done
ufw enable
```

Tieni aperta una seconda sessione SSH mentre abiliti UFW, e **verifica da fuori** (`nmap -Pn IP_PUBBLICO` da un altro computer): le porte devono risultare chiuse. Attenzione: le porte pubblicate da **Docker** aggirano UFW; se sulla VPS ci sono container, pubblicali su `127.0.0.1:PORTA:PORTA` o regola la catena `DOCKER-USER`.

## 3 · Cloudflare (una volta)

1. **SSL/TLS → Overview → Full (strict)**; *Always Use HTTPS* attivo; *Minimum TLS Version* 1.2.
2. **Limite di richieste al login** (*Security → WAF → Rate limiting rules*): URI che contiene `/login`, metodo POST, più di 10 richieste in 10 secondi per IP → *Block*.
3. DNS del dominio dell'hub: record `A` **`hub`** → **TS_BACKEND**, **DNS only** (nuvola grigia). Un indirizzo 100.x è raggiungibile solo dalla tailnet: l'hub non esiste per il resto di Internet.

## 4 · Backend: inoltro dalla tailnet e hub

Come root sul **backend**:

```bash
# 4.1 Nginx ascolta sull'IP Tailscale e inoltra ai siti di Hestia (preserva l'host)
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/node/tailnet-forwarder.sh -o /root/tailnet-forwarder.sh
bash /root/tailnet-forwarder.sh            # stampa: http://TS_BACKEND:80

# 4.2 Hub su un dominio dedicato (solo tailnet, quindi senza certificato)
v-add-web-domain falco3205 hub.falconefabio.it
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/hub/install.sh -o /root/install-hub.sh
bash /root/install-hub.sh --user falco3205 --domain hub.falconefabio.it --root --no-ssl --scheme http --admin-email tu@tuamail.it
```

Lo script stampa la password. Dal portatile (con Tailscale acceso) apri `http://hub.falconefabio.it`, accedi e **subito**: *Sicurezza → Verifica in due passaggi → Attiva*; conserva i codici di recupero. Il traffico dentro Tailscale è già cifrato (WireGuard).

## 5 · Collega il backend all'hub

Hub → **Server → Collega un server**:

| Campo | Valore |
|---|---|
| Nome | `VPS backend` |
| Ruolo | Backend |
| IP pubblico | IP principale del backend (quello di `v-list-sys-ips plain`) |
| Indirizzo del backend visto dal frontend | `http://TS_BACKEND:80` |
| Utente Hestia | `falco3205` |

Copia il comando mostrato (contiene il token, visibile una sola volta) ed eseguilo **come root sul backend**: installa l'agente, il template Hestia `alienshop` e l'inoltro Tailscale. Verifica:

```bash
tail -n 5 /var/log/alienshop-node.log       # nessun errore
```

Nel pannello il server deve risultare **online** entro un minuto.

## 6 · Collega il frontend

Hub → **Server → Collega un server** → Ruolo **Frontend**, IP pubblico **IP_FRONTEND**, utente Hestia `falco3205`. Esegui il comando mostrato **come root sul frontend**: installa l'agente, il template `alienshop-edge` (proxy con cache) e legge l'IP vero dei visitatori da Cloudflare. L'agente raggiunge l'hub passando dalla tailnet (`hub.falconefabio.it` → TS_BACKEND).

## 7 · Crea un negozio per un cliente

1. **DNS del dominio del cliente** (su Cloudflare): `A` `@` → **IP_FRONTEND**, *Proxied* (nuvola arancione); `www` `CNAME` → `@`, *Proxied*. Fallo **prima**: serve per emettere il certificato sul frontend.
2. Hub → **Negozi → Nuovo negozio**: dominio, nome, email dell'amministratore, tema, lingua; **dove installarlo** (cartella principale o sottocartella: il resto del sito resta com'è e deve stare sullo stesso backend); **Server backend** `VPS backend`; **Pubblicazione** *Tramite frontend* → `VPS frontend`.
3. Entro un minuto il backend installa il negozio e il frontend lo pubblica. La scheda mostra esito, registro e **password iniziale**: salvala e premi *Le ho salvate*.
4. **Apri l'admin del negozio** dalla scheda (accesso monouso) e attiva la 2FA (*Admin → Profilo → Sicurezza*).

Se Cloudflare mostra errore 526, il certificato sul frontend non c'è (il dominio non puntava ancora a Cloudflare quando è partita l'installazione): crea un **certificato di origine** (Cloudflare: *SSL/TLS → Origin Server → Create Certificate*), salva `dominio.it.crt` e `dominio.it.key` in `/root/ssl/dominio.it/` sul frontend e lancia:

```bash
v-add-web-domain-ssl falco3205 dominio.it /root/ssl/dominio.it
```

**Dominio creato direttamente da Hestia sul backend**: scegli il template web `alienshop`; dopo pochi secondi il negozio si installa e compare nell'hub (poi lo pubblichi dal frontend come sopra).

## 8 · Sicurezza: regole e come muoversi

**Regole fisse**
- 2FA su GitHub, Cloudflare, Tailscale, Hestia, **hub** e **ogni admin di negozio**.
- Backend senza porte pubbliche; frontend aperto solo a Cloudflare; SSH e pannello Hestia (8083) **solo via Tailscale**.
- Hub su un **dominio dedicato** e solo in tailnet; mai sotto un percorso di un altro sito.
- Branch `main` protetto su GitHub; **aggiornamento automatico dei negozi spento** finché non sei sicuro di ciò che pubblichi.
- Password lunghe e diverse (gestore di password); quelle iniziali dei negozi si cambiano al primo accesso.
- Il token dei server non si condivide: se esce, *Server → Genera un nuovo token* e rilancia il comando.
- Verifica periodicamente da fuori che le porte siano chiuse (`nmap`) e che `https://dominio.it/config/config.php` e `/storage/` rispondano 403/404.

**Nel quotidiano**
1. Lavora su un branch, prova (`php tests/run.php && php tests/hub.php && php tests/node.php`), poi unisci in `main`.
2. **Aggiorna prima un negozio di prova** dall'hub (*scheda → Aggiorna il software*), controlla, poi gli altri. Ogni aggiornamento salva una copia del database e si può annullare.
3. Guarda ogni giorno la **Panoramica**: negozi che non rispondono, errori, aggiornamenti, ordini da spedire.
4. **Backup**: non sono centralizzati. Su ogni backend usa i backup di Hestia e porta una copia fuori dalla VPS; prima di lavori grossi `php bin/console backup:create` nella cartella del negozio.
5. Se sospetti una violazione: sospendi il negozio dall'hub, rigenera il token del server, cambia le password, controlla *Attività* e `/var/log/alienshop-node.log`, e leggi [SECURITY.md](SECURITY.md).

## 9 · Se qualcosa non funziona

| Sintomo | Dove guardare |
|---|---|
| Server "offline" nell'hub | `tail -f /var/log/alienshop-node.log` sulla VPS; `cat /etc/alienshop/node.json`; `tailscale status`; `curl -I http://hub.falconefabio.it` dalla VPS |
| Negozio in errore | Hub → **Attività** → apri il lavoro: c'è il registro dei comandi eseguiti |
| 502 sul dominio del cliente | dal frontend: `curl -I -H 'Host: dominio.it' http://TS_BACKEND:80/`; `tailscale ping TS_BACKEND`; ACL di Tailscale |
| 526 / errore certificato | certificato di origine sul frontend (passo 7) |
| Tutti i visitatori con lo stesso IP, login bloccati per tutti | manca `--cloudflare` sul frontend, o nel backend dell'hub il campo *IP da cui il backend vede arrivare il frontend* (con Tailscale lo imposta il pannello: `127.0.0.1` + IP del backend) |
| Il negozio non si aggiorna | file modificati a mano (l'aggiornamento Git si ferma): `git status` nella cartella del negozio |

## Limiti noti (leggi prima di usarlo con clienti veri)

I comandi Hestia, i template Nginx, Tailscale/UFW/Cloudflare, i pagamenti reali, la posta e il Sistema di Interscambio non sono stati provati su server reali: i test coprono hub, agente (in modalità prova) e negozio in locale. Alla prima installazione fai una prova con un dominio tuo e tieni d'occhio la pagina *Attività* dell'hub.

## Test

```bash
php tests/run.php     # negozio
php tests/hub.php     # hub
php tests/node.php    # agente (modalità prova: non esegue nulla sul server)
```
