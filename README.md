# Test SAML IDP Docker

This is a wrapper around SimpleSAMLphp which provides a test SAML Identity Provider to use in Totara instances. It is
for testing the SAML2 connections only and should not be used in any production site.

We currently embed SimpleSAMLphp version **2.0.3**. If you'd like to test with the 1.19.7 line please checkout the latest `v1.x` tags.

## Configuration

| Variable      | Description                                                                     |
|---------------|---------------------------------------------------------------------------------|
| `LISTEN_PORT` | The port used to access the service. Defaults to `8089`.                        |
| `SITE_TITLE`  | Override the default site title, used when running multiple to tell them apart. |

## Getting Started

There is no published image - build it yourself from this repository.

```shell
# Build the image (the default target is the production stage)
git clone git@github.com:totara/simple-saml-test.git
cd simple-saml-test
docker build -t simple-saml-test:local .

# Start the service
docker run --rm -p 8089:8089 -e LISTEN_PORT=8089 -it simple-saml-test:local
```

Rebuild whenever you pull this repository, and note that each build generates fresh IdP certificates - any service
provider holding the old metadata will need to refresh it.

Once started, you can access the service via `http://localhost:{LISTEN_PORT}` (defaults to 8089).

Open the site, login as admin, and then navigate to Manage Service Providers.
Add any SP instances on the page there, the URL must be the full URL to your metadata (it does not fully validate).

Eg: `http://{YOUR_SP_INSTANCE}/path/to/metadata.php`

We currently do not support raw XML dumps, the SAML image must be able to download the metadata file from your Service Provider directly.
You can teach docker the IP address of your service if it isn't resolvable.

```shell
# Instance is running directly on the host machine
docker run --add-host={YOUR_SP_INSTANCE}:host-gateway ... -it simple-saml-test:local

# Instance is somewhere else, replace the domain & IP
docker run --add-host={YOUR_SP_INSTANCE}:{IP_OF_SITE} ... -it simple-saml-test:local
```

### Using Totara Docker Dev

If you're using Totara Docker Dev library, you can add this image to the service directly. Build it first, as above -
the tag below refers to your local build.

Create a new file called `saml.yml` and add it to the `custom` directory in your Totara docker project.

Add the following contents:

```yaml
version: "3.7"

services:
  saml2:
    image: simple-saml-test:local
    networks:
      - totara
    ports:
      - "8089:8089"
    environment:
      - LISTEN_PORT=8089
      - SITE_TITLE="Testing"
```

Make sure you add `saml2` to your local hosts file, so it resolves in your browser.

Start the docker service using `t up saml2`.

Try and access `http://saml2:8089` and confirm you see the test environment.

*Important*: The URL that Totara and the URL that you access the site on via your browser must be the same.

The path to the metadata file depends on what SAML plugin you are using which is why it's not specified here.

## Custom Users

By default, there's a hard-coded list of users and attributes. However, you can provide your own PHP file via volumes and replace the user list with your own.

Create a new file called `custom-auth-sources.php` with the following structure:

```php
<?php

return [
// username:password => [array of attributes]
    'my_user:password1' => [
        'uid' => ['my_uid'],
        'username' => ['my_username'],
    ],
    'another:password' => [
        'uid' => ['another'],
        'username' => ['annie_example'],
        'firstname' => ['annie']
    ],
]
```

The `username:password` section applies to the IdP, while the internal array is what will be posted back to the SP.
In the example above, the `my_user` user is known as `my_username` or `my_uid` to the service provider and will never see `my_user`.

Once created, include it as a volume, such as:
`docker run ... -v /path/to/custom-auth-sources.php:/var/www/custom-auth-sources.php ... -it simple-saml-test:local`

## Custom IdP Configuration

To change the settings in `./metadata/saml20-idp-hosted.php` you can create a file called `custom-saml20-idp-hosted.php`.
Return an array of settings to override or merge into the `saml20-idp-hosted.php` main file.

Once created, include it as a volume, such as:
`docker run ... -v /path/to/custom-saml20-idp-hosted.php:/var/www/custom-saml20-idp-hosted.php ... -it simple-saml-test:local`

### Enable Other Certificates
Three certificates are provided with this docker image:
+ server.crt / server.pem - Normal key that lasts 10 years
+ new_server.crt / new_server.pem - Another key that lasts 10 years
+ expired_server.crt / expired_server.pem - Key that's expired.

The first is automatically used, but the new and expired can be set by adding the following to your `custom-saml20-idp-hosted.php` override.

```php
# To change the active key
return [
  'privatekey' => 'new_server.pem',
  'certificate' => 'new_server.crt',
];

# To have both the regular & new keys together
return [
  'privatekey' => 'server.pem',
  'certificate' => 'server.crt',
  'new_privatekey' => 'new_server.pem',
  'new_certificate' => 'new_server.crt',
];

# To use the expired key
return [
  'privatekey' => 'expired_server.pem',
  'certificate' => 'expired_server.crt',
];
```

### Key Transport Algorithms

When an assertion is encrypted, the IdP generates a one-off session key to encrypt it with, then wraps that session key
with the SP's public key. The algorithm used for the wrapping is the *key transport* algorithm, and it is declared
separately from the block cipher that encrypts the assertion body.

SimpleSAMLphp only ever uses `rsa-oaep-mgf1p`, the XML Encryption 1.0 algorithm, which fixes both the OAEP digest and
the MGF1 hash to SHA-1. Hardened identity providers instead use the XML Encryption 1.1 `rsa-oaep`, which declares its
digest and MGF1 hash explicitly and is normally paired with SHA-256. An SP that assumes SHA-1 cannot decrypt those
assertions at all, so this image can be told to emit them.

Add a `key_transport` key to your `custom-saml20-idp-hosted.php`:

```php
return [
    'key_transport' => [
        'mode'   => 'rsa-oaep',   // 'stock' (default) leaves SimpleSAMLphp alone
        'block'  => 'aes256-gcm', // aes128-cbc | aes256-cbc | aes128-gcm | aes256-gcm
        'digest' => 'sha256',     // OAEP digest: sha1 | sha256 | sha384 | sha512
        'mgf'    => 'sha256',     // MGF1 hash:   sha1 | sha256 | sha384 | sha512
    ],
];
```

SimpleSAMLphp ignores the unknown key, so it can sit alongside the rest of your IdP overrides. If you would rather keep
it separate, mount the same array on its own at `/var/www/key-transport.php`.

The default is `stock`, so without this the image behaves exactly as it always has.

With `mode => 'rsa-oaep'` the `<xenc:EncryptedKey>` is emitted as:

```xml
<xenc:EncryptionMethod Algorithm="http://www.w3.org/2009/xmlenc11#rsa-oaep">
  <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
  <xenc11:MGF Algorithm="http://www.w3.org/2009/xmlenc11#mgf1sha256"/>
</xenc:EncryptionMethod>
```

Useful combinations when testing an SP:

| Setting | What it tells you |
|---|---|
| `mode => 'stock'` | The unchanged path. Always worth re-running as a regression check. |
| `mode => 'rsa-oaep'`, sha256/sha256 | The configuration hardened identity providers use. |
| `mode => 'rsa-oaep'`, sha1/sha1 | Control: isolates the digest from the algorithm URI, since only the URI changes from stock. |
| `mode => 'rsa-oaep'`, sha256 digest, sha1 mgf | The two hashes are declared separately and need not match. |

An SP that cannot handle a given combination usually reports only a generic decryption failure, because the algorithms
are negotiated in the ciphertext rather than the handshake. Expect to read the SP's own logs rather than anything the
IdP reports, and be wary of matching on a specific OpenSSL error code - OpenSSL's error queue is global, so a stale
entry is often what surfaces.

## Developing This Image

* Fork this repo, create a new branch and make the change.
* Test using the built-in docker image with docker-compose, you can run `docker-compose up --build dev` to run the dev version with the config/metadata/modules folders volumed in (
  real time changes).
* Once everything is all good, test with the prod version `docker-compose up --build prod`.
* If it is all good, submit a pull request for the change.

## Updating SimpleSAMLphp library

* Fork this repo, create a new branch
* Edit the Dockerfile and change the `SAML_VERSION` build argument to the new version you want to include
* Check any upgrade notes about things that must change, specifically look for changes that impact modules, hooks or the idp-hosted or idp-remote files.
* Refresh `patches/key-transport.patch` if the build fails applying it. It is applied with `--fuzz=0` on purpose, so a moved context line breaks the build instead of silently dropping the key transport hook.
* Test using the built-in docker image with docker-compose, you can run `docker-compose up --build dev` to run the dev version with the config/metadata/modules folders volumed in (
  real time changes).
* Once everything is all good, test with the prod version `docker-compose up --build prod`.
* If it is all good, submit a pull request for the change.
