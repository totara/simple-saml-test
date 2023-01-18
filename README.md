# Test SAML IDP Docker

This is a wrapper around SimpleSAMLphp which provides a test SAML Identity Provider to use in Totara instances. It is
for testing the SAML2 connections only and should not be used in any production site.

Currently embedded is SimpleSAMLphp version **1.19.7**.

## Configuration

There are two environmental variables that need to be set in the Docker image for this to work.

| Variable      | Description                                                                                                                                             |
|---------------|---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `TOTARA_URL`  | The URLs of the Totara instance to mark as trusted. Eg: `http://my-totara-instance.local,http://another-site`. Accept multiple if seperated by a comma. |
| `LISTEN_PORT` | The port used to access the service. Defaults to `8089`.                                                                                                |
| `SITE_TITLE`  | Override the default site title, used when running multiple to tell them apart.                                                                         |

## Getting Started

```shell
# Download the docker image
docker pull totara/simple-saml-test:latest

# Start the service
docker run --rm -p 8089:8089 -e LISTEN_PORT=8089 -e TOTARA_URL={YOUR_TOTARA_URL} -it totara/simple-saml-test:latest
```

Set `{YOUR_TOTARA_URL}` to the domain of your Totara instance.

If your Totara instance domain isn't publicly resolvable (such as it's a test environment) you will need to teach the
Domain/IP to this docker image.

```shell
# Totara instance is running directly on the host machine
docker run --add-host={YOUR_TOTARA_URL}:host-gateway ... -it totara/simple-saml-test:latest

# Totara instance is somewhere else, replace the domain & IP
docker run --add-host={YOUR_TOTARA_URL}:{IP_OF_SITE} ... -it totara/simple-saml-test:latest
```

Once started, you can access the service via `http://localhost:{LISTEN_PORT}`.

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
      - TOTARA_URL=http://${SAML_TOTARA_URL:-totara74}
      - LISTEN_PORT=8089
      - SITE_TITLE="Testing"
```

Edit your `.env` file and add the following line.

```dotenv
SAML_TOTARA_URL=totara74
```

In it place the name of the Totara instance you're using to connect.

Make sure you add `saml2` to your local hosts file, so it resolves in your browser.

Start the docker service using `t up saml2`.

Try and access http://saml2:8089 and confirm you see the test environment.

*Important*: The URL that Totara and the URL that you access the site on via your browser must be the same.

