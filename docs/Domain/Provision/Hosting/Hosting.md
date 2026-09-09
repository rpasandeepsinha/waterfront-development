# Hosting Documentation

This section explains how a consumer can use the hosting provision request classes to manage web hosting through the `ProvisionGateway`.

## Overview

The Hosting provision type supports creating hosting accounts and generating SSO (Single Sign-On) links. Hosting is provisioned through provider-specific services, with the default provider being **Plesk**.

Currently supported providers:
- **Plesk** (default) -- `ProvisionProvider::PLESK`
- **DirectAdmin** -- `ProvisionProvider::DIRECTADMIN`

Each provider has its own validator with provider-specific required fields.

## Available Requests

### HostingCreateRequest

Used to create a new hosting account. Some parameters are provider-specific.

| Parameter        | Required | Provider     | Description                                      |
|------------------|----------|--------------|--------------------------------------------------|
| servicePlan      | Yes      | Both         | Service plan in Plesk or package name in DirectAdmin. |
| email            | Yes      | Both         | Customer email address.                          |
| context          | Yes      | Both         | Context UUID for the deployment.                 |
| domain           | No       | Both         | Domain name. Will be generated if null.          |
| username         | No       | Both         | Username. Will be generated if null.             |
| password         | No       | Both         | Password. Will be generated if null.             |
| contactName      | No       | Plesk        | Contact name for the Plesk customer.             |
| enableDns        | No       | DirectAdmin  | Enable DNS for the account. Default: `false`.    |
| enableFtp        | No       | DirectAdmin  | Enable FTP for the account. Default: `false`.    |
| enableSsh        | No       | DirectAdmin  | Enable SSH for the account. Default: `false`.    |
| enableSsl        | No       | DirectAdmin  | Enable SSL for the account. Default: `false`.    |
| installWordpress | No       | Plesk        | Install WordPress. Default: `false`.             |
| ipv4             | No       | Plesk        | IPv4 address for the hosting account.            |

**Plesk validation rules:** `servicePlan` (required), `email` (required, email), `contactName` (required), `ipv4` (required, valid IPv4).

**DirectAdmin validation rules:** `servicePlan` (required), `email` (required, email).

Example usage:

```php
$request = new HostingCreateRequest(
    servicePlan: 'default-plan',
    email: 'customer@example.com',
    context: $contextUuid,
    domain: 'example.com',
    contactName: 'John Doe', // Plesk only
    ipv4: '192.168.1.1',     // Plesk only
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    // Hosting account created
}
```

### HostingSsoRequest

Used to generate a Single Sign-On URL for a hosting account.

| Parameter      | Required | Provider | Description                                         |
|----------------|----------|----------|-----------------------------------------------------|
| username       | Yes      | Both     | Provider username to generate SSO for.              |
| context        | Yes      | Both     | Context UUID for the deployment.                    |
| ipAddress      | No       | Plesk    | IP address of the hosting account.                  |
| redirectToMail | No       | Plesk    | Redirect to mail interface after login. Default: `false`. |

Example usage:

```php
$request = new HostingSsoRequest(
    username: 'hosting-user',
    context: $contextUuid,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    // SSO link generated successfully
}
```

## Deployment Model

Hosting deployments are stored with a parent `HostingDeployment` model that links to:
- A `Server` record
- An optional `PleskHostingDeployment` child (stores `customer_name`, `subscription_domain`)
- An optional `DirectAdminHostingDeployment` child (stores `username`, `default_domain`)
