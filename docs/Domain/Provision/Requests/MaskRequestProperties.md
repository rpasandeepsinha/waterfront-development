# Masking request properties in Provision Requests

The `SensitiveParameter` attribute can be used on properties in a `ProvisionRequestInterface` that should be masked when stored to the database by the `ProvisionTraceabilityService`.

The `ProvisionRequestNormalizer` checks for the attribute on any property and masks the value when normalizing to an array and thereby also extending to any encoding being done after that (xml, json, etc).

## How to use

Example usage looks like this:

```php
class CreateSomethingRequest implements ProvisionRequestInterface
{
    /**
     * @param string|null $username         Will be generated if null
     * @param string|null $password         Will be generated if null
     */
    public function __construct(
        public readonly ?string $username = null,
        #[SensitiveParameter]
        public readonly ?string $password = null,
    ) {
    }
}
```

This request, when send through the `ProvisionGateway`:

```php
$this->provisionGateway->request(
   new CreateSomethingRequest('user','password');
);
```

It will end up like this in the database:

```json
{
  "username" : "user",
  "password" : "****"
}
```

## Supported property types

Supported types for masking are:
- string
- int
- float

If a unsupported property is given the #[SensitiveParameter] attribute it will set the value to `null`
