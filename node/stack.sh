#!/usr/bin/env bash
# Prepara un server Debian/Ubuntu "nudo" (senza Hestia) come backend AlienShop: Nginx, PHP-FPM, MariaDB.
# Idempotente. Uso (come root):  bash stack.sh [--listen auto|IP]
#   --listen auto  (default) ascolta solo sull'IP Tailscale del server; se Tailscale manca si ferma
#   --listen IP    ascolta su quell'indirizzo (0.0.0.0 = tutte le interfacce: esponi il server solo se hai un firewall)
set -euo pipefail

LISTEN="auto"
while [ $# -gt 0 ]; do
  case "$1" in
    --listen) LISTEN="$2"; shift 2;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 1;;
  esac
done

die() { echo "Errore: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root"
command -v apt-get >/dev/null || die "serve Debian o Ubuntu (apt-get)"

if [ "$LISTEN" = "auto" ]; then
  command -v tailscale >/dev/null 2>&1 || die "Tailscale non installato: curl -fsSL https://tailscale.com/install.sh | sh && tailscale up  (oppure usa --listen IP)"
  LISTEN="$(tailscale ip -4 2>/dev/null | head -1)"
  echo "$LISTEN" | grep -Eq '^100\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}$' || die "Tailscale non è connesso (tailscale up)"
fi
echo "$LISTEN" | grep -Eq '^([0-9]{1,3}\.){3}[0-9]{1,3}$' || die "indirizzo di ascolto non valido: $LISTEN"

echo "==> Pacchetti (Nginx, PHP-FPM, MariaDB)"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq nginx php-fpm php-cli php-mysql php-sqlite3 php-mbstring php-gd php-curl php-intl php-xml php-zip mariadb-server git curl unzip cron openssl >/dev/null
php -r 'exit(PHP_VERSION_ID >= 80100 ? 0 : 1);' || die "serve PHP 8.1 o superiore (php -v)"
PHPV="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"
systemctl enable --now nginx "php$PHPV-fpm" mariadb cron >/dev/null 2>&1 || true

echo "==> MariaDB: solo locale, senza account di prova"
mysql --batch >/dev/null 2>&1 <<'SQL' || true
DELETE FROM mysql.global_priv WHERE User='';
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
SQL
if ! grep -rqE '^\s*bind-address\s*=\s*127\.0\.0\.1' /etc/mysql/ 2>/dev/null; then
  mkdir -p /etc/mysql/mariadb.conf.d
  printf '[mysqld]\nbind-address = 127.0.0.1\n' > /etc/mysql/mariadb.conf.d/99-alienshop-local.cnf
  systemctl restart mariadb >/dev/null 2>&1 || true
fi

echo "==> Nginx: indirizzo $LISTEN, host sconosciuti scartati"
rm -f /etc/nginx/sites-enabled/default
mkdir -p /etc/nginx/alienshop.d /var/www/alienshop/shops /var/www/alienshop/sites
cat > /etc/nginx/conf.d/00-alienshop-default.conf <<NGX
server {
    listen $LISTEN:80 default_server;
    server_name _;
    server_tokens off;
    return 444;
}
NGX
nginx -t >/dev/null 2>&1 || die "configurazione Nginx non valida"
systemctl reload nginx >/dev/null 2>&1 || true

NGUSER="$(awk '/^user /{gsub(";","",$2); print $2; exit}' /etc/nginx/nginx.conf 2>/dev/null || true)"
mkdir -p /etc/alienshop
php -r '
$f = "/etc/alienshop/stack.json";
$c = is_file($f) ? (json_decode((string)file_get_contents($f), true) ?: []) : [];
$c = ["php" => $argv[1], "listen" => $argv[2], "nginx_user" => $argv[3] ?: "www-data", "base" => "/var/www/alienshop"] + $c;
$c["php"] = $argv[1]; $c["listen"] = $argv[2];
file_put_contents($f, json_encode($c, JSON_PRETTY_PRINT));
' "$PHPV" "$LISTEN" "$NGUSER"
chmod 644 /etc/alienshop/stack.json
echo "Server pronto: PHP $PHPV, Nginx in ascolto su $LISTEN:80."
