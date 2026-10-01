# AlienShop

E-commerce leggero e veloce in PHP, senza dipendenze, con un **pannello (Hub)** per installare e controllare i negozi dei tuoi clienti su due VPS: una **backend** che ospita i negozi e una **frontend** che li pubblica. Dominio e DNS su Cloudflare, gestione dei siti con Hestia.

- Funzionalità del negozio: [docs/FUNZIONALITA.md](docs/FUNZIONALITA.md)
- Installare **un solo** negozio (senza hub, cPanel, XAMPP…): [docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md](docs/INSTALLAZIONE-SINGOLO-NEGOZIO.md)
- Sicurezza (cosa è protetto, rischi che restano): [SECURITY.md](SECURITY.md)
- Note tecniche per chi sviluppa: [DEVELOPMENT.md](DEVELOPMENT.md)

## Come è fatto

```
                         ┌────────── HUB (hub.falconefabio.it) ──────────┐
                         │ crea negozi · incassi, ordini, errori · accessi│
                         └──▲─────────────────────▲──────────────────▲────┘
                            │ lavori (ogni min.)  │                  │ dati firmati
 visitatore ─▶ Cloudflare ─▶ VPS FRONTEND ──tunnel Cloudflare──▶ VPS BACKEND
              (DNS, WAF)     Nginx: HTTPS, cache   (backend-origin…)   Hestia: PHP + database
                             pagine 60 s, servito                      + negozi dei clienti
                             anche se il backend è giù                 + l'hub
```

- **Hub**: app a parte che gestisce tutto da un unico pannello. Non ha password né chiavi di Hestia: sono gli **agenti** sulle VPS a contattarlo ogni minuto e a eseguire i lavori (crea dominio, database, certificato, installa il negozio).
- **Backend**: ospita negozi, database, immagini e l'hub. Non è esposto direttamente: Cloudflare lo raggiunge attraverso il tunnel.
- **Frontend**: riceve il traffico dei domini dei clienti, mette in cache e inoltra al backend attraverso il tunnel.
- Ogni negozio si installa nella **cartella principale** del dominio o in una **sottocartella**.

Nomi usati nei comandi (cambiali con i tuoi): utente Hestia `falco3205`, hub `hub.falconefabio.it`, tunnel verso il backend `backend-origin.falconefabio.it`.

## Prima di cominciare

1. Due VPS (Ubuntu/Debian) con **Hestia Control Panel** già installato e **PHP CLI 8.1+** (`php -v`). Conosci i due IP pubblici: **IP_BACKEND** e **IP_FRONTEND**.
2. Un utente Hestia su entrambe (qui `falco3205`). Se manca: `v-add-user falco3205 PASSWORD email@dominio.it` (come root).
3. Un account Cloudflare con i domini (zone) e un account GitHub con questo repository.
4. **Attiva la 2FA** sul tuo account GitHub, su Cloudflare e sul pannello Hestia (chi scrive su `main` esegue codice su tutti i negozi).

## 1 · Cloudflare (una volta)

Dal pannello Cloudflare:

1. **SSL/TLS → Overview → Full (strict)**; *Edge Certificates*: *Always Use HTTPS* attivo, *Minimum TLS Version* 1.2.
2. **IP delle tue VPS sempre ammessi** (l'hub interroga i negozi e gli agenti contattano l'hub passando da Cloudflare, e senza questa regola Bot Fight Mode può bloccarli): *Manage Account → Configurations → Tools → IP Access Rules* → aggiungi **IP_BACKEND** e **IP_FRONTEND** con azione *Allow*, per tutte le zone dell'account. Per le zone di clienti che non sono nel tuo account fai lo stesso nella loro zona.
3. **Limite di richieste al login** (*Security → WAF → Rate limiting rules*): se l'URI contiene `/login` e il metodo è POST, più di 10 richieste in 10 secondi per IP → *Block*.
4. Non attivare regole che mostrano sfide (captcha) sui percorsi `/hub/` dei negozi: l'hub non le supera. La regola 2 basta.

Gli altri record DNS (`hub`, `backend-origin`) li crea il comando del tunnel al passo 2.

## 2 · VPS backend

Tutti i comandi **come root** sul backend.

**2.1 Tunnel Cloudflare (cloudflared)**

```bash
mkdir -p --mode=0755 /usr/share/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' > /etc/apt/sources.list.d/cloudflared.list
apt update && apt install -y cloudflared

cloudflared tunnel login                       # apri il link, scegli la zona falconefabio.it
cloudflared tunnel create alienshop            # stampa l'ID del tunnel (UUID)
cloudflared tunnel route dns alienshop backend-origin.falconefabio.it
cloudflared tunnel route dns alienshop hub.falconefabio.it
mkdir -p /etc/cloudflared && cp ~/.cloudflared/*.json /etc/cloudflared/
```

Crea `/etc/cloudflared/config.yml` (sostituisci `UUID` e `IP_BACKEND`):

```yaml
tunnel: UUID
credentials-file: /etc/cloudflared/UUID.json
ingress:
  - hostname: backend-origin.falconefabio.it
    service: http://127.0.0.1:8088          # relay con segreto (passo 3)
  - hostname: hub.falconefabio.it
    service: http://IP_BACKEND:80           # Nginx di Hestia
  - service: http_status:404
```

```bash
cloudflared service install && systemctl enable --now cloudflared
systemctl status cloudflared --no-pager
```

**2.2 Installa l'hub** (sottodominio dedicato: tienilo separato dagli altri siti)

```bash
v-add-web-domain falco3205 hub.falconefabio.it
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/hub/install.sh -o /root/install-hub.sh
bash /root/install-hub.sh --user falco3205 --domain hub.falconefabio.it --root --no-ssl --admin-email tu@tuamail.it
```

`--no-ssl` perché HTTPS lo gestisce Cloudflare (un reindirizzamento HTTPS sul server creerebbe un ciclo). Lo script stampa la password. Apri `https://hub.falconefabio.it`, accedi e **subito**: *Sicurezza → Verifica in due passaggi → Attiva*. Conserva i codici di recupero.

> Se preferisci l'hub in una cartella di un dominio esistente, `--path alienshop` al posto di `--root`: è meno sicuro (stessa origine di altri siti).

## 3 · Collega il backend all'hub

Nel pannello dell'hub: **Server → Collega un server**:

| Campo | Valore |
|---|---|
| Nome | `VPS backend` |
| Ruolo | Backend |
| IP pubblico | IP_BACKEND |
| Hostname del tunnel | `backend-origin.falconefabio.it` |
| Utente Hestia | `falco3205` |

Compare un comando: **copialo ed eseguilo come root sul backend** (contiene il token, mostrato una sola volta). Ha questa forma:

```bash
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/node/setup.sh -o setup.sh && \
sudo ALIEN_NODE_TOKEN=… bash setup.sh --hub https://hub.falconefabio.it --role backend --user falco3205 --cloudflare --relay-secret … --repo Falco3205/AlienShop --branch main
```

Installa l'agente, il template Hestia `alienshop`, il relay protetto sulla porta 8088 e legge l'IP vero dei visitatori da Cloudflare. Verifica:

```bash
tail -n 5 /var/log/alienshop-node.log                      # nessun errore
curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8088/   # deve rispondere 403
```

Nel pannello il server deve risultare **online** entro un minuto.

## 4 · VPS frontend

Nel pannello dell'hub: **Server → Collega un server** → Ruolo **Frontend**, IP pubblico **IP_FRONTEND**, utente Hestia `falco3205` (quello che ospiterà i domini proxy). Esegui il comando mostrato **come root sul frontend** (stessa forma, con `--role edge --cloudflare`). Installa l'agente, il template `alienshop-edge` (proxy con cache) e l'IP reale da Cloudflare.

Restringi poi 80 e 443 ai soli IP di Cloudflare (passo 6.2).

## 5 · Crea un negozio per un cliente

1. **DNS del dominio del cliente** (su Cloudflare): record `A` `@` → **IP_FRONTEND**, *Proxied* (nuvola arancione); `www` come `CNAME` verso `@`, *Proxied*. Lo fai **prima**, così il certificato del frontend si emette.
2. Hub → **Negozi → Nuovo negozio**: dominio, nome, email dell'amministratore, tema, lingua.
   - **Dove installarlo**: nella cartella principale del dominio oppure in una sottocartella (il resto del sito resta com'è: deve stare sullo stesso backend).
   - **Server backend**: `VPS backend`. **Pubblicazione**: *Tramite frontend* → server `VPS frontend`.
3. Entro un minuto l'agente sul backend installa il negozio e quello sul frontend lo pubblica. La scheda del negozio mostra l'esito, il registro e la **password iniziale**: salvala e premi *Le ho salvate*.
4. Dalla scheda: **Apri l'admin del negozio** (accesso monouso), poi nel negozio *Admin → Profilo → Sicurezza* attiva la 2FA.

Se il certificato sul frontend non si emette (ad esempio il dominio non punta ancora a Cloudflare), il negozio risponde ma Cloudflare mostra errore 526: installa un **certificato di origine Cloudflare** (*SSL/TLS → Origin Server → Create Certificate*), salva certificato e chiave in `/root/ssl/dominio.it.crt` e `.key` e sul frontend:

```bash
mkdir -p /root/ssl/dominio.it && cp /root/ssl/dominio.it.crt /root/ssl/dominio.it/dominio.it.crt && cp /root/ssl/dominio.it.key /root/ssl/dominio.it/dominio.it.key
v-add-web-domain-ssl falco3205 dominio.it /root/ssl/dominio.it
```

**Dominio creato direttamente da Hestia sul backend**: scegli il template web `alienshop`; dopo pochi secondi il negozio si installa e compare nell'hub (poi pubblicalo dal frontend come sopra).

## 6 · Sicurezza: regole e come muoversi

**6.1 Regole fisse**
- 2FA su: GitHub, Cloudflare, Hestia, **hub** e **ogni admin di negozio**.
- Hub su un **sottodominio dedicato**, mai sotto un percorso di un altro sito.
- Branch `main` protetto su GitHub; **aggiornamento automatico dei negozi spento** (si accende solo quando sei sicuro di ciò che pubblichi).
- Mai riusare password; usa un gestore di password. Le password iniziali dei negozi si cambiano al primo accesso.
- Il token dei server e il segreto del relay non si condividono né si incollano in chat; se escono, rigenera il token in *Server → Genera un nuovo token* e rilancia il comando.

**6.2 Firewall e accessi alle VPS**

```bash
# SSH solo con chiave (come root, su entrambe): in /etc/ssh/sshd_config
#   PasswordAuthentication no
#   PermitRootLogin prohibit-password
systemctl reload ssh
```

Nel pannello Hestia (*Firewall*) o da terminale sul **frontend**: consenti 80/443 **solo** agli intervalli Cloudflare (elenco: https://www.cloudflare.com/ips) e rimuovi la regola aperta a tutti. Prima aggiungi le regole Cloudflare, poi togli quella generica, e tieni aperta una seconda sessione SSH per non chiuderti fuori:

```bash
for ip in $(curl -fsS https://www.cloudflare.com/ips-v4); do v-add-firewall-rule ACCEPT "$ip" "80,443" TCP "Cloudflare"; done
v-list-firewall            # trova l'ID della regola 80/443 da 0.0.0.0/0 …
# v-delete-firewall-rule ID   (solo dopo aver verificato che i siti rispondano dai domini)
```

Sul **backend** non serve esporre 80/443 a Internet (il traffico arriva dal tunnel): limita anche la porta di Hestia (8083) al tuo IP o a Cloudflare Access.

**6.3 Come muoversi nel quotidiano**
1. **Lavora su un branch**, prova (tests: `php tests/run.php && php tests/hub.php && php tests/node.php`), poi unisci in `main`.
2. **Aggiorna prima un negozio di prova** dall'hub (*scheda negozio → Aggiorna il software*), controlla, poi gli altri. Ogni aggiornamento salva prima una copia del database e si può annullare (*Torna alla versione precedente*).
3. Guarda ogni giorno la **Panoramica** dell'hub: negozi che non rispondono, errori nei log, aggiornamenti disponibili, ordini da spedire.
4. **Backup**: dall'hub non sono ancora centralizzati; su ogni backend usa i backup di Hestia (*Backup*) e scarica una copia fuori dal server. Prima di lavori grossi: `php bin/console backup:create` nella cartella del negozio.
5. Se sospetti una violazione: sospendi il negozio dall'hub, rigenera il token del server, cambia le password, controlla *Attività* e `/var/log/alienshop-node.log`, e leggi [SECURITY.md](SECURITY.md).

## 7 · Se qualcosa non funziona

| Sintomo | Dove guardare |
|---|---|
| Server "offline" nell'hub | `tail -f /var/log/alienshop-node.log` sulla VPS; `cat /etc/alienshop/node.json` (hub e token); `curl -I https://hub.falconefabio.it` |
| Negozio in errore | Hub → **Attività** → apri il lavoro: c'è il registro dei comandi eseguiti |
| 502/526 sul dominio del cliente | `systemctl status cloudflared` sul backend; `curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8088/` (403 = relay vivo); certificato sul frontend (passo 5) |
| Tutti i visitatori hanno lo stesso IP / login bloccati per tutti | manca `--cloudflare` o il campo *IP da cui il backend vede arrivare il frontend* del backend nell'hub (con il tunnel è `127.0.0.1` + IP del backend: lo imposta il pannello se lo lasci vuoto) |
| Il negozio non si aggiorna | file modificati a mano (l'aggiornamento Git si ferma): `git status` nella cartella del negozio |

## Limiti noti (leggi prima di usarlo con clienti veri)

I comandi Hestia, i template Nginx, il tunnel Cloudflare, i pagamenti reali, la posta e il Sistema di Interscambio non sono stati provati su server reali: i test coprono hub, agente (in modalità prova) e negozio in locale. Alla prima installazione reale fai una prova con un dominio tuo e tieni d'occhio la pagina *Attività* dell'hub.

## Test

```bash
php tests/run.php     # negozio
php tests/hub.php     # hub
php tests/node.php    # agente (modalità prova: non esegue nulla sul server)
```
