#!/usr/bin/env bash
# Installa l'hub AlienShop su un server Debian/Ubuntu "nudo" (senza Hestia), di solito la VPS backend.
# Come root:
#   bash install.sh --domain hub.tuodominio.it --admin-email tu@example.com [--listen auto|IP] [--scheme http|https] [--repo utente/repo] [--branch main]
#
#   --listen auto  Nginx ascolta solo sull'IP Tailscale del server (default): l'hub esiste solo nella tailnet
#   --scheme http  indirizzo del pannello in http (consigliato in tailnet: il traffico è già cifrato da WireGuard)
# La password dell'amministratore viene generata e stampata (oppure imposta HUB_ADMIN_PASSWORD).
set -euo pipefail

DOMAIN=""; EMAIL=""; LISTEN="auto"; SCHEME="http"; REPO="Falco3205/AlienShop"; BRANCH="main"
while [ $# -gt 0 ]; do
  case "$1" in
    --domain) DOMAIN="$2"; shift 2;;
    --admin-email) EMAIL="$2"; shift 2;;
    --listen) LISTEN="$2"; shift 2;;
    --scheme) SCHEME="$2"; shift 2;;
    --repo) REPO="$2"; shift 2;;
    --branch) BRANCH="$2"; shift 2;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 1;;
  esac
done

die() { echo "Errore: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root"
[ -n "$DOMAIN" ] && [ -n "$EMAIL" ] || die "servono --domain e --admin-email"
echo "$DOMAIN" | grep -Eq '^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,24}$' || die "dominio non valido"
[ "$SCHEME" = "http" ] || [ "$SCHEME" = "https" ] || die "--scheme deve essere http o https"
echo "$REPO" | grep -Eq '^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$' || die "repository non valido"
echo "$BRANCH" | grep -Eq '^[A-Za-z0-9_./-]{1,60}$' || die "branch non valido"
command -v git >/dev/null || { apt-get update -qq && apt-get install -y -qq git >/dev/null; }

DIR=/opt/alienshop-hub
HUBUSER=alienhub
PASSWORD="${HUB_ADMIN_PASSWORD:-$(openssl rand -base64 18 | tr -d '/+=' | cut -c1-16)}"

echo "==> Utente e download"
id -u "$HUBUSER" >/dev/null 2>&1 || useradd --system --home-dir "$DIR" --no-create-home --shell /usr/sbin/nologin --user-group "$HUBUSER"
install -d -o "$HUBUSER" -g "$HUBUSER" -m 0750 "$DIR"
if [ -d "$DIR/.git" ]; then
  runuser -u "$HUBUSER" -- git -C "$DIR" pull --ff-only
elif [ -z "$(ls -A "$DIR")" ]; then
  runuser -u "$HUBUSER" -- git clone --depth 1 -b "$BRANCH" "https://github.com/$REPO.git" "$DIR"
else
  die "$DIR esiste già e non è un'installazione AlienShop"
fi

echo "==> Nginx, PHP-FPM, MariaDB"
bash "$DIR/node/stack.sh" --listen "$LISTEN"
LISTENIP="$(php -r '$c = json_decode(file_get_contents("/etc/alienshop/stack.json"), true); echo $c["listen"];')"
PHPV="$(php -r '$c = json_decode(file_get_contents("/etc/alienshop/stack.json"), true); echo $c["php"];')"
NGUSER="$(php -r '$c = json_decode(file_get_contents("/etc/alienshop/stack.json"), true); echo $c["nginx_user"];')"

echo "==> Installazione dell'hub"
if [ ! -f "$DIR/hub/config/config.php" ]; then
  HUB_ADMIN_PASSWORD="$PASSWORD" runuser -u "$HUBUSER" -- php "$DIR/hub/bin/hub" install --url="$SCHEME://$DOMAIN" --admin-email="$EMAIL"
fi
usermod -aG "$HUBUSER" "$NGUSER"

echo "==> PHP-FPM e Nginx"
cat > "/etc/php/$PHPV/fpm/pool.d/$HUBUSER.conf" <<POOL
[$HUBUSER]
user = $HUBUSER
group = $HUBUSER
listen = /run/php/$HUBUSER.sock
listen.owner = $NGUSER
listen.group = $NGUSER
listen.mode = 0660
pm = ondemand
pm.max_children = 6
pm.process_idle_timeout = 30s
php_admin_value[open_basedir] = $DIR:/tmp
php_admin_value[expose_php] = off
php_admin_value[disable_functions] = exec,passthru,shell_exec,system,proc_open,popen,pcntl_exec,pcntl_fork,dl
php_admin_flag[allow_url_include] = off
POOL
cat > "/etc/nginx/conf.d/as-hub-$DOMAIN.conf" <<NGX
server {
    listen $LISTENIP:80;
    server_name $DOMAIN;
    server_tokens off;
    root $DIR/hub/public;
    index index.php;
    location ~ /\.(?!well-known/) { deny all; return 404; }
    location / { try_files \$uri /index.php?\$query_string; }
    location ~ \.php\$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $DIR/hub/public/index.php;
        fastcgi_pass unix:/run/php/$HUBUSER.sock;
        fastcgi_read_timeout 120s;
    }
}
NGX
"php-fpm$PHPV" -t >/dev/null 2>&1 || die "configurazione PHP-FPM non valida"
nginx -t >/dev/null 2>&1 || die "configurazione Nginx non valida"
systemctl reload "php$PHPV-fpm" && systemctl reload nginx

echo "==> Aggiornamento dei dati dei negozi ogni 5 minuti"
echo "*/5 * * * * $HUBUSER /usr/bin/php $DIR/hub/bin/hub poll >/dev/null 2>&1" > /etc/cron.d/alienhub
chmod 644 /etc/cron.d/alienhub

cat <<DONE

Hub installato.
  Pannello:  $SCHEME://$DOMAIN/   (Nginx in ascolto su $LISTENIP:80)
  Utente:    $EMAIL
  Password:  $PASSWORD   (cambiala da Impostazioni)

Prossimi passi:
  1. DNS: record A "$DOMAIN" -> $LISTENIP  (solo DNS, senza proxy Cloudflare)
  2. Apri il pannello e attiva subito la verifica in due passaggi (Sicurezza)
  3. Aggiornare l'hub: runuser -u $HUBUSER -- php $DIR/hub/bin/hub update
DONE
