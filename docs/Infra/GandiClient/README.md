# Gandi Client technical documentation

The [Gandi Client](../../../src/Infra/GandiClient/GandiClient.php) is responsible for communicating with the [Gandi LiveDNS API](https://api.sandbox.gandi.net/docs/livedns/)

## Client Requirements
To use the API successfully we need at least two .env variables.
* `GANDI_URL` -> This is the url to the Gandi LiveDNS API
* `GANDI_TOKEN` -> This is the Gandi auth token, this can be created in the Gandi account, when needed

## Local Testing
The Gandi client and its requests and responses are fully covered by Mockoon, which is why we do not need a Faker for this client to successfully test all possible responses in the local development environment.
See the [basic Mockoon manual](https://yh-jira.atlassian.net/wiki/spaces/DEV/pages/1717403653/Mockoon+the+basics) to make any adjustments.
For the endpoint specific test options see the [Endpoint section](#endpoints).

## Endpoints

### Domain's records
GET `/v5/livedns/domains/{fqdn}/records`<br>
This endpoint is used to check whether the final AXFR to Gandi was executed successfully.

#### Testing
All responses that we can expect on this endpoint have been added as a mock to the [Mockoon](https://git.sandwave.io/sandwave/mock) repo. Below you will find an overview of possible statuses and the associated strings with which a response can be triggered.
- **Default :** 200 OK
  Because this is the default, this response responds to all domain names unless one of the specific strings is included.
- 404 Not Found<br>
  When it needs to be simulated that a domain has not arrived at Gandi<br>
  Trigger : use the string `not-found` in the domain name Example: test-not-found.nl
- 403 Forbidden<br>
  When it needs to be simulated that there a lack off permissions<br>
  Trigger : use the string `forbidden` in the domain name Example: test-forbidden.nl
- 401 unauthorized<br>
  When it is necessary to simulate that authentication fails, for example an incorrect token
  Trigger : use the string `unauthorized` in the domain name Example: test-unauthorized.nl


