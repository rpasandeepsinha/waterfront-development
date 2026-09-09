# DirectAdminJsonClient Client technical documentation

The [DirectAdminJsonClient Client](../../../src/Infra/DirectAdminJsonClient/DirectAdminClient.php) is responsible for communicating with the new [DirectAdminJson API](https://docs.directadmin.com/developer/api/#api-documentation)

It will be a replacement for the existing DirectAdminClient, which is based on the old Legacy DirectAdmin API using `CMD_*` as commands.

## Client Requirements
The DirectAdminClient can be resolved by the IoC container, either by being injected in a `__construct` method or by using the Laravel Container directly.
To use the API we need to build a [DirectAdminServer](../../../src/Infra/DirectAdminJsonClient/DTO/D****irectAdminServer.php) object when sending request using the Connector.

**Example for getting an SSO url:**
```php
$server = new DirectAdminServer(
            baseUrl: 'https://da-server.nl',
            username: 'testuser',
            password: 'testpassword',
            port: 2222,
        );

$directAdminClient = $this->app->make(DirectAdminClient::class);

$sso = $directAdminClient->createLoginUrl($server);
```

## Sending requests as specific user
The DirectAdminJsonClient can send requests as a specific user by setting the `asUser` attribute in a `DirectAdminServer`. This will make sure that the requests are sent as the user instead of the admin.

**Example for getting an SSO url as a specific user:**
```php
$server = new DirectAdminServer(
            baseUrl: 'https://da-server.nl',
            username: 'testuser',
            password: 'testpassword',
            port: 2222,
        );

$directAdminClient = $this->app->make(DirectAdminClient::class);

$server->asUser = 'sub-user';

$sso = $directAdminClient->createLoginUrl($server);
```

## Local Testing
The DirectAdminJsonClient client and its requests and responses are fully covered by Mockoon, which is why we do not need a Faker for this client to successfully test all possible responses in the local development environment.
See the [basic Mockoon manual](https://yh-jira.atlassian.net/wiki/spaces/DEV/pages/1717403653/Mockoon+the+basics) to make any adjustments.
For the endpoint specific test options see the [Endpoint section](#endpoints).

