# DKIM documentation

## What is DKIM

DomainKeys Identified Mail (DKIM) is an email authentication method designed to detect forged sender addresses in email (spoofing).

DKIM allows the receiver to check that an email that claimed to have come from a specific domain was indeed authorized by the owner of that domain.

## DKIM state

Allows us to check if DKIM is enabled or disabled for a domain.

Location:

`src/Domain/Hosting/Services/HostingService::isDkimEnabled`

## Enable/Disable DKIM

This enables or disables DKIM for a specific domain.

When the `enableDkim` is called in the `HostingController` it also adds and removes the PowerDNS record.

Locations:

`src/Domain/Hosting/Services/HostingService::setDkim`

`src/Apps/API/Waterfront/Controllers/HostingController::enableDkim`

## Get DKIM record

Retrieve the type, host and value of an DKIM record for a specific domain.

Location:

`src/Domain/Hosting/Services/HostingService::getDkimRecord`

## Plesk

Plesk automatically adds a "o=-" TXT record while enabling DKIM.
This indicates that all emails of this domain should be signed with DKIM.
If an email of this domain is not signed with DKIM it should be rejected.
