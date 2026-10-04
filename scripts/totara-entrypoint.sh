#!/bin/sh
set -e

# An empty volume or bind mount over the cert directory starts without keys.
generate-certificates.sh

# A bind-mounted directory arrives owned by the host user, and the SP list is written by apache.
if ! su www-data -s /bin/sh -c 'test -w /var/www/metadata_storage'; then
    chown www-data /var/www/metadata_storage
fi

exec docker-php-entrypoint "$@"
