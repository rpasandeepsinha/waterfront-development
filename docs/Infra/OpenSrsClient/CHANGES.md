# OpenSRSClient – File-wise Change Summary

A file-wise summary of every file created or modified for the OpenSRSClient implementation.

Column 4 ("Code Added/Changed") is filled only for **existing** files whose change is 5 lines or fewer. For newly created files and for larger changes it is intentionally left empty.

## Newly created files

| SL. No. | File Name | What the File Does | Code Added/Changed in Existing Files |
|---|---|---|---|
| 1 | `src/Infra/OpenSrsClient/Config/connection.php` | Package config for the OpenSRS connection; reads `OPENSRS_API_URL`, `OPENSRS_USERNAME`, `OPENSRS_API_KEY` from the environment. | |
| 2 | `src/Infra/OpenSrsClient/Providers/OpenSrsClientProvider.php` | Deferrable service provider. Publishes/merges the package config and binds `OpenSrsConnectionInterface`, `OpenSrsClient` and `OpenSrsClientFactory` – swapping in the fakers when `APP_FAKE_DOMAIN_CLIENT` is set. | |
| 3 | `src/Infra/OpenSrsClient/Interfaces/OpenSrsConnectionInterface.php` | Contract for supplying connection details: API URL, reseller username (X-Username header) and API key (used to sign the request body). | |
| 4 | `src/Infra/OpenSrsClient/Messages/Connection.php` | Concrete `OpenSrsConnectionInterface` holding validated API URL, username and API key for a single account. | |
| 5 | `src/Infra/OpenSrsClient/Factories/OpenSrsClientFactory.php` | Builds an `OpenSrsClient`. With no credentials it returns the environment-bound client; with `OpenSrsProviderCredentials` it builds a fresh account-bound client so queue jobs never mutate a shared singleton. | |
| 6 | `src/Infra/OpenSrsClient/OpenSrsClient.php` | Main client. Exposes domain operations (check, retrieve, register, transfer, modify, auth code, nameserver update) and maps OPS requests/responses to Waterfront domain DTOs. | |
| 7 | `src/Infra/OpenSrsClient/Support/OpsXml.php` | Encoder/decoder for the OpenSRS OPS (XCP) protocol envelope (`OPS_envelope > body > data_block > dt_assoc`). Hardened against XXE and duplicate keys. | |
| 8 | `src/Infra/OpenSrsClient/Support/ContactSetBuilder.php` | Builds the OpenSRS `contact_set` (owner/admin/billing/tech) structure from a Waterfront customer array. | |
| 9 | `src/Infra/OpenSrsClient/Support/OwnerAccount.php` | Derives deterministic OpenSRS owner-account credentials (`reg_username` / `reg_password`) per customer, satisfying OpenSRS complexity rules. | |
| 10 | `src/Infra/OpenSrsClient/Traits/RequestDomainTrait.php` | Shared `setDomain()` / `getDomain()` for request messages, wrapping the raw string in a `Domain` DTO. | |
| 11 | `src/Infra/OpenSrsClient/Exceptions/OpenSrsResultException.php` | Runtime exception thrown when OpenSRS returns an error response, carrying the response text and code. | |
| 12 | `src/Infra/OpenSrsClient/Messages/BaseRequest.php` | Abstract request: builds the OPS XML, signs the body with a double-MD5 of the API key, sets timeouts and sends via Guzzle. | |
| 13 | `src/Infra/OpenSrsClient/Messages/BaseResponse.php` | Abstract response: parses the OPS envelope and exposes status code, response code/text, success flag and attributes. | |
| 14 | `src/Infra/OpenSrsClient/Messages/DomainLookupRequest.php` | Builds a `DOMAIN / LOOKUP` request for a single domain. | |
| 15 | `src/Infra/OpenSrsClient/Messages/DomainLookupResponse.php` | Maps a lookup response to a `CheckResult` (free / active / unknown), preserving the premium reason. | |
| 16 | `src/Infra/OpenSrsClient/Messages/DomainGetRequest.php` | Builds a `get` domain request with a selectable projection type (`all_info`, `domain_auth_info`, …). | |
| 17 | `src/Infra/OpenSrsClient/Messages/DomainGetResponse.php` | Maps a `get` response to a `RetrieveResult` (dates, auto-renew, lock, WHOIS privacy, nameservers, handles). | |
| 18 | `src/Infra/OpenSrsClient/Messages/DomainRegistrationRequest.php` | Builds an `sw_register` (`reg_type=new`) request, including contact set and owner account. | |
| 19 | `src/Infra/OpenSrsClient/Messages/DomainRegistrationResponse.php` | Maps a registration response to a `RegistrationResult` (active / requested / failed with reason). | |
| 20 | `src/Infra/OpenSrsClient/Messages/DomainTransferRequest.php` | Builds an `sw_register` (`reg_type=transfer`) request, optionally with the transfer auth info. | |
| 21 | `src/Infra/OpenSrsClient/Messages/DomainTransferResponse.php` | Maps a transfer response to a `TransferResult` (scheduled / failed with reason). | |
| 22 | `src/Infra/OpenSrsClient/Messages/DomainModifyRequest.php` | Builds a `modify` domain request for one modification type plus its companion `data` attributes. | |
| 23 | `src/Infra/OpenSrsClient/Messages/DomainModifyResponse.php` | Response wrapper for a `modify` domain call (success/failure via `BaseResponse`). | |
| 24 | `src/Infra/OpenSrsClient/Messages/DomainNameserverUpdateRequest.php` | Builds an `advanced_update_nameservers` (`op_type=assign`) request that replaces the delegation set. | |
| 25 | `src/Infra/OpenSrsClient/Fakers/ConnectionFaker.php` | `OpenSrsConnectionInterface` stub returning empty credentials for tests / fake mode. | |
| 26 | `src/Infra/OpenSrsClient/Fakers/OpenSrsClientFaker.php` | Extends `OpenSrsClient` and returns canned XML fixtures instead of making HTTP calls. | |
| 27 | `src/Domain/Domains/Services/OpenSrsService.php` | Domain driver (`DomainDriverInterface`) backed by OpenSRS. Delegates check/register/transfer/modify/nameserver/renewal operations to the client; contact-handle and DNSSEC operations report unsupported. | |
| 28 | `src/Domain/Domains/Models/OpenSrsProviderCredentials.php` | Eloquent model for the `opensrs_provider_credentials` table; `api_key` is an encrypted cast, with a `belongsTo` to `DomainProviderBusinessUnit`. | |
| 29 | `src/Apps/Nova/Domains/Resources/NovaOpenSrsProviderCredentials.php` | Nova resource for managing OpenSRS credentials (API URL, username, encrypted API key, business unit); delete is disabled. | |
| 30 | `database/migrations/2026_09_08_120000_create_opensrs_provider_credentials_table.php` | Creates the `opensrs_provider_credentials` table (business-unit FK, `api_url`, `username`, `text` `api_key` for ciphertext, timestamps). | |
| 31 | `tests/Factories/OpenSrsProviderCredentialsFactory.php` | Model factory generating fake `api_url`, `username` and `api_key` for `OpenSrsProviderCredentials`. | |
| 32 | `tests/Domain/Domains/Services/OpenSrsServiceTest.php` | Unit tests for `OpenSrsService`: delegation to the client, www rejection, failure wrapping, `fetchDomain` mapping, and unsupported operations. | |
| 33 | `tests/Infra/OpenSrsClient/OpenSrsClientTest.php` | Tests `OpenSrsClient` (via the faker) for check, retrieve, auth code and nameserver update. | |
| 34 | `tests/Infra/OpenSrsClient/DomainLookupTest.php` | Tests the lookup request XML and the available/taken/premium response mapping. | |
| 35 | `tests/Infra/OpenSrsClient/DomainGetTest.php` | Tests the `get` request XML, `all_info` → `RetrieveResult` mapping and auth-code extraction. | |
| 36 | `tests/Infra/OpenSrsClient/DomainRegistrationTest.php` | Tests the `sw_register` request XML and success/failure response mapping. | |
| 37 | `tests/Infra/OpenSrsClient/DomainTransferTest.php` | Tests the transfer request XML (with/without auth info) and response mapping. | |
| 38 | `tests/Infra/OpenSrsClient/DomainModifyTest.php` | Tests the modify and assign-nameserver request XML and the modify response. | |
| 39 | `tests/Infra/OpenSrsClient/Messages/BaseRequestSignatureTest.php` | Verifies the request body is signed with a double-MD5 of the API key. | |
| 40 | `tests/Infra/OpenSrsClient/Support/OpsXmlTest.php` | Tests OPS XML encode/decode, nested round-tripping, XXE protection and duplicate-key rejection. | |
| 41 | `tests/Infra/OpenSrsClient/data/` (9 fixtures: `customer.php` + 8 `opensrs_*.xml`) | Canned request/response fixtures used by the faker and the message tests. | |

## Existing files updated

| SL. No. | File Name | What the File Does | Code Added/Changed in Existing Files |
|---|---|---|---|
| 42 | `.env` | Local development environment variables. | Added `OPENSRS_API_URL="https://horizon.opensrs.net:55443"`, `OPENSRS_USERNAME="fake"`, `OPENSRS_API_KEY="fake"` |
| 43 | `.env.testing` | Environment variables for the test suite. | Added the same 3 keys: `OPENSRS_API_URL`, `OPENSRS_USERNAME`, `OPENSRS_API_KEY` |
| 44 | `config/app.php` | Registers application service providers. | Added `use Waterfront\Infra\OpenSrsClient\Providers\OpenSrsClientProvider;` and `OpenSrsClientProvider::class,` in the providers array |
| 45 | `src/Apps/Nova/NovaServiceProvider.php` | Registers Nova resources and the admin menu. | Added the `NovaOpenSrsProviderCredentials` import and `MenuItem::resource(NovaOpenSrsProviderCredentials::class),` in the domains menu group |
| 46 | `src/Domain/Providers/Enums/ProviderSlug.php` | Enum of domain/hosting/SSL provider slugs. | Added `case OPEN_SRS = 'opensrs';` |
| 47 | `src/Domain/Provision/Enums/ProvisionProvider.php` | Enum of provisioning providers used in logging context. | Added `case OPENSRS = 'opensrs';` |
| 48 | `src/Domain/Domains/Repositories/DomainDeploymentRepository.php` | Reads domain-provider credentials for a business unit. | Added `OpenSrsProviderCredentials` to the union return type of `getDomainProviderCredentials()` and the match arm `ProviderSlug::OPEN_SRS => OpenSrsProviderCredentials::query(),` (plus the import) |
| 49 | `src/Domain/Hosting/Factories/HostingServiceFactory.php` | Resolves the hosting driver for a provider slug. | Added `ProviderSlug::OPEN_SRS,` to the match arm of provider slugs that `throw new RuntimeException()` (not a hosting provider) |
| 50 | `src/Domain/Ssl/Factories/SslServiceFactory.php` | Resolves the SSL driver for a provider slug. | Added `ProviderSlug::OPEN_SRS,` to the match arm of provider slugs that `throw new RuntimeException()` (not an SSL provider) |
| 51 | `src/Domain/Domains/Factories/DomainServiceFactory.php` | Resolves the domain driver (service) for a provider slug and business unit. | |
| 52 | `database/seeds/Products/DomainSeeder.php` | Seeds domain providers and their credentials. | |
| 53 | `resources/lang/waterfront-backend.json` | Backend translation strings (used by Nova). | |
| 54 | `tests/Domain/Domains/Factories/DomainServiceFactoryTest.php` | Tests for `DomainServiceFactory`. | |

### Notes on the empty rows above

- **`DomainServiceFactory.php`** – added `OpenSrsService` and `OpenSrsClientFactory` constructor dependencies, a `ProviderSlug::OPEN_SRS` match arm, and a new `getOpenSrsService()` method that returns the environment client when no business unit is set, otherwise loads `OpenSrsProviderCredentials` and builds an account-bound client (with a debug log line). ~36 lines.
- **`DomainSeeder.php`** – adds a disabled OpenSRS `Provider` row and two `OpenSrsProviderCredentials` rows (Waterfront and Arge Web business units). ~22 lines.
- **`waterfront-backend.json`** – adds `opensrs-provider-credentials.*` translation keys (plural, singular, `api_url`, `username`, `api_key`) in `nl` and `en`. ~50 lines.
- **`DomainServiceFactoryTest.php`** – adds the two new constructor stubs to every `DomainServiceFactory` instantiation, a `driverResolvesOpenSrs` test and a `getDriverWithOpenSrsBusinessUnit` test asserting credential lookup, client creation and the debug log. ~81 lines.
