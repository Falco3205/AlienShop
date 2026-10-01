#!/usr/bin/env bash
# Installa l'hub AlienShop su un dominio Hestia, in una sottocartella (es. falconefabio.it/alienshop).
# Uso (come root): bash install.sh --user falco3205 --domain hub.falconefabio.it --root --admin-email tu@example.com [--repo utente/repo] [--branch main]
#   --root   installa nella radice del dominio (CONSIGLIATO: un sottodominio dedicato isola il pannello dagli altri siti)
#   --path X installa in una sottocartella (es. falconefabio.it/alienshop)
# La password admin viene generata e stampata (oppure imposta HUB_ADMIN_PASSWORD).
set -euo pipefail

HUSER=""; DOMAIN=""; SUBPATH="alienshop"; PATH_SET=0; EMAIL=""; REPO="Falco3205/AlienShop"; BRANCH="main"
while [ $# -gt 0 ]; do
  case "$1" in
    --user) HUSER="$2"; shift 2;;
    --domain) DOMAIN="$2"; shift 2;;
    --path) SUBPATH="$2"; PATH_SET=1; shift 2;;
    --root) SUBPATH=""; PATH_SET=1; shift;;
    --admin-email) EMAIL="$2"; shift 2;;
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
[ -z "$SUBPATH" ] || echo "$SUBPATH" | grep -Eq '^[a-z0-9][a-z0-9_-]{0,40}$' || die "cartella non valida"
echo "$REPO" | grep -Eq '^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$' || die "repository non valido"
HESTIA="${HESTIA:-/usr/local/hestia}"
export PATH="$HESTIA/bin:$PATH"
v-list-web-domain "$HUSER" "$DOMAIN" >/dev/null 2>&1 || die "il dominio $DOMAIN non esiste in Hestia per l'utente $HUSER"
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "serve PHP CLI 8.1 o superiore"
command -v git >/dev/null || die "git non installato"

if [ -n "$SUBPATH" ]; then TARGET="/home/$HUSER/web/$DOMAIN/public_html/$SUBPATH"; else TARGET="/home/$HUSER/web/$DOMAIN/public_html"; fi
if [ -n "$SUBPATH" ]; then
  [ ! -e "$TARGET" ] || [ -d "$TARGET/.git" ] || die "$TARGET esiste già e non è un'installazione AlienShop"
else
  [ -d "$TARGET/.git" ] || [ -z "$(find "$TARGET" -mindepth 1 -maxdepth 1 ! -name index.html ! -name robots.txt ! -name .well-known ! -name favicon.ico | head -1)" ] || die "$TARGET contiene già dei file: usa un dominio o sottodominio dedicato"
  rm -f "$TARGET/index.html" "$TARGET/robots.txt" "$TARGET/favicon.ico"
fi
PASSWORD="${HUB_ADMIN_PASSWORD:-$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)}"

echo "==> Download"
if [ -d "$TARGET/.git" ]; then
  runuser -u "$HUSER" -- git -C "$TARGET" pull --ff-only
else
  runuser -u "$HUSER" -- git clone --depth 1 -b "$BRANCH" "https://github.com/$REPO.git" "$TARGET"
fi

echo "==> Installazione dell'hub"
if [ ! -f "$TARGET/hub/config/config.php" ]; then
  HUB_ADMIN_PASSWORD="$PASSWORD" runuser -u "$HUSER" -- php "$TARGET/hub/bin/hub" install --url="https://$DOMAIN${SUBPATH:+/$SUBPATH}" --admin-email="$EMAIL"
fi

echo "==> Nginx"
CONF="/home/$HUSER/conf/web/$DOMAIN"
mkdir -p "$CONF"
if [ -z "$SUBPATH" ]; then
  TPL_DIR="$HESTIA/data/templates/web/nginx/php-fpm"
  for ext in tpl stpl; do
    sed -E 's#(root[[:space:]]+)(%s?docroot%);#\1\2/hub/public;#' "$TPL_DIR/default.$ext" > "$TPL_DIR/alienshop-hub.$ext"
    grep -q '/hub/public;' "$TPL_DIR/alienshop-hub.$ext" || die "non riesco ad adattare il template di Hestia"
  done
  v-change-web-domain-tpl "$HUSER" "$DOMAIN" alienshop-hub
  v-add-letsencrypt-domain "$HUSER" "$DOMAIN" >/dev/null 2>&1 || echo "Attenzione: certificato non emesso (DNS non ancora puntato?)."
  v-add-web-domain-ssl-force "$HUSER" "$DOMAIN" >/dev/null 2>&1 || true
else
SOCK="$(grep -hE '^listen[[:space:]]*=' /etc/php/*/fpm/pool.d/$DOMAIN.conf 2>/dev/null | head -1 | sed -E 's/^listen[[:space:]]*=[[:space:]]*//' || true)"
[ -n "$SOCK" ] || SOCK="/run/php/php-fpm-$DOMAIN.sock"
for f in nginx.conf_alienshop_hub nginx.ssl.conf_alienshop_hub; do
cat > "$CONF/$f" <<NGX
location ^~ /$SUBPATH/ {
    alias $TARGET/hub/public/;
    try_files \$uri @alienshop_hub;
    location ~ \.(php|phtml|phar)\$ { return 404; }
}
location = /$SUBPATH { return 301 /$SUBPATH/; }
location @alienshop_hub {
    include /etc/nginx/fastcgi_params;
    fastcgi_pass unix:$SOCK;
    fastcgi_param SCRIPT_FILENAME $TARGET/hub/public/index.php;
    fastcgi_param SCRIPT_NAME /$SUBPATH/index.php;
    fastcgi_param REQUEST_URI \$request_uri;
}
NGX
done
fi
v-restart-web >/dev/null 2>&1 || systemctl reload nginx

echo "==> Aggiornamento dati dei negozi ogni 5 minuti"
v-add-cron-job "$HUSER" '*/5' '*' '*' '*' '*' "php $TARGET/hub/bin/hub poll" >/dev/null 2>&1 || true

cat <<DONE

Hub installato.
  Pannello:  https://$DOMAIN/${SUBPATH:+$SUBPATH/}
  Utente:    $EMAIL
  Password:  $PASSWORD   (cambiala da Impostazioni)

Prossimo passo: nel pannello, "Server → Collega un server" e incolla il comando sulle VPS.
DONE
