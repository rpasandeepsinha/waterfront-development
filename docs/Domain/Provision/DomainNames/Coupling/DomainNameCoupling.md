# DomainName Coupling Documentation

This section explains how a consumer can use the `DomainNameCoupleProvisionRequest` classes to manage coupling
domain names to different services using the `ProvisionGateway`.

## Overview

The `DomainNameCoupleProvisionRequest` classes provide a way for consumers to couple domains to different provision
types. These requests are designed to be sent through the `ProvisionGateway`, which handles the actual coupling logic.

The provider for domain name coupling is `INTERNAL` -- no external service is involved.

**Allowed couple types:** Only `HOSTING` and `RESELLER_HOSTING` provision types are currently allowed for domain coupling. Attempting to couple to other types will result in a validation error.

## Available Requests

### DomainNameCoupleRequest

Used to couple a domain to an existing deployment. The consumer must provide the following data:

| Parameter   | Required | Description                                                                                      |
|-------------|----------|--------------------------------------------------------------------------------------------------|
| domain      | Yes      | The domain to couple.                                                                            |
| requestUuid | Yes      | UUID of the provisioning request that relates to the deployment to couple to.                    |
| context     | Yes      | Context UUID for the deployment.                                                                 |

**Validation rules:**
- `domain` must be a valid domain name
- `requestUuid` must reference an existing deployment
- The deployment's provision type must be `HOSTING` or `RESELLER_HOSTING`

Example usage:

```php
$request = new DomainNameCoupleRequest(
    domain: 'example.com',
    requestUuid: $provisioningRequestUuid,
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    // DomainName has been coupled to the given hosting deployment
}
```

### DomainNameDecoupleRequest

Used to decouple a domain from an existing deployment. The consumer must provide the following data:

| Parameter   | Required | Description                                                                                      |
|-------------|----------|--------------------------------------------------------------------------------------------------|
| domain      | Yes      | The domain to decouple.                                                                          |
| requestUuid | Yes      | UUID of the provisioning request that relates to the deployment to decouple from.                |
| context     | Yes      | Context UUID for the deployment.                                                                 |

Example usage:

```php
$request = new DomainNameDecoupleRequest(
    domain: 'example.com',
    requestUuid: $provisioningRequestUuid,
    context: $contextUuid,
);

$provisionResult = $this->provisionGateway->request($request);

if($provisionResult->provisionStatus === ProvisionStatus::SUCCESS) {
    // DomainName has been decoupled from the given hosting deployment
}
```

## Results

- `DomainNameCoupleResult` -- Contains the created `DomainNameCoupleDeployment` (nullable)
- `DomainNameDecoupleResult` -- Contains the removed `DomainNameCoupleDeployment`

## Deployment Model

The `DomainNameCoupleDeployment` model (extends `ProvisionDeployment`) stores:
- `domain` -- The coupled domain name
- `couple_type` -- The `ProvisionType` of the target deployment (e.g., `HOSTING`)
- `deployment_uuid` -- The UUID of the target deployment
