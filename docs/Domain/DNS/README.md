# DNS technical documentation

This readme serves as a collection / index for the existing DNS documentation within this repo.

## DNS Logging
DNS history provides insight not only to the customer, but also to the support agent and can be instrumental in solving issues.
Every call to a 3rd party DNS client is logged. The logging makes use of the `DnsLogService`. To get the customer or user make use of the `AuthenticationManager`.

## Specs

To make use of the specs use the `ProductSpecName` at src/Domain/Products/Enums/ProductSpecName.php

* `ProductSpecName::DNS_CAN_EDIT_RECORDS` -> Indicates whether customers are allowed to edit DNS records.
* `ProductSpecName::DNS_CAN_COUPLE_HOSTING_OR_REDIRECT` -> Indicates whether hosting or redirect is permitted to be coupled to a domain with this DNS product.
* `ProductSpecName::PRODUCT_CANCEL_WITH_PARENT` -> Indicates whether this DNS product can only be cancelled by cancelling the parent, rather than cancelling the DNS product separately.


## Nameservers

We keep track of the nameserver types that are set for a domain in our DnsDeployment. These are saved on our side and are used to determine which process we should take when a domain is ordered or updated.

The nameserver types in the database do not reflect the actual nameservers that are set for a domain. These will always be retrieved live from the registrar and shown as such in Coast.

The `NameserverType` enum is used to determine the type of nameservers that are set for a domain.

| Nameserver Type | Description | Relation in `DnsDeployment` | Can manage DNS in Coast |
| --- | --- | --- | --- |
| External | Nameservers that are not managed by us. These are set by a customer in Coast and point to a 3rd party DNS provider. | `externalNameservers()` | ❌ |
| Internal | Nameservers that are managed by us, pointing to the PowerDNS server of CLDIN. These can be set by a customer in Coast by enabling the `Use provided nameservers` option. | `dnsNameservers()` | ✅ |
| Vanity | Nameservers that are managed by use, pointing to a 3rd party that provides Premium DNS (Such as Gandi). These can not be set by a client in Coast but are provisioned when ordering PremiumDNS. | `vanityNameservers()` | ✅ |
