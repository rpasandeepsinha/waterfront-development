# Multiple credentials for registrars

## Registrars
Our application uses the generic `DomainService` to interact with registrars. We currently support two registrars:
- Realtime Register (`RtrService`)
- Openprovider (`OpenProviderService`)

The file can be found here: `src/Domain/Domains/DomainService.php`

## Connecting business unit to register
In Nova you can add domain provider business units (`NovaDomainProviderBusinessUnitResource`)
you can then couple the business unit to the registrars (`NovaOpProviderCredentials` & `NovaRtrProviderCredentials`)
here you can add all the credentials that are needed. The credentials are encrypted stored in the database.

For example if a `DomainDeployment` has the `domain_business_unit_id` of RTR it will create a new client
using the RTR credentials that is coupled to it. This allows you to quickly switch out credentials for different subscriptions.

The file can be found here: `src/Domain/Domains/Factories/DomainServiceFactory.php`
