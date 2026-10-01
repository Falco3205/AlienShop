#!/bin/bash
# Hook del template "alienshop": parte quando a un dominio Hestia viene applicato il template.
# Argomenti di Hestia: utente dominio ip cartella-home cartella-sito
user="$1"
domain="$2"
[ -x /usr/local/bin/alienshop-node ] || exit 0
[ -f "/home/$user/web/$domain/public_html/config/config.php" ] && exit 0
nohup bash -c "sleep 10; /usr/local/bin/alienshop-node claim '$user' '$domain' --skip-template" >> /var/log/alienshop-claim.log 2>&1 &
exit 0
