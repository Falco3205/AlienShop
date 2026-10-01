# Alternativa: collegare frontend e backend con un tunnel Cloudflare

Usala solo se non vuoi (o non puoi) usare Tailscale: la guida principale è nel [README](../README.md). Qui il backend non ha porte aperte e il frontend lo raggiunge attraverso un hostname pubblico del tunnel (`backend-origin.tuodominio.it`), protetto da un segreto.

## Sul backend (come root)

```bash
mkdir -p --mode=0755 /usr/share/keyrings
curl -fsSL https://pkg.cloudflare.com/cloudflare-main.gpg | tee /usr/share/keyrings/cloudflare-main.gpg >/dev/null
echo 'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared any main' > /etc/apt/sources.list.d/cloudflared.list
apt update && apt install -y cloudflared

cloudflared tunnel login
cloudflared tunnel create alienshop
cloudflared tunnel route dns alienshop backend-origin.tuodominio.it
cloudflared tunnel route dns alienshop hub.tuodominio.it
mkdir -p /etc/cloudflared && cp ~/.cloudflared/*.json /etc/cloudflared/
```

`/etc/cloudflared/config.yml` (sostituisci `UUID` e `IP_BACKEND`):

```yaml
tunnel: UUID
credentials-file: /etc/cloudflared/UUID.json
ingress:
  - hostname: backend-origin.tuodominio.it
    service: http://127.0.0.1:8088          # relay con segreto
  - hostname: hub.tuodominio.it
    service: http://IP_BACKEND:80           # Nginx di Hestia
  - service: http_status:404
```

```bash
cloudflared service install && systemctl enable --now cloudflared
```

## Hub

```bash
v-add-web-domain falco3205 hub.tuodominio.it
curl -fsSL https://raw.githubusercontent.com/Falco3205/AlienShop/main/hub/install.sh -o /root/install-hub.sh
bash /root/install-hub.sh --user falco3205 --domain hub.tuodominio.it --root --no-ssl --admin-email tu@tuamail.it
```

`--no-ssl`: HTTPS lo gestisce Cloudflare (un redirect HTTPS sul server creerebbe un ciclo).

## Collega i server dal pannello

Backend: nel pannello *Server → Collega un server*, ruolo Backend, **Hostname del tunnel** `backend-origin.tuodominio.it` (lascia vuoto l'indirizzo): il pannello genera un segreto e un comando con `--cloudflare --relay-secret …` da lanciare come root sul backend. Verifica: `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8088/` deve dare 403. Il frontend si collega come nella guida principale.

In Cloudflare aggiungi **IP_BACKEND** e **IP_FRONTEND** alle *IP Access Rules* (azione Allow), altrimenti Bot Fight Mode può bloccare l'hub e gli agenti. Il resto (zone, SSL, nuovo negozio) è identico alla guida principale.
