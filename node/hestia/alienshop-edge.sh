#!/bin/bash
# Hook del template Hestia "alienshop-edge": parte quando a un dominio viene applicato il template
# (anche alla creazione del dominio). Argomenti di Hestia: utente dominio ip cartella-home cartella-sito
user="$1"
domain="$2"
[ -x /usr/local/bin/alienshop-node ] || exit 0
echo "$user" | grep -Eq '^[a-z][a-z0-9_-]{0,31}$' || exit 0
echo "$domain" | grep -Eq '^([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,24}$' || exit 0
# già pubblicato dall'hub (la configurazione del proxy esiste): niente da fare
[ -f "/home/$user/conf/web/$domain/alienshop_edge.inc" ] && exit 0
nohup bash -c 'sleep 8; exec /usr/local/bin/alienshop-node claim "$1" "$2"' alienshop-claim "$user" "$domain" >> /var/log/alienshop-claim.log 2>&1 &
exit 0
