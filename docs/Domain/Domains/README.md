# Domains technical documentation

## Registrars
Our application uses the generic `DomainService` to interact with registrars. We currently support two registrars:
- Realtime Register (`RtrService`)
- Openprovider (`OpenProviderService`)

Each registar specific implementation should implement the `DomainDriverInterface`

We also have a placeholder driver for domain registration that is used during migrations. This is implemented by the `DomainPlaceHolderService`

## Domain registration & transfers

Using the `register` or `transfer` method a domain can be registered with a registrar. Generic domain data can be given for the the domain registration such as the domain name, the registrant, the nameservers, the (transfer) auth code and the option for DnsSec or PrivacyProtection.

### Minimal domain registration & transfers
Because we want to make sure that a customer always gets the domain registered, we have a minimal domain registration. This means that we only require the domain name and the registrant. The domain will be registered without the nameservers, or DnsSec.
This minimal register / transfer is called at the beginning of our order flow. Further down the order flow the DNS will be setup and the domain will be updated with the correct nameservers, or DnsSec.

The same logic is used for TLDs that require a nameserver. TLDs that require a nameserver can be found in the [metadata of the TLD](https://dm.realtimeregister.com/app/support/metadata#tab-general) provided by RTR.

See the [zone-check.plantuml](zone-check.plantuml) diagram for the domain registration flow.

# Product Specifications

| Name     | Type    | Description                                                                                       |
|----------|:--------|---------------------------------------------------------------------------------------------------|
| allow_whois_private | string  | If the domain allows a private whois to be setup. ('yes' or 'no')                                 |
| dnssec_enabled | boolean | If the domain supports Dnssec and if this should be enabled (1 or 0)                              |
| tld-has-zonecheck | boolean | If the domain has a [ZoneCheck](https://dm.realtimeregister.com/docs/api/tlds/metadata) (1 or 0). |
