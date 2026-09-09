# Redirects Documentation

This section explains how a consumer can use the `RedirectProvisionRequest` classes to manage redirects through the
`ProvisionGateway`.

## Overview

The `RedirectProvisionRequest` classes provide a way for consumers to interact with the Provision domain to create,
update, delete, list, or retrieve redirects. These requests are dispatched through the `ProvisionGateway`, which
orchestrates the provisioning process and returns a result object.

The default provider for redirects is `Caddy`.

## Available Requests

### CreateRedirectRequest

Used to create a new redirect. The consumer must provide the following data:

| Parameter       | Required | Description                                                        |
|-----------------|----------|--------------------------------------------------------------------|
| domain          | Yes      | The primary domain for the provider to bind all redirects to.      |
| destinationUrl  | Yes      | The default URL the primary domain should redirect to.             |
| redirectType    | Yes      | The type of redirect (`RedirectType` enum: PERMANENT, TEMPORARY, FRAME). |
| context         | Yes      | Context UUID for the deployment.                                   |

Example usage:

```php
$request = new CreateRedirectRequest(
    domain: 'example.com',
    destinationUrl: 'https://destination.com',
    redirectType: RedirectType::FRAME,
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    // Redirect created successfully
}
```

### GetRedirectRequest

Used to get a specific redirect by domain name.

| Parameter  | Required | Description                          |
|------------|----------|--------------------------------------|
| domainName | Yes      | The domain name of the redirect.     |
| context    | Yes      | Context UUID for the deployment.     |

Example usage:

```php
$request = new GetRedirectRequest(
    domainName: 'example.com',
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    // Access redirect data from result
}
```

### ListRedirectsRequest

Used to list all redirects for a given context.

| Parameter | Required | Description                      |
|-----------|----------|----------------------------------|
| context   | Yes      | Context UUID for the deployment. |

Example usage:

```php
$request = new ListRedirectsRequest(
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    foreach ($provisionResult->redirects as $redirect) {
        echo "Redirect from {$redirect->source} to {$redirect->destination} ({$redirect->redirectType->value})\n";
    }
}
```

### UpdateRedirectRequest

Used to update an existing redirect's destination and/or type.

| Parameter      | Required | Description                                                              |
|----------------|----------|--------------------------------------------------------------------------|
| domain         | Yes      | The source domain of the redirect to update.                             |
| destinationUrl | Yes      | The new destination URL.                                                 |
| redirectType   | Yes      | The new redirect type (`RedirectType` enum: PERMANENT, TEMPORARY, FRAME). |
| context        | Yes      | Context UUID for the deployment.                                         |

Example usage:

```php
$request = new UpdateRedirectRequest(
    domain: 'example.com',
    destinationUrl: 'https://new-destination.com',
    redirectType: RedirectType::PERMANENT,
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    // Redirect updated successfully
}
```

### DeleteRedirectRequest

Used to remove a redirect by source domain.

| Parameter  | Required | Description                          |
|------------|----------|--------------------------------------|
| domainName | Yes      | The source domain of the redirect.   |
| context    | Yes      | Context UUID for the deployment.     |

Example usage:

```php
$request = new DeleteRedirectRequest(
    domainName: 'example.com',
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    // Redirect deleted successfully
}
```

### TerminateRedirectsRequest

Used to terminate all redirects for a given context. This is typically used when a subscription is cancelled.

| Parameter | Required | Description                      |
|-----------|----------|----------------------------------|
| context   | Yes      | Context UUID for the deployment. |

Example usage:

```php
$request = new TerminateRedirectsRequest(
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    // All redirects terminated successfully
}
```

## RedirectType Enum

| Case        | Value   | Description            |
|-------------|---------|------------------------|
| `PERMANENT` | `301`   | Permanent redirect     |
| `TEMPORARY` | `302`   | Temporary redirect     |
| `FRAME`     | `frame` | Frame-based redirect   |
