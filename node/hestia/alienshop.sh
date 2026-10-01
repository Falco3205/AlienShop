#!/bin/bash
# Hook del template "alienshop": parte quando a un dominio Hestia viene applicato il template.
# Argomenti di Hestia: utente dominio ip cartella-home cartella-sito
user="$1"
domain="$2"
[ -x /usr/local/bin/alienshop-node ] || exit 0
[ -f "/home/$user/web/$domain/public_html/config/config.php" ] && exit 0
echo "$user" | grep -Eq '^[a-z][a-z0-9_-]{0,31}$' || exit 0
echo "$domain" | grep -Eq '^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,24}$' || exit 0
nohup bash -c 'sleep 10; exec /usr/local/bin/alienshop-node claim "$1" "$2" --skip-template' alienshop-claim "$user" "$domain" >> /var/log/alienshop-claim.log 2>&1 &
exit 0
