#!/bin/sh
# Generate any IdP certificate that does not exist yet and leave the rest alone, so a volume over
# the cert directory keeps the same keys across rebuilds and restarts.
set -e

cd "${1:-/var/www/html/cert}"

generate() {
    name=$1
    days=$2

    if [ -f "$name.crt" ] && [ -f "$name.pem" ]; then
        return
    fi

    echo "Generating the $name certificate"
    openssl req -subj /C=NZ/ST=Wellington/L=Wellington/O=Totara/OU=Development/CN=server \
        -newkey rsa:3072 -new -x509 -days "$days" -nodes -out "$name.crt" -keyout "$name.pem"
    chown www-data "$name.crt" "$name.pem"
}

generate server 3650
generate new_server 3650
# Valid for one day from generation, so it only reads as expired once that day has passed.
generate expired_server 1
