#!/usr/bin/env bash
# Installa l'hub AlienShop sul server con Hestia (il frontend), in una cartella di un dominio esistente
# (es. https://falconefabio.it/alienshop/). Come root:
#   bash install.sh --user falco3205 --domain falconefabio.it --admin-email tu@example.com [--path alienshop] [--allow IP,IP] [--repo utente/repo] [--branch main]
#
#   --path X     cartella del pannello (default: alienshop)
#   --allow IPs  consente l'accesso al pannello solo a questi IP/CIDR (separati da virgola); consigliato.
#                Richiede l'IP reale dei visitatori (node/setup.sh --cloudflare sul frontend, se sei dietro Cloudflare)
# La password dell'amministratore viene generata e stampata (oppure imposta HUB_ADMIN_PASSWORD).
set -euo pipefail

HUSER=""; DOMAIN=""; EMAIL=""; SUBPATH="alienshop"; ALLOW=""; REPO="Falco3205/AlienShop"; BRANCH="main"
while [ $# -gt 0 ]; do
  case "$1" in
    --user) HUSER="$2"; shift 2;;
    --domain) DOMAIN="$2"; shift 2;;
    --admin-email) EMAIL="$2"; shift 2;;
    --path) SUBPATH="$2"; shift 2;;
    --allow) ALLOW="$2"; shift 2;;
    --repo) REPO="$2"; shift 2;;
    --branch) BRANCH="$2"; shift 2;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 1;;
  esac
done

die() { echo "Errore: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root"
[ -n "$HUSER" ] && [ -n "$DOMAIN" ] && [ -n "$EMAIL" ] || die "servono --user, --domain e --admin-email"
echo "$HUSER" | grep -Eq '^[a-z][a-z0-9_-]{0,31}$' || die "utente non valido"
echo "$DOMAIN" | grep -Eq '^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,24}$' || die "dominio non valido"
echo "$SUBPATH" | grep -Eq '^[a-z0-9][a-z0-9_-]{0,40}$' || die "cartella non valida"
echo "$REPO" | grep -Eq '^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$' || die "repository non valido"
echo "$BRANCH" | grep -Eq '^[A-Za-z0-9_./-]{1,60}$' || die "branch non valido"
ALLOW_RULES=""
if [ -n "$ALLOW" ]; then
  for ip in $(echo "$ALLOW" | tr ',' ' '); do
    echo "$ip" | grep -Eq '^[0-9a-fA-F:.]+(/[0-9]{1,3})?$' || die "indirizzo non valido in --allow: $ip"
    ALLOW_RULES="${ALLOW_RULES}    allow $ip;\n"
  done
  ALLOW_RULES="${ALLOW_RULES}    deny all;\n"
fi
HESTIA="${HESTIA:-/usr/local/hestia}"
export PATH="$HESTIA/bin:$PATH"
[ -d "$HESTIA/bin" ] || die "Hestia non trovato in $HESTIA: l'hub si installa sul server con Hestia (il frontend)"
v-list-web-domain "$HUSER" "$DOMAIN" >/dev/null 2>&1 || die "il dominio $DOMAIN non esiste in Hestia per l'utente $HUSER"
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "serve PHP CLI 8.1 o superiore"
command -v git >/dev/null || die "git non installato"

TARGET="/home/$HUSER/web/$DOMAIN/public_html/$SUBPATH"
[ ! -e "$TARGET" ] || [ -d "$TARGET/.git" ] || die "$TARGET esiste già e non è un'installazione AlienShop"
PASSWORD="${HUB_ADMIN_PASSWORD:-$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)}"

echo "==> Download"
if [ -d "$TARGET/.git" ]; then
  runuser -u "$HUSER" -- git -C "$TARGET" pull --ff-only
else
  runuser -u "$HUSER" -- git clone --depth 1 -b "$BRANCH" "https://github.com/$REPO.git" "$TARGET"
fi

echo "==> Installazione dell'hub"
if [ ! -f "$TARGET/hub/config/config.php" ]; then
  HUB_ADMIN_PASSWORD="$PASSWORD" runuser -u "$HUSER" -- php "$TARGET/hub/bin/hub" install --url="https://$DOMAIN/$SUBPATH" --admin-email="$EMAIL"
fi

echo "==> Nginx (solo $TARGET/hub/public è raggiungibile dal web)"
SOCK="$(grep -hE '^listen[[:space:]]*=' /etc/php/*/fpm/pool.d/$DOMAIN.conf 2>/dev/null | head -1 | sed -E 's/^listen[[:space:]]*=[[:space:]]*//' || true)"
[ -n "$SOCK" ] || SOCK="/run/php/php-fpm-$DOMAIN.sock"
CONF="/home/$HUSER/conf/web/$DOMAIN"
mkdir -p "$CONF"
for f in nginx.conf_alienshop_hub nginx.ssl.conf_alienshop_hub; do
{
printf 'location ^~ /%s/ {\n' "$SUBPATH"
printf "$ALLOW_RULES"
cat <<NGX
    alias $TARGET/hub/public/;
    try_files \$uri @alienshop_hub;
    location ~ \.(php|phtml|phar)\$ { return 404; }
}
location = /$SUBPATH {
$(printf "$ALLOW_RULES")
    try_files /__none @alienshop_hub;
}
location @alienshop_hub {
    include /etc/nginx/fastcgi_params;
    fastcgi_pass unix:$SOCK;
    fastcgi_param SCRIPT_FILENAME $TARGET/hub/public/index.php;
    fastcgi_param SCRIPT_NAME /$SUBPATH/index.php;
    fastcgi_param REQUEST_URI \$request_uri;
    fastcgi_read_timeout 700s;
}
NGX
} > "$CONF/$f"
done
v-restart-web >/dev/null 2>&1 || systemctl reload nginx

echo "==> Aggiornamento dei dati dei negozi ogni 5 minuti"
v-add-cron-job "$HUSER" '*/5' '*' '*' '*' '*' "php $TARGET/hub/bin/hub poll" >/dev/null 2>&1 || true

cat <<DONE

Hub installato.
  Pannello:  https://$DOMAIN/$SUBPATH/
  Utente:    $EMAIL
  Password:  $PASSWORD   (cambiala da Impostazioni)

Al primo accesso il pannello ti obbliga ad attivare la verifica in due passaggi.
Prossimo passo: "Server → Collega un server" e incolla il comando sulle VPS.
DONE
