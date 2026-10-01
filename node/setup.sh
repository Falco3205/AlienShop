#!/usr/bin/env bash
# Collega una VPS con Hestia all'hub AlienShop (agente + template Hestia).
# Uso (come root):
#   bash setup.sh --hub https://dominio/alienshop --token TOKEN --role backend|edge --user UTENTE_HESTIA [--repo utente/repo] [--branch main] [--users u1,u2]
set -euo pipefail

HUB=""; TOKEN=""; ROLE=""; HUSER=""; REPO="Falco3205/AlienShop"; BRANCH="main"; USERS=""
while [ $# -gt 0 ]; do
  case "$1" in
    --hub) HUB="$2"; shift 2;;
    --token) TOKEN="$2"; shift 2;;
    --role) ROLE="$2"; shift 2;;
    --user) HUSER="$2"; shift 2;;
    --repo) REPO="$2"; shift 2;;
    --branch) BRANCH="$2"; shift 2;;
    --users) USERS="$2"; shift 2;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 1;;
  esac
done

die() { echo "Errore: $*" >&2; exit 1; }
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root"
TOKEN="${TOKEN:-${ALIEN_NODE_TOKEN:-}}"
[ -n "$HUB" ] && [ -n "$TOKEN" ] && [ -n "$HUSER" ] || die "servono --hub, il token (ALIEN_NODE_TOKEN) e --user"
[ "$ROLE" = "backend" ] || [ "$ROLE" = "edge" ] || die "--role deve essere backend o edge"
echo "$TOKEN" | grep -Eq '^[0-9a-f]{48}$' || die "token non valido"
echo "$REPO" | grep -Eq '^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$' || die "repository non valido"
echo "$HUB" | grep -Eq '^https?://[A-Za-z0-9._:/-]+$' || die "indirizzo dell'hub non valido"
HESTIA="${HESTIA:-/usr/local/hestia}"
[ -d "$HESTIA/bin" ] || die "Hestia non trovato in $HESTIA"
"$HESTIA/bin/v-list-user" "$HUSER" >/dev/null 2>&1 || die "l'utente Hestia '$HUSER' non esiste"

echo "==> Dipendenze"
for bin in git php curl; do
  command -v "$bin" >/dev/null 2>&1 || { command -v apt-get >/dev/null && apt-get install -y "$bin" php-cli >/dev/null || die "$bin non installato"; }
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

echo "==> Configurazione"
mkdir -p /etc/alienshop
ALLOWED="[\"$HUSER\"]"
if [ -n "$USERS" ]; then ALLOWED="$(php -r 'echo json_encode(array_values(array_filter(explode(",", $argv[1]))));' "$USERS")"; fi
php -r '
$c = ["hub" => $argv[1], "token" => $argv[2], "role" => $argv[3], "user" => $argv[4], "users" => json_decode($argv[5], true), "repo" => $argv[6], "branch" => $argv[7], "hestia" => $argv[8]];
file_put_contents("/etc/alienshop/node.json", json_encode($c, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
' "$HUB" "$TOKEN" "$ROLE" "$HUSER" "$ALLOWED" "$REPO" "$BRANCH" "$HESTIA"
chmod 600 /etc/alienshop/node.json

TPL_DIR="$HESTIA/data/templates/web/nginx/php-fpm"
if [ "$ROLE" = "backend" ]; then
  echo "==> Template Hestia 'alienshop' (document root su public/)"
  for ext in tpl stpl; do
    src="$TPL_DIR/default.$ext"
    [ -f "$src" ] || die "template $src non trovato"
    sed -E 's#(root[[:space:]]+)(%s?docroot%);#\1\2/public;#' "$src" > "$TPL_DIR/alienshop.$ext"
    grep -q '/public;' "$TPL_DIR/alienshop.$ext" || die "non riesco ad adattare $src"
  done
  install -m 755 /opt/alienshop/node/hestia/alienshop.sh "$TPL_DIR/alienshop.sh"
else
  echo "==> Template Hestia 'alienshop-edge' (proxy con cache)"
  install -m 644 /opt/alienshop/node/hestia/alienshop-edge.tpl "$TPL_DIR/alienshop-edge.tpl"
  install -m 644 /opt/alienshop/node/hestia/alienshop-edge.stpl "$TPL_DIR/alienshop-edge.stpl"
  mkdir -p /var/cache/nginx/alienshop
  NGUSER="$(awk '/^user /{gsub(";","",$2); print $2; exit}' /etc/nginx/nginx.conf 2>/dev/null || true)"
  [ -n "$NGUSER" ] && chown -R "$NGUSER" /var/cache/nginx/alienshop || true
  echo 'proxy_cache_path /var/cache/nginx/alienshop levels=1:2 keys_zone=alienshop:50m max_size=2g inactive=7d use_temp_path=off;' > /etc/nginx/conf.d/alienshop-cache.conf
fi

echo "==> Esecuzione periodica (ogni minuto)"
cat > /etc/cron.d/alienshop-node <<CRON
* * * * * root flock -n /run/alienshop-node.lock /usr/local/bin/alienshop-node run >> /var/log/alienshop-node.log 2>&1
CRON
chmod 644 /etc/cron.d/alienshop-node

echo "==> Primo contatto con l'hub"
/usr/local/bin/alienshop-node run && echo "Server collegato." || echo "Attenzione: l'hub non risponde ancora. Controlla l'indirizzo e il token; l'agente riproverà ogni minuto."
