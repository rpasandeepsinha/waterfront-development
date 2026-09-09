# Microsoft 365 Documentation

This section explains how a consumer can use the Microsoft 365 provision request classes to manage M365 tenants and domains through the `ProvisionGateway`.

## Overview

The Microsoft 365 provision type manages tenant operations and domain lifecycle within Microsoft 365. It uses two providers:

- **Microsoft Online** (`ProvisionProvider::MICROSOFT_ONLINE`) -- Used for tenant ID retrieval and authorization URL generation. Requires validation.
- **Microsoft Graph** (`ProvisionProvider::MICROSOFT_GRAPH`) -- Used for all domain CRUD operations. Does not require validation.

The default provider is `MICROSOFT_ONLINE`.

## Context UUIDs

Microsoft 365 provisioning relies heavily on [Context UUIDs](../Requests/ContextUuids.md). The first step is typically creating a tenant (e.g., `yourhosting.onmicrosoft.com`). All subsequent domain operations reference the same context UUID -- which **is** the Microsoft 365 tenant UUID -- to associate with that tenant.

> Domain operation requests no longer accept a separate `tenantId` parameter. The `context` UUID *is* the tenant UUID and is used as such when authenticating against Microsoft Graph.

## Retry Traceability (`tagUuid`)

All domain operation requests require a `tagUuid` -- the subscription UUID -- which is exposed on the request as the `tag` and used by the retry logic to correlate retried requests with the original subscription.

## Available Requests

### Microsoft365TenantIdRequest

Used to retrieve the tenant ID for a given tenant name. This is typically one of the first operations in the M365 provisioning flow.

| Parameter  | Required | Description                                    |
|------------|----------|------------------------------------------------|
| tenantName | Yes      | Name of the tenant to get the ID for.          |
| context    | Yes      | Context UUID for the deployment.               |

**Provider:** Microsoft Online (default). **Requires validation:** Yes (validates `tenantName`).

Returns `TenantIdResult` containing the `tenantId` string.

Example usage:

```php
$request = new Microsoft365TenantIdRequest(
    tenantName: 'yourhosting',
    context: $contextUuid,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    $tenantId = $result->tenantId;
}
```

### Microsoft365AuthorizationUrlRequest

Used to get an authorization URL for a tenant. The tenant must already exist on microsoft.com.

| Parameter  | Required | Description                                                 |
|------------|----------|-------------------------------------------------------------|
| tenantName | Yes      | Name of the tenant to get the authorization URL from.       |
| context    | Yes      | Context UUID for the deployment.                            |

**Provider:** Microsoft Online (default). **Requires validation:** Yes (validates `tenantName`).

Returns `TenantAuthorizationUrlResult` containing the `authorizationUrl` string.

Example usage:

```php
$request = new Microsoft365AuthorizationUrlRequest(
    tenantName: 'yourhosting',
    context: $contextUuid,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    $authUrl = $result->authorizationUrl;
    // Redirect user to $authUrl for authorization
}
```

### Microsoft365CreateDomainRequest

Used to create a domain under a Microsoft 365 tenant.

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name to create.                                 |
| context    | Yes      | The Microsoft 365 tenant UUID the domain should be created under.          |
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `CreateDomainResult` containing a `Domain` object.

Example usage:

```php
$request = new Microsoft365CreateDomainRequest(
    domainName: 'example.com',
    context: $tenantUuid,
    tagUuid: $subscriptionUuid,
);

$result = $this->provisionGateway->request($request);
```

### Microsoft365VerifyDomainRequest

Used to verify a domain under a Microsoft 365 tenant. The domain must have been created first, and the required DNS verification records must be in place.

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name to verify.                                 |
| context    | Yes      | The Microsoft 365 tenant UUID the domain is verified within.               |
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `VerifyDomainResult` containing a `Domain` object.

### Microsoft365GetDomainRequest

Used to retrieve details of a domain from a Microsoft 365 tenant.

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name to retrieve.                               |
| context    | Yes      | The Microsoft 365 tenant UUID the domain belongs to.                       |
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `GetDomainResult` containing a `Domain` object.

### Microsoft365DeleteDomainRequest

Used to delete a domain from a Microsoft 365 tenant.

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name to delete.                                 |
| context    | Yes      | The Microsoft 365 tenant UUID the domain should be removed from.           |
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `DeleteDomainResult` containing a `bool $isDeleted`.

### Microsoft365PromoteDomainRequest

Used to promote a domain within a Microsoft 365 tenant.

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name to promote.                                |
| context    | Yes      | The Microsoft 365 tenant UUID the domain is promoted within.               |
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `PromoteDomainResult` containing a `bool $isPromoted`.

### Microsoft365SetDomainAsDefaultDomainRequest

Used to set a domain as the default domain for a Microsoft 365 tenant.

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name to set as default.                         |
| context    | Yes      | The Microsoft 365 tenant UUID the default domain is being set within.      |
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `SetDomainAsDefaultDomainResult` containing a `bool $isDefault`.

### Microsoft365GetVerificationDnsRecordsRequest

Used to retrieve the DNS records required for domain verification.

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name.                                           |
| context    | Yes      | The Microsoft 365 tenant UUID to retrieve the verification DNS record from.|
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `VerificationDnsRecordsResult` containing an array of `DomainDnsRecord` objects.

### Microsoft365GetServiceDnsRecordsRequest

Used to retrieve the DNS records required for M365 services (Exchange, Teams, etc.).

| Parameter  | Required | Description                                                                |
|------------|----------|----------------------------------------------------------------------------|
| domainName | Yes      | The fully qualified domain name.                                           |
| context    | Yes      | The Microsoft 365 tenant UUID to retrieve the DNS records from.            |
| tagUuid    | Yes      | Subscription UUID used as trace tag for retries.                           |

**Provider:** Microsoft Graph. **Requires validation:** No.

Returns `ServiceDnsRecordsResult` containing an array of `DomainDnsRecord` objects.

## Typical Provisioning Flow

A typical Microsoft 365 domain provisioning flow follows these steps:

1. **Get Tenant ID** -- `Microsoft365TenantIdRequest` to retrieve the tenant ID from the tenant name
2. **Get Authorization URL** -- `Microsoft365AuthorizationUrlRequest` to authorize access to the tenant
3. **Create Domain** -- `Microsoft365CreateDomainRequest` to register the domain with the tenant
4. **Get Verification DNS Records** -- `Microsoft365GetVerificationDnsRecordsRequest` to get required DNS records
5. **Verify Domain** -- `Microsoft365VerifyDomainRequest` after DNS records are in place
6. **Get Service DNS Records** -- `Microsoft365GetServiceDnsRecordsRequest` to configure M365 services
7. **Promote Domain** -- `Microsoft365PromoteDomainRequest` (optional)
8. **Set as Default** -- `Microsoft365SetDomainAsDefaultDomainRequest` (optional)

## Architecture Diagrams

- [Provision request with context UUID](../Architecture/provision-request-with-context-uuid.plantuml) -- Flow when context UUID already exists
- [Provision request without context UUID](../Architecture/provision-request-without-context-uuid.plantuml) -- Flow when context UUID is new
