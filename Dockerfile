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
