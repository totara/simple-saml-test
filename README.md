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

```shell
# Download the docker image
docker pull totara/simple-saml-test:latest

# Start the service
docker run --rm -p 8089:8089 -e LISTEN_PORT=8089 -it totara/simple-saml-test:latest
```

Once started, you can access the service via `http://localhost:{LISTEN_PORT}` (defaults to 8089).

Open the site, login as admin, and then navigate to Manage Service Providers.
Add any SP instances on the page there, the URL must be the full URL to your metadata (it does not fully validate).

Eg: `http://{YOUR_SP_INSTANCE}/path/to/metadata.php`

We currently do not support raw XML dumps, the SAML image must be able to download the metadata file from your Service Provider directly.
You can teach docker the IP address of your service if it isn't resolvable.

```shell
# Instance is running directly on the host machine
docker run --add-host={YOUR_SP_INSTANCE}:host-gateway ... -it totara/simple-saml-test:latest

# Instance is somewhere else, replace the domain & IP
docker run --add-host={YOUR_SP_INSTANCE}:{IP_OF_SITE} ... -it totara/simple-saml-test:latest
```

### Using Totara Docker Dev

If you're using Totara Docker Dev library, you can add this image to the service directly.

Create a new file called `saml.yml` and add it to the `custom` directory in your Totara docker project.

Add the following contents:

```yaml
version: "3.7"

services:
  saml2:
    image: totara/simple-saml-test:latest
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

Create a new file called `custom_auth_sources.php` with the following structure:

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
```

The `username:password` section applies to the IdP, while the internal array is what will be posted back to the SP.
In the example above, the `my_user` user is known as `my_username` or `my_uid` to the service provider and will never see `my_user`.

Once created, include it as a volume, such as:
`docker run ... -v /path/to/custom_auth_sources.php:/var/www/custom_auth_sources.php ... -it totara/simple-saml-test:latest`

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
* Test using the built-in docker image with docker-compose, you can run `docker-compose up --build dev` to run the dev version with the config/metadata/modules folders volumed in (
  real time changes).
* Once everything is all good, test with the prod version `docker-compose up --build prod`.
* If it is all good, submit a pull request for the change.
