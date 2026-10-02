#!/usr/bin/env bash
# Collega una VPS all'hub AlienShop. Come root.
#
#   Backend (server "nudo": niente Hestia; installa Nginx, PHP-FPM, MariaDB e l'agente):
#     ALIEN_NODE_TOKEN=TOKEN bash setup.sh --hub http://hub.dominio.it --role backend [--listen auto|IP]
#   Frontend (con Hestia: pubblica i domini dei clienti come proxy con cache):
#     ALIEN_NODE_TOKEN=TOKEN bash setup.sh --hub http://hub.dominio.it --role edge --user UTENTE_HESTIA [--cloudflare]
#
#   --listen auto  (backend) Nginx ascolta solo sull'IP Tailscale del server (default); oppure un IP preciso
#   --cloudflare   (frontend) legge l'IP vero dei visitatori da Cloudflare e aggiorna ogni settimana gli intervalli
#   --repo/--branch  origine del software (default Falco3205/AlienShop, main)
set -euo pipefail

HUB=""; TOKEN=""; ROLE=""; HUSER=""; REPO="Falco3205/AlienShop"; BRANCH="main"; USERS=""; CLOUDFLARE=0; LISTEN="auto"
while [ $# -gt 0 ]; do
  case "$1" in
    --hub) HUB="$2"; shift 2;;
    --token) TOKEN="$2"; shift 2;;
    --role) ROLE="$2"; shift 2;;
    --user) HUSER="$2"; shift 2;;
    --repo) REPO="$2"; shift 2;;
    --branch) BRANCH="$2"; shift 2;;
    --users) USERS="$2"; shift 2;;
    --cloudflare) CLOUDFLARE=1; shift;;
    --listen) LISTEN="$2"; shift 2;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 1;;
  esac
done

die() { echo "Errore: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root"
TOKEN="${TOKEN:-${ALIEN_NODE_TOKEN:-}}"
[ -n "$HUB" ] && [ -n "$TOKEN" ] || die "servono --hub e il token (ALIEN_NODE_TOKEN)"
[ "$ROLE" = "backend" ] || [ "$ROLE" = "edge" ] || die "--role deve essere backend o edge"
echo "$TOKEN" | grep -Eq '^[0-9a-f]{48}$' || die "token non valido"
echo "$REPO" | grep -Eq '^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$' || die "repository non valido"
echo "$BRANCH" | grep -Eq '^[A-Za-z0-9_./-]{1,60}$' || die "branch non valido"
echo "$HUB" | grep -Eq '^https?://[A-Za-z0-9._:/-]+$' || die "indirizzo dell'hub non valido"
HESTIA="${HESTIA:-/usr/local/hestia}"
if [ "$ROLE" = "edge" ]; then
  [ -n "$HUSER" ] || die "per il frontend serve --user (utente Hestia)"
  [ -d "$HESTIA/bin" ] || die "Hestia non trovato in $HESTIA: il frontend usa Hestia"
  "$HESTIA/bin/v-list-user" "$HUSER" >/dev/null 2>&1 || die "l'utente Hestia '$HUSER' non esiste"
fi

echo "==> Dipendenze di base"
command -v apt-get >/dev/null || die "serve Debian o Ubuntu"
for bin in git php curl; do
  command -v "$bin" >/dev/null 2>&1 || { apt-get install -y "$bin" php-cli >/dev/null || die "$bin non installato"; }
done
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "serve PHP CLI 8.1 o superiore"

echo "==> Agente in /opt/alienshop"
if [ -d /opt/alienshop/.git ]; then
  git -C /opt/alienshop fetch -q origin "$BRANCH" && git -C /opt/alienshop reset -q --hard FETCH_HEAD
else
  git clone -q --depth 1 -b "$BRANCH" "https://github.com/$REPO.git" /opt/alienshop
fi
ln -sf /opt/alienshop/node/alienshop-node /usr/local/bin/alienshop-node
chmod +x /opt/alienshop/node/alienshop-node

if [ "$ROLE" = "backend" ]; then
  bash /opt/alienshop/node/stack.sh --listen "$LISTEN"
fi

echo "==> Configurazione"
mkdir -p /etc/alienshop
ALLOWED="[\"$HUSER\"]"
if [ -n "$USERS" ]; then ALLOWED="$(php -r 'echo json_encode(array_values(array_filter(explode(",", $argv[1]))));' "$USERS")"; fi
umask 077
php -r '
$c = ["hub" => $argv[1], "token" => $argv[2], "role" => $argv[3], "user" => $argv[4], "users" => json_decode($argv[5], true), "repo" => $argv[6], "branch" => $argv[7], "hestia" => $argv[8]];
file_put_contents("/etc/alienshop/node.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
' "$HUB" "$TOKEN" "$ROLE" "$HUSER" "$ALLOWED" "$REPO" "$BRANCH" "$HESTIA"
chmod 600 /etc/alienshop/node.json
umask 022

nginx_reload() { if nginx -t >/dev/null 2>&1; then systemctl reload nginx >/dev/null 2>&1 || "$HESTIA/bin/v-restart-web" >/dev/null 2>&1 || true; else return 1; fi; }

if [ "$ROLE" = "edge" ]; then
  echo "==> Template Hestia 'alienshop-edge' (proxy con cache)"
  TPL_DIR="$HESTIA/data/templates/web/nginx/php-fpm"
  install -m 644 /opt/alienshop/node/hestia/alienshop-edge.tpl "$TPL_DIR/alienshop-edge.tpl"
  install -m 644 /opt/alienshop/node/hestia/alienshop-edge.stpl "$TPL_DIR/alienshop-edge.stpl"
  install -m 755 /opt/alienshop/node/hestia/alienshop-edge.sh "$TPL_DIR/alienshop-edge.sh"
  mkdir -p /var/cache/nginx/alienshop
  NGUSER="$(awk '/^user /{gsub(";","",$2); print $2; exit}' /etc/nginx/nginx.conf 2>/dev/null || true)"
  [ -n "$NGUSER" ] && chown -R "$NGUSER" /var/cache/nginx/alienshop || true
  echo 'proxy_cache_path /var/cache/nginx/alienshop levels=1:2 keys_zone=alienshop:50m max_size=2g inactive=7d use_temp_path=off;' > /etc/nginx/conf.d/alienshop-cache.conf

  if [ "$CLOUDFLARE" -eq 1 ]; then
    echo "==> Cloudflare: IP reale dei visitatori"
    install -m 755 /opt/alienshop/node/alienshop-cloudflare-ips /usr/local/bin/alienshop-cloudflare-ips
    /usr/local/bin/alienshop-cloudflare-ips || echo "Attenzione: impossibile scaricare gli intervalli di Cloudflare."
    printf '#!/bin/sh\n/usr/local/bin/alienshop-cloudflare-ips\n' > /etc/cron.weekly/alienshop-cloudflare
    chmod 755 /etc/cron.weekly/alienshop-cloudflare
  fi
  nginx_reload || true
fi

echo "==> Esecuzione periodica (ogni minuto)"
cat > /etc/cron.d/alienshop-node <<CRON
* * * * * root flock -n /run/alienshop-node.lock /usr/local/bin/alienshop-node run >> /var/log/alienshop-node.log 2>&1
CRON
chmod 644 /etc/cron.d/alienshop-node

echo "==> Primo contatto con l'hub"
/usr/local/bin/alienshop-node run && echo "Server collegato." || echo "Attenzione: l'hub non risponde ancora. Controlla l'indirizzo e il token; l'agente riproverà ogni minuto."
