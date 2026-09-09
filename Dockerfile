FROM node:18-alpine AS node_builder

# Define the SAML version installed
ARG SAML_VERSION=2.0.3
ARG SAML_TAR_URL=https://github.com/simplesamlphp/simplesamlphp/releases/download/v${SAML_VERSION}/simplesamlphp-${SAML_VERSION}.tar.gz
ARG SAML_TAR_NAME=simplesamlphp-${SAML_VERSION}.tar.gz

# Create and install the application, then cleanup the behaviour
RUN mkdir /app && \
    cd /app && \
    mkdir samlphp && \
    wget ${SAML_TAR_URL} && \
    tar -xzf ${SAML_TAR_NAME} -C ./samlphp --strip-components=1 && \
    cd samlphp && \
    rm -rf metadata


FROM composer:2 AS key_transport_deps

# phpseclib can produce RSA-OAEP ciphertext with digests the bundled xmlseclibs
# cannot. Used only by the configurable key transport - see the README.
WORKDIR /key-transport
RUN composer init --no-interaction --name=totara/key-transport-deps && \
    composer require --no-interaction --no-progress phpseclib/phpseclib:^3.0


FROM php:8.0-apache-buster AS dev

COPY --from=node_builder /app/samlphp/ /var/www/html/

ENV SIMPLESAMLPHP_CONFIG_DIR=/var/www/config/
ENV SIMPLESAMLPHP_METADATA_DIR=/var/www/metadata/
ENV SIMPLESAMLPHP_METADATA_STORAGE_DIR=/var/www/metadata_storage/
ENV LISTEN_PORT=8089

# Default expose port
EXPOSE 8089

# Update apache listen ports
RUN sed -ri -e 's!/var/www/html!/var/www/html/public/!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!/var/www/html/public/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    sed -ri -e 's!Listen 80!Listen ${LISTEN_PORT}!g' /etc/apache2/ports.conf && \
    sed -ri -e 's!:80>!:${LISTEN_PORT}>!g' /etc/apache2/sites-available/*.conf

# Generate the internal certificate
RUN cd /var/www/html/cert &&  \
    openssl req -subj /C=NZ/ST=Wellington/L=Wellington/O=Totara/OU=Development/CN=server \
      -newkey rsa:3072 -new -x509 -days 3650 -nodes -out server.crt -keyout server.pem && \
    openssl req -subj /C=NZ/ST=Wellington/L=Wellington/O=Totara/OU=Development/CN=server \
      -newkey rsa:3072 -new -x509 -days 3650 -nodes -out new_server.crt -keyout new_server.pem && \
    openssl req -subj /C=NZ/ST=Wellington/L=Wellington/O=Totara/OU=Development/CN=server \
      -newkey rsa:3072 -new -x509 -days 1 -nodes -out expired_server.crt -keyout expired_server.pem && \
    chown www-data *.crt && \
    chown www-data *.pem

# Expose PHP errors to the CLI
RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini" && \
    echo "log_errors = On\nerror_log = /dev/stderr" > "$PHP_INI_DIR/conf.d/error.ini"

RUN mkdir -p /var/www/metadata_storage && \
    chown www-data /var/www/metadata_storage

# --- configurable key transport -------------------------------------------
# SimpleSAMLphp hardcodes rsa-oaep-mgf1p key transport and AES-128-CBC, so out
# of the box there is no way to test an SP against anything else. This patches
# a hook into the vendored saml2 library. It stays inert unless the mounted IdP
# config sets key_transport.mode, so the image behaves exactly as before by
# default.
COPY --from=key_transport_deps /key-transport/vendor/phpseclib /var/www/html/vendor/phpseclib
COPY --from=key_transport_deps /key-transport/vendor/paragonie /var/www/html/vendor/paragonie
COPY patches/totara-key-transport.php /var/www/html/vendor/simplesamlphp/saml2/src/SAML2/
COPY patches/key-transport.patch /tmp/key-transport.patch

# --fuzz=0 so that bumping SAML_VERSION fails the build rather than silently
# dropping the hook.
RUN cd /var/www/html && \
    patch -p1 --fuzz=0 < /tmp/key-transport.patch && \
    rm /tmp/key-transport.patch
# --- end configurable key transport ---------------------------------------



FROM php:8.0-apache-buster AS prod

ENV SIMPLESAMLPHP_CONFIG_DIR=/var/www/config/
ENV SIMPLESAMLPHP_METADATA_DIR=/var/www/metadata/
ENV SIMPLESAMLPHP_METADATA_STORAGE_DIR=/var/www/metadata_storage/
ENV LISTEN_PORT=8089

# Default expose port
EXPOSE 8089

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --from=dev /var/www/html /var/www/html
COPY --from=dev /etc/apache2/ /etc/apache2/

COPY config/ /var/www/config/
COPY metadata/ /var/www/metadata/
COPY modules/totara/ /var/www/html/modules/totara/

RUN mkdir -p /var/www/metadata_storage && \
    chown www-data /var/www/metadata_storage
