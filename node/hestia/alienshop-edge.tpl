#=========================================================================#
# AlienShop - frontend: inoltra tutto al backend e mette in cache         #
#=========================================================================#
server {
    listen      %ip%:%web_port%;
    server_name %domain_idn% %alias_idn%;
    error_log   /var/log/%web_system%/domains/%domain%.error.log error;

    include %home%/%user%/conf/web/%domain%/nginx.forcessl.conf*;

    location ^~ /.well-known/acme-challenge/ {
        root %docroot%;
        default_type text/plain;
    }

    include %home%/%user%/conf/web/%domain%/alienshop_edge.inc;
}
