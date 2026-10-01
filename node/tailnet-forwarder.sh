#!/usr/bin/env bash
# Rende i siti di Hestia raggiungibili dalla tailnet Tailscale (da eseguire sul backend, come root).
# Nginx ascolta sull'IP Tailscale del server (porta 80) e inoltra all'IP principale preservando l'intestazione Host.
# Idempotente: si può rilanciare.
set -euo pipefail
die() { echo "Errore: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root"
HESTIA="${HESTIA:-/usr/local/hestia}"
command -v tailscale >/dev/null 2>&1 || die "Tailscale non installato (curl -fsSL https://tailscale.com/install.sh | sh && tailscale up)"
TSIP="$(tailscale ip -4 2>/dev/null | head -1)"
echo "$TSIP" | grep -Eq '^100\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$' || die "Tailscale non è connesso (tailscale up)"
MAINIP="$("$HESTIA/bin/v-list-sys-ips" plain 2>/dev/null | awk 'NR==1{print $1}')"
[ -n "$MAINIP" ] || MAINIP="$(hostname -I | awk '{print $1}')"
[ -n "$MAINIP" ] || die "non riesco a determinare l'IP del server"
cat > /etc/nginx/conf.d/alienshop-tailnet.conf <<TAILNET
server {
    listen $TSIP:80;
    server_name _;
    client_max_body_size 64m;
    location / {
        proxy_pass http://$MAINIP:80;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Forwarded-For \$http_x_forwarded_for;
        proxy_set_header X-Real-IP \$http_x_real_ip;
        proxy_set_header X-Forwarded-Proto \$http_x_forwarded_proto;
        proxy_set_header Connection "";
        proxy_read_timeout 700s;
    }
}
TAILNET
if nginx -t >/dev/null 2>&1; then
  systemctl reload nginx >/dev/null 2>&1 || "$HESTIA/bin/v-restart-web" >/dev/null 2>&1 || true
else
  rm -f /etc/nginx/conf.d/alienshop-tailnet.conf
  die "configurazione Nginx non valida: modifica annullata"
fi
echo "Pronto. Indirizzo del backend per il frontend e per l'hub: http://$TSIP:80"
