### General Class Reference

#### `SslDeployment` (Model)
- **Table**: `ssl_subscriptions`

**Relationships**:
- `subscription(): BelongsTo<Subscription>`
- `provider(): BelongsTo<Provider>`

**Hooks**:
- On `creating`, sets `provider_id` to the default SSL provider if unset.

----------

#### `SslServiceFactory`
 **Resolves between**:
- **`RtrSslService`** (Realtime Register)
- **`SslPlaceholderService`** (stub)
- **`OpenproviderSslService`** (OpenProvider)

----------

#### Driver Interface
```php
interface SslDriverInterface

{

public function create(ProductSpec, int $period, array $customerData, SslDeployment, ?string $csr): Result;

public function retrieve(SslDeployment): Result;

public function reissue(array $customerData, SslDeployment, string $csr): Result;

public function renew(SslDeployment): Result;

public function check(string $domain = '', int $period = 12): bool;

public function prepareCertificates(int $certificateId, string $domain, array $certificates): void;

public function prepareCertificateInstallParameters(int, string, array, bool): array;

public function csrExistsForDomain(string $domain): bool;

public function validate(string $csr, string $domain, ?SslDeployment): array|bool;

public function resolveCsrDomain(string $commonName): string;

public function compareTwoDomains(string $one, string $two): bool;

public function checkPrivateKeyMatches(string $domain): bool;

}

  ```
----------

#### `RemoteSslServiceClient`

| Method                                                                 | Purpose                                                                                   |
|------------------------------------------------------------------------|-------------------------------------------------------------------------------------------|
| `create(int $period, SslDeployment, ?string $csr): Result`            | Wraps driver’s `create()`, logs, catches exceptions → `Result::STATUS_ERROR`.            |
| `retrieve(SslDeployment): Result`                                     | Delegates to driver’s `retrieve()`.                                                      |
| `reissue(SslDeployment, string $csr): Result`                         | Logs and delegates to driver’s `reissue()`.                                              |
| `renew(SslDeployment): Result`                                        | Logs and delegates to driver’s `renew()`.                                                |
| `csrExistsForDomain(string $domain, ProviderSlug $driver): bool`     | Checks driver’s CSR existence.                                                           |
| `csrValidate(string $csr, string $domain, ?SslDeployment): array`     | Validates CSR and domain, returns result as array.                                       |



----------

### `SslService` (OpenProvider Driver)

- Implements `SslDriverInterface`:

| Method                                              | Description                                                                                                  |
|-----------------------------------------------------|--------------------------------------------------------------------------------------------------------------|
| `create(...) : Result`                              | Delegates to `CreateCertificatePipeline::process`.                                                           |
| `retrieve(SslDeployment): Result`                   | Calls `OpenproviderClient->retrieveSsl(certificate_id)`.                                                     |
| `reissue(customerData, SslDeployment, csr): Result` | Builds `Parameters`, calls `reissueSsl()`, dispatches `UpdateDns` job, runs `CheckSsl`.                      |
| `renew(SslDeployment): Result`                      | Retrieves current, updates `last_result`, calls `renewSsl()`, dispatches `UpdateDns` job.                    |
| `check(domain, period): bool`                       | Delegates to `CertificateService::check()`.                                                                  |
| `prepareCertificates(...) : void`                   | Fetches SSL, saves CSR & certificates via `CsrManager` and `CertificateManager`.                             |
| `prepareCertificateInstallParameters(...) : array`  | Gathers private key, CSR, certificates via `CertificateService`.                                             |
| `csrExistsForDomain(domain): bool`                  | Delegates to `CsrManager`.                                                                                   |
| `validate(csr, domain): array<bool>`                | Validates the CSR and domain, returns array of boolean checks.                                               |
| `checkPrivateKeyMatches(domain): bool`              | Compares stored key against installed cert using `openssl_x509_check_private_key`.                           |


----------

### `RtrSslService` vs. `SslService`

| Feature                      | `RtrSslService`                                                                   | `SslService` (Openprovider)                                                              |
|-----------------------------|------------------------------------------------------------------------------------|------------------------------------------------------------------------------------------|
| **CSR Generation**          | Via `GenerateCsrStep` + `CsrManager`.                                             | Via `CreateCertificatePipeline`.                                                         |
| **DNS Update**              | Calls `SslDnsService::updateDns`, synchronous.                                     | Uses queued job (`UpdateDns`) + `SslDnsService`.                                         |
| **Ordering API**            | `CertificateRequester` → Realtime Register PHP SDK.                               | `OpenproviderClientFactory` → Openprovider API.                                          |
| **Reissue Flow**            | Regenerates CSR, updates DNS, calls `reissueCertificate`.                          | Dispatches `UpdateDns`, runs `CheckSsl` console command.                                 |
| **Prepare Certificates Storage** | **Not implemented** (`NotImplementedException`).                                 | Implements `prepareCertificates`: fetch + save root/intermediate/main certs.            |
| **Error Handling**          | Wraps all remote calls in try/catch; logs errors.                                 | Similar, but rethrows wrapped exceptions on renew failure.                               |



----------
