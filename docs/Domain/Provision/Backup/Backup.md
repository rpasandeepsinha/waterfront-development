# Backup Documentation

This section explains how a consumer can use the backup provision request classes to manage backup accounts through the `ProvisionGateway`.

## Overview

The Backup provision type supports creating backup accounts, managing SSO access, updating quotas, suspending/enabling accounts, retrieving usage data, and terminating accounts. The default (and currently only) provider is **Acronis**.

Provider: **Acronis** -- `ProvisionProvider::ACRONIS`

## Available Requests

### CreateBackupRequest

Used to create a new backup account. Creates an Acronis tenant, user, sets quotas, and stores deployment records.

| Parameter                    | Required | Description                                                 |
|------------------------------|----------|-------------------------------------------------------------|
| tagUuid                      | Yes      | Unique tag for the deployment (e.g., subscription UUID). Must not already exist. |
| email                        | Yes      | Customer email address.                                     |
| firstname                    | Yes      | Customer first name.                                        |
| lastname                     | Yes      | Customer last name.                                         |
| cloudStorageInGb             | No       | Cloud storage quota in GB.                                  |
| localStorageInGb             | No       | Local storage quota in GB.                                  |
| username                     | No       | Acronis username. Generated if null.                        |
| password                     | No       | Acronis password (min 16 chars, mixed case, numbers). Generated if null. Masked in storage. |
| language                     | No       | Account language (`Language` enum). Default: `ENGLISH`.     |
| mobileDevices                | No       | Number of mobile devices quota.                             |
| workStations                 | No       | Number of workstations quota.                               |
| servers                      | No       | Number of servers quota.                                    |
| vms                          | No       | Number of virtual machines quota.                           |
| hostingServers               | No       | Number of hosting servers quota.                            |
| m365Seats                    | No       | Number of Microsoft 365 seats quota.                        |
| m365SharepointSites          | No       | Number of M365 SharePoint sites quota.                      |
| m365Teams                    | No       | Number of M365 Teams quota.                                 |
| googleWorkspaceSeats         | No       | Number of Google Workspace seats quota.                     |
| enableGoogleWorkspaceDrive   | No       | Enable Google Team Drive.                                   |
| websites                     | No       | Number of websites quota.                                   |

Returns `BackupCreateResult` which includes the generated `username` and `password`.

Example usage:

```php
$request = new CreateBackupRequest(
    tagUuid: $subscriptionUuid,
    email: 'customer@example.com',
    firstname: 'John',
    lastname: 'Doe',
    cloudStorageInGb: 100.0,
    servers: 5,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    $username = $result->username;
    $password = $result->password;
}
```

### UpdateBackupRequest

Used to update an existing backup account's password and/or offering item quotas. At least one field must be provided.

| Parameter                    | Required | Description                                                 |
|------------------------------|----------|-------------------------------------------------------------|
| tagUuid                      | Yes      | Tag identifying the backup deployment.                      |
| password                     | No       | New password (min 16 chars, mixed case, numbers). Masked in storage. |
| cloudStorageInGb             | No       | Updated cloud storage quota in GB.                          |
| localStorageInGb             | No       | Updated local storage quota in GB.                          |
| mobileDevices                | No       | Updated mobile devices quota.                               |
| workStations                 | No       | Updated workstations quota.                                 |
| vms                          | No       | Updated virtual machines quota.                             |
| servers                      | No       | Updated servers quota.                                      |
| hostingServers               | No       | Updated hosting servers quota.                              |
| m365Seats                    | No       | Updated M365 seats quota.                                   |
| m365SharepointSites          | No       | Updated M365 SharePoint sites quota.                        |
| m365Teams                    | No       | Updated M365 Teams quota.                                   |
| googleWorkspaceSeats         | No       | Updated Google Workspace seats quota.                       |
| enableGoogleWorkspaceDrive   | No       | Updated Google Team Drive setting.                          |
| websites                     | No       | Updated websites quota.                                     |

Returns `BackupUpdateResult` which includes the updated `offeringItems`.

Example usage:

```php
$request = new UpdateBackupRequest(
    tagUuid: $subscriptionUuid,
    cloudStorageInGb: 200.0,
    servers: 10,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    // Quotas updated successfully
}
```

### GetBackupSsoRequest

Used to generate a Single Sign-On URL for a backup account.

| Parameter | Required | Description                                     |
|-----------|----------|-------------------------------------------------|
| tagUuid   | Yes      | Tag identifying the backup deployment. Must have exactly one backup linked. |

Returns `BackupSsoResult` which includes the `ssoUrl`. The SSO URL is masked in stored results.

Example usage:

```php
$request = new GetBackupSsoRequest(
    tagUuid: $subscriptionUuid,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    $ssoUrl = $result->ssoUrl;
    // Redirect customer to $ssoUrl
}
```

### SetBackupSuspensionStateRequest

Used to enable or disable (suspend) a backup tenant.

| Parameter | Required | Description                                         |
|-----------|----------|-----------------------------------------------------|
| tagUuid   | Yes      | Tag identifying the backup deployment.              |
| enable    | No       | `true` to enable, `false` to suspend. Default: `false`. |

Example usage:

```php
// Suspend a backup account
$request = new SetBackupSuspensionStateRequest(
    tagUuid: $subscriptionUuid,
    enable: false,
);

$result = $this->provisionGateway->request($request);
```

### GetBackupUsageRequest

Used to retrieve backup storage usage statistics.

| Parameter | Required | Description                            |
|-----------|----------|----------------------------------------|
| tagUuid   | Yes      | Tag identifying the backup deployment. |

Returns `BackupUsagesResult` which includes `tenantUsages` from Acronis.

Example usage:

```php
$request = new GetBackupUsageRequest(
    tagUuid: $subscriptionUuid,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    $usages = $result->tenantUsages;
}
```

### TerminateBackupRequest

Used to terminate a backup account. This disables the Acronis tenant, deletes it, and removes local deployment records.

| Parameter | Required | Description                            |
|-----------|----------|----------------------------------------|
| tagUuid   | Yes      | Tag identifying the backup deployment. |

Example usage:

```php
$request = new TerminateBackupRequest(
    tagUuid: $subscriptionUuid,
);

$result = $this->provisionGateway->request($request);

if ($result->provisionStatus === ProvisionStatus::SUCCESS) {
    // Backup account terminated
}
```

## Offering Items

Acronis uses "offering items" to configure resource quotas for a tenant. The following offering items are supported:

| Offering Item              | Acronis Property Name                | Unit       |
|----------------------------|--------------------------------------|------------|
| Cloud Storage              | `pg_base_storage`                    | Bytes (input in GB) |
| Local Storage              | `local_storage`                      | Bytes (input in GB) |
| Virtual Machines           | `pg_base_vms`                        | Count      |
| Servers                    | `pg_base_servers`                    | Count      |
| Workstations               | `pg_base_workstations`               | Count      |
| Mobile Devices             | `pg_base_mobiles`                    | Count      |
| Hosting Servers            | `pg_base_web_hosting_servers`        | Count      |
| M365 Seats                 | `pg_base_m365_seats`                 | Count      |
| M365 SharePoint Sites      | `pg_base_m365_sharepoint_sites`      | Count      |
| M365 Teams                 | `pg_base_m365_teams`                 | Count      |
| Google Workspace Seats     | `pg_base_gworkspace_seats`           | Count      |
| Google Team Drive          | `pg_base_google_team_drive`          | Count      |
| Websites                   | `pg_base_websites`                   | Count      |

When M365 seats are enabled, additional offering items for mailboxes and OneDrive are automatically added. Similarly, enabling Google Workspace seats adds Gmail and Drive offering items.

## Deployment Model

Backup deployments are stored with a parent `BackupDeployment` model that links to an `AcronisBackupDeployment` child containing:
- `tenant_uuid` -- The Acronis tenant UUID
- `user_uuid` -- The Acronis user UUID
- `acronis_provider_id` -- Link to the `AcronisProvider` configuration

## Supported Languages

The `Language` enum supports 27 languages for Acronis accounts, including: English, Dutch, German, French, Spanish, Italian, Portuguese, Japanese, Korean, Chinese, and more.
