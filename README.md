# Test SAML IDP Docker

This is a wrapper around SimpleSAMLphp which provides a test SAML Identity Provider to use in Totara instances. It is
for testing the SAML2 connections only and should not be used in any production site.

Currently embedded is SimpleSAMLphp version **1.19.7**.

## Configuration

There are two environmental variables that need to be set in the Docker image for this to work.

| Variable      | Description                                                                                                                                             |
|---------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `SP_URLS`  | The URLs of the Service Provider instances to register. Eg: `http://my-instance.local/path/to/metadata.php,http://another-site/path/to/metadata.php`. Accept multiple if seperated by a comma. **Must be the full URL to the metadata**. |
| `LISTEN_PORT` | The port used to access the service. Defaults to `8089`.                                                                                                |
| `SITE_TITLE`  | Override the default site title, used when running multiple to tell them apart.                                                                         |

## Getting Started

```shell
# Download the docker image
docker pull totara/simple-saml-test:latest

# Start the service
docker run --rm -p 8089:8089 -e LISTEN_PORT=8089 -e SP_URLS=http://{YOUR_SP_INSTANCE}/path/to/metadata.php -it totara/simple-saml-test:latest
```

Set `{YOUR_SP_INSTANCE}` to the domain of your service provider instance (typically a Totara instance but technically could work with any SAML site).

If your domain isn't publicly resolvable (such as it's a test environment) you will need to teach the
Domain/IP to this docker image.

```shell
# Instance is running directly on the host machine
docker run --add-host={YOUR_SP_INSTANCE}:host-gateway ... -it totara/simple-saml-test:latest

# Instance is somewhere else, replace the domain & IP
docker run --add-host={YOUR_SP_INSTANCE}:{IP_OF_SITE} ... -it totara/simple-saml-test:latest
```

Once started, you can access the service via `http://localhost:{LISTEN_PORT}` (defaults to 8089).

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
      - SP_URLS=${SAML_TOTARA_URLS}
      - LISTEN_PORT=8089
      - SITE_TITLE="Testing"
```

Edit your `.env` file and add the following line.

```dotenv
SAML_TOTARA_URLS=http://totara74/path/to/sp/metadata.php
```

In it place the name of the Totara instance you're using to connect.

Make sure you add `saml2` to your local hosts file, so it resolves in your browser.

Start the docker service using `t up saml2`.

Try and access http://saml2:8089 and confirm you see the test environment.

*Important*: The URL that Totara and the URL that you access the site on via your browser must be the same.

The path to the metadata file depends on what SAML plugin you are using which is why it's not specified here.

## Developing This Image

* Fork this repo, create a new branch and make the change.
* Test using the built-in docker image with docker-compose, you can run `docker-compose up --build dev` to run the dev version with the config/metadata/modules folders volumed in (real time changes).
* Once everything is all good, test with the prod version `docker-compose up --build prod`.
* If it is all good, submit a pull request for the change.
