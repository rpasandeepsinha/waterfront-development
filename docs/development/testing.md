# Testing

## Checking code coverage

<details>

<strong> Run the following command to enable phpdbg based code coverage reports. </strong>

```
$ phpdbg -qrr vendor/bin/phpunit --stop-on-failure --coverage-html=coverage
```
</details>

## Testing

### Fakers

For testing purposes, you can use internal fakers. To enable these, set the following values in your environment:

<details>
<summary><strong>Show config</strong></summary>

```dotenv
APP_FAKE_DOMAIN_CLIENT=true
APP_FAKE_HOSTING_CLIENT=true
APP_FAKE_POWERDNS_CLIENT=true
APP_FAKE_PAYMENT_CLIENT=true
APP_FAKE_SPAM_FILTER_CLIENT=true
APP_FAKE_CLOUD_SERVICE=true
APP_FAKE_DIRECTADMIN_CLIENT=true
```

</details>

### Testing with OpenApi
Run the following docker command from the base project dir:
```
docker run -p 8080:8080 -e SWAGGER_JSON=/opt/docs/api/<component>.json -v "${PWD}/docs:/opt/docs"  swaggerapi/swagger-ui
```

You need to replace `<component>` with one of the file names in `docs/api`. (compass/ferry/partner/etc.)

The OpenApi page is then available on: http://localhost:8080/

### Mocking or replacing services in the service container

Replacing services in integration tests with a mock or a different service should be done before instantiating services.
Note that Laravel mocks services in the container with methods like `Queue::fake();` or `Event::fake()`. Example:

```php
    Event::fake([EventFiredFromService::class]);

    $service = self::resolve(Service::class);
    $service->run();
```
