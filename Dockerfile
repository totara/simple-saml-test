FROM node:18-alpine as node_builder

# Define the SAML version installed
ARG SAML_VERSION=1.19.7
ARG SAML_TAR_URL=https://github.com/simplesamlphp/simplesamlphp/releases/download/v${SAML_VERSION}/simplesamlphp-${SAML_VERSION}.tar.gz
ARG SAML_TAR_NAME=simplesamlphp-${SAML_VERSION}.tar.gz

# Create and install the application, then cleanup the behaviour
RUN mkdir /app && \
    cd /app && \
    mkdir samlphp && \
    wget ${SAML_TAR_URL} && \
    tar -xzf ${SAML_TAR_NAME} -C ./samlphp --strip-components=1 && \
    cd samlphp && \
    npm install && \
    npm run build && \
    rm -rf node_modules && \
    rm -rf metadata


FROM php:8.0-apache-buster

COPY --from=node_builder /app/samlphp/ /var/www/html/

ENV SIMPLESAMLPHP_CONFIG_DIR /var/www/config/
ENV LISTEN_PORT 8089

# Default expose port
EXPOSE 8089

# Update apache listen ports
RUN sed -ri -e 's!/var/www/html!/var/www/html/www/!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e 's!/var/www/!/var/www/html/www/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf && \
    sed -ri -e 's!Listen 80!Listen ${LISTEN_PORT}!g' /etc/apache2/ports.conf && \
    sed -ri -e 's!:80>!:${LISTEN_PORT}>!g' /etc/apache2/sites-available/*.conf && \
    sed -ri -e "s!usenewui!:usenewui2!g" /var/www/html/www/index.php # Hack to prevent auto-redirect to login page


# Generate the internal certificate
RUN cd /var/www/html/cert &&  \
    openssl req -subj /C=NZ/ST=Wellington/L=Wellington/O=Totara/OU=Development/CN=server \
      -newkey rsa:3072 -new -x509 -days 3652 -nodes -out server.crt -keyout server.pem && \
    chown www-data server.* && \
    chown www-data /var/www/html/cache

COPY config/ /var/www/config/
COPY metadata/ /var/www/html/metadata/
COPY modules/totara/ /var/www/html/modules/totara/
