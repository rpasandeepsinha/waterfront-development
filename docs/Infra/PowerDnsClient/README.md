# PowerDnsClient

PowerDnsClient used in Waterfront to talk to PowerDNS for managing DNS zones and records.

## Additional info on records

### SOA record

See https://en.wikipedia.org/wiki/SOA_record

A SOA record has the following fields in the contents in this exact order:
- MNAME
- RNAME
- SERIAL
- REFRESH
- RETRY
- EXPIRE
- TTL

If a secondary nameserver observes an increase in the serial, it will try to update its zone.
PowerDNS itself updates the serial depending on the `soa-edit`/`soa-edit-api` metadata on the zone.
In our client we **also** try to update it manually just in case this doesn't work,
see `src/Domain/DNS/Entities/DnsZone.php` in the `updateSoa()` function. If we don't do this then CLDIN
might mistakenly delete the zone, due to it not being synced and considered a misconfigured zone.

If the serial is invalid like a negative number or a string (a serial should always start at 1), then
the `PowerDnsSoaSerialUpdater` will make it a correct serial. See examples in: `PowerDnsSoaSerialUpdaterTest`
