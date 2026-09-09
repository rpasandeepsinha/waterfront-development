# Sitebuilder Documentation

This section explains how a consumer can use the Sitebuilder provision request classes to manage website builder deployments through the `ProvisionGateway`.

## Overview

The Sitebuilder provision type manages BaseKit website builder deployments. It supports creating sites, generating SSO links, updating packages, adding SSL certificates, and terminating sites/contexts.

Provider: **BaseKit** -- `ProvisionProvider::BASEKIT`

## Context UUIDs

Sitebuilder uses [Context UUIDs](../Requests/ContextUuids.md) to manage user-site relationships. See [Sitebuilder Context UUID](Requests/ContextUuid.md) for important details about how each site must provide its own context UUID to allow different package configurations per site.

## Available Requests

### CreateSitebuilderRequest

Used to create a new Sitebuilder website. See [detailed docs](Requests/CreateSitebuilderRequest.md) for package and contract period details.

| Parameter      | Required | Description                                          |
|----------------|----------|------------------------------------------------------|
| domain         | Yes      | Domain name for the website.                         |
| packages       | Yes      | Array of integers (BaseKit package IDs).             |
| firstname      | Yes      | Customer first name.                                 |
| lastname       | Yes      | Customer last name.                                  |
| email          | Yes      | Customer email address.                              |
| contractPeriod | Yes      | Contract period for all packages.                    |
| context        | Yes      | Context UUID (should be unique per site, e.g., parent subscription UUID). |

**Validation:** Only one sitebuilder can be linked to a tag.

Example usage:

```php
$request = new CreateSitebuilderRequest(
    domain: 'example.com',
    packages: [101, 102],
    firstname: 'John',
    lastname: 'Doe',
    email: 'john@example.com',
    contractPeriod: 12,
    context: $contextUuid,
);

$result = $this->provisionGateway->request($request);
```

### UpdateSitebuilderRequest

Used to update packages for an existing Sitebuilder site. See [detailed docs](Requests/UpdateSitebuilderRequest.md) for package diff behavior.

| Parameter      | Required | Description                                                   |
|----------------|----------|---------------------------------------------------------------|
| tagUuid        | Yes      | Tag identifying the deployment (e.g., subscription UUID).     |
| context        | Yes      | Context UUID for the deployment.                              |
| packages       | Yes      | Array of integers -- the desired end state of enabled packages. |
| contractPeriod | Yes      | Contract period for all packages.                             |

The system diffs the provided packages against currently enabled packages: disabling removed packages and enabling new ones.

Example usage:

```php
$request = new UpdateSitebuilderRequest(
    tagUuid: $subscriptionUuid,
    context: $contextUuid,
    packages: [101, 103], // 102 removed, 103 added
    contractPeriod: 12,
);

$result = $this->provisionGateway->request($request);
```

### GetSitebuilderSsoRequest

Used to generate a Single Sign-On URL for a Sitebuilder site. See [detailed docs](Requests/CreateSitebuilderSsoRequest.md).

| Parameter | Required | Description                                                |
|-----------|----------|------------------------------------------------------------|
| context   | Yes      | Context UUID for the deployment.                           |
| tagUuid   | Yes      | Tag identifying the deployment (e.g., subscription UUID).  |

Returns `SitebuilderSsoResult` containing a `ssoUrl`. The customer should be redirected to this URL to access BaseKit. The SSO URL is masked in stored results.

Example usage:

```php
$request = new GetSitebuilderSsoRequest(
    context: $contextUuid,
    tagUuid: $subscriptionUuid,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    $ssoUrl = $result->ssoUrl;
}
```

### AddSslSitebuilderRequest

Used to add an SSL certificate to a Sitebuilder site.

| Parameter       | Required | Description                                              |
|-----------------|----------|----------------------------------------------------------|
| tagUuid         | Yes      | Tag identifying the deployment.                          |
| context         | Yes      | Context UUID for the deployment.                         |
| privateKey      | Yes      | The SSL private key. Masked in storage (`#[SensitiveParameter]`). |
| mainCertificate | Yes      | The main SSL certificate.                                |

Example usage:

```php
$request = new AddSslSitebuilderRequest(
    tagUuid: $subscriptionUuid,
    context: $contextUuid,
    privateKey: $privateKeyPem,
    mainCertificate: $certificatePem,
);

$result = $this->provisionGateway->request($request);
```

### TerminateSitebuilderRequest

Used to terminate a single Sitebuilder site.

| Parameter | Required | Description                                              |
|-----------|----------|----------------------------------------------------------|
| context   | Yes      | Context UUID for the deployment.                         |
| tagUuid   | Yes      | Tag identifying the deployment to terminate.             |

Example usage:

```php
$request = new TerminateSitebuilderRequest(
    context: $contextUuid,
    tagUuid: $subscriptionUuid,
);

$result = $this->provisionGateway->request($request);
```

### TerminateSitebuilderContextRequest

Used to terminate an entire Sitebuilder context (user), including all associated sites.

| Parameter | Required | Description                              |
|-----------|----------|------------------------------------------|
| context   | Yes      | Context UUID to terminate.               |

Example usage:

```php
$request = new TerminateSitebuilderContextRequest(
    context: $contextUuid,
);

$result = $this->provisionGateway->request($request);
```

## Internal / Migration Requests

The following requests are used for internal operations and migrations:

- **GetBasekitSiteByRefRequest** -- Retrieve a BaseKit site by its reference ID. Returns `BasekitSiteResult` with `siteRef` and `domain`.
- **GetBasekitUserByRefRequest** -- Retrieve a BaseKit user by their reference ID. Returns `BasekitUserResult` with `userId` and `email`.
- **CreateBasekitDeploymentsFromMigrationRequest** -- Create deployment records from BaseKit migration data.
- **RollbackBasekitDeploymentsFromMigrationRequest** -- Rollback deployment records created during migration.

## Deployment Model

Sitebuilder deployments are stored with:
- `SitebuilderDeployment` -- Parent model (extends `ProvisionDeployment`)
  - Links to `BasekitSitebuilderDeployment` (stores `site_ref`, `uuid`)
  - Links to `BasekitContext` (stores `context_uuid`, `user_ref`)

The `BasekitContext` model maps a context UUID to a BaseKit user reference, enabling multiple sites under different contexts for the same customer.
