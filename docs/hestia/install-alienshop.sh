#!/usr/bin/env bash
# Installa AlienShop su un dominio di Hestia Control Panel.
# Uso (da root):  bash install-alienshop.sh UTENTE DOMINIO EMAIL_ADMIN [NOME_NEGOZIO]
# Variabili opzionali: ALIEN_ADMIN_PASSWORD, ALIEN_THEME, ALIEN_DEMO=1, ALIEN_REPO, ALIEN_BRANCH, SKIP_SSL=1
set -euo pipefail

USER_NAME="${1:-}"; DOMAIN="${2:-}"; ADMIN_EMAIL="${3:-}"; STORE_NAME="${4:-AlienShop}"
REPO="${ALIEN_REPO:-https://github.com/Falco3205/AlienShop.git}"
BRANCH="${ALIEN_BRANCH:-main}"
HESTIA="${HESTIA:-/usr/local/hestia}"
TPL_NAME="alienshop"

die() { echo "Errore: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root"
[ -n "$USER_NAME" ] && [ -n "$DOMAIN" ] && [ -n "$ADMIN_EMAIL" ] || die "uso: $0 UTENTE DOMINIO EMAIL_ADMIN [NOME_NEGOZIO]"
[ -d "$HESTIA/bin" ] || die "Hestia non trovato in $HESTIA"
export PATH="$HESTIA/bin:$PATH"
command -v git >/dev/null || die "git non installato (apt install git)"
v-list-user "$USER_NAME" >/dev/null 2>&1 || die "l'utente Hestia '$USER_NAME' non esiste"
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "serve PHP CLI 8.1 o superiore"

ADMIN_PASSWORD="${ALIEN_ADMIN_PASSWORD:-$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)}"
DB_PASSWORD="$(openssl rand -hex 16)"
WEBROOT="/home/$USER_NAME/web/$DOMAIN/public_html"
as_user() { runuser -u "$USER_NAME" -- "$@"; }

echo "==> Dominio web"
if ! v-list-web-domain "$USER_NAME" "$DOMAIN" >/dev/null 2>&1; then
  v-add-web-domain "$USER_NAME" "$DOMAIN"
fi
[ -d "$WEBROOT" ] || die "cartella $WEBROOT non trovata"
leftovers="$(find "$WEBROOT" -mindepth 1 -maxdepth 1 ! -name index.html ! -name robots.txt ! -name .well-known ! -name favicon.ico | head -1)"
[ -z "$leftovers" ] || die "$WEBROOT contiene già dei file: uso una cartella vuota per non sovrascrivere nulla"

echo "==> Template Nginx"
for ext in tpl stpl; do
  src="$HESTIA/data/templates/web/nginx/php-fpm/default.$ext"
  dst="$HESTIA/data/templates/web/nginx/php-fpm/$TPL_NAME.$ext"
  [ -f "$src" ] || die "template $src non trovato"
  sed -E 's#(root[[:space:]]+)(%s?docroot%);#\1\2/public;#' "$src" > "$dst"
  grep -q '/public;' "$dst" || die "non riesco ad adattare $src: crea a mano un template con root su <docroot>/public"
done

echo "==> Download da GitHub"
rm -f "$WEBROOT/index.html" "$WEBROOT/robots.txt" "$WEBROOT/favicon.ico"
as_user git clone --depth 1 -b "$BRANCH" "$REPO" "$WEBROOT"
as_user mkdir -p "$WEBROOT/storage" "$WEBROOT/config"

echo "==> Database MySQL"
DB_SLUG="shop"
v-add-database "$USER_NAME" "$DB_SLUG" "$DB_SLUG" "$DB_PASSWORD" mysql
DB_NAME="${USER_NAME}_${DB_SLUG}"

echo "==> Impostazioni Nginx (blocco PHP in uploads, template)"
CONF_DIR="/home/$USER_NAME/conf/web/$DOMAIN"
mkdir -p "$CONF_DIR"
cat > "$CONF_DIR/nginx.conf_alienshop" <<'NGX'
location ^~ /uploads/ {
    location ~ \.(php|phtml|phar)$ { return 403; }
}
NGX
v-change-web-domain-tpl "$USER_NAME" "$DOMAIN" "$TPL_NAME"

if [ "${SKIP_SSL:-0}" != "1" ]; then
  echo "==> HTTPS (Let's Encrypt)"
  v-add-letsencrypt-domain "$USER_NAME" "$DOMAIN" || echo "Attenzione: certificato non emesso (DNS non ancora puntato?). Riprova: v-add-letsencrypt-domain $USER_NAME $DOMAIN"
  v-add-web-domain-ssl-force "$USER_NAME" "$DOMAIN" >/dev/null 2>&1 || true
fi
SCHEME="https"; [ "${SKIP_SSL:-0}" = "1" ] && SCHEME="http"

echo "==> Installazione di AlienShop"
as_user env ALIEN_ADMIN_PASSWORD="$ADMIN_PASSWORD" ALIEN_DB_PASS="$DB_PASSWORD" \
  php "$WEBROOT/bin/console" install \
  --url="$SCHEME://$DOMAIN" --store-name="$STORE_NAME" --admin-email="$ADMIN_EMAIL" \
  --db=mysql --db-host=localhost --db-name="$DB_NAME" --db-user="$DB_NAME" \
  --theme="${ALIEN_THEME:-aurora}" --demo="${ALIEN_DEMO:-0}"

echo "==> Attività pianificate"
v-add-cron-job "$USER_NAME" '*' '*' '*' '*' '*' "php $WEBROOT/bin/console cron:run" >/dev/null 2>&1 || true

cat <<DONE

AlienShop installato.
  Negozio:   $SCHEME://$DOMAIN
  Pannello:  $SCHEME://$DOMAIN/admin
  Utente:    $ADMIN_EMAIL
  Password:  $ADMIN_PASSWORD   (cambiala dal profilo)
  Database:  $DB_NAME

Aggiornare in futuro:
  runuser -u $USER_NAME -- git -C $WEBROOT pull && runuser -u $USER_NAME -- php $WEBROOT/bin/console migrate
DONE
