### Note
This is a archived file from the `DirectAdminClient`. It was used when it was still an external composer package.

# Changelog

All notable changes to the `DirectAdmin` package will be documented in this file

## v3.2.0
### Added
- SuspendUser command.
- UnsuspendUser command.

## v3.1.0
### Added
- DisableLetsEncryptAutoRenew command.

## v3.0.0

### Changed
- __Breaking change__ Required function parameters should precede optional arguments
- __Breaking change__ The faker class is not automatically loaded anymore


## v2.2.0
### Changed
- Make sure that calls are always done as admin by default unless `asUser` method is provided.

## v2.1.0
### Added
- DeleteDomains command to delete a domain of a user.

### Changed
- Update `ShowDomain` command to allow check for absence of domain.

## v2.0.1

### Changed
- Update `DirectAdmin` faker to use correct response data for `CMD_API_SHOW_USER_USAGE`

## v2.0.0

### Added
- ShowDomain Command
- Retrieval of existing domain and user settings for modifying.

### Changed
- __Breaking change__ Minimal php 8.0
- __Breaking change__ ShowUserStats command to be called from admin with all stats and domains
- Test are now compatibel with lates DA password and user rules.

## v1.2.13
### Adding
- Add `DirectAdminConnectionException` when connection with DirectAdmin fails.

## v1.2.12
### Fixes
- `CreateLoginKey` no longer checks for hardcoded text response.

## v1.2.11
### Changed
- `sslCertificate` method allows multiple `DirectAdminCommands` instead of strict `UploadSSL` (for CA upload e.g.)
-  user create command , so ssl is enabled by default

### Adding
- Ca certificate upload command

## v1.2.10
### Changed
- ModifyDomain added setAction function to be able to go from change to create mode

## v1.2.9
### Fixing
- Fix GetNameServers faker

## v1.2.8
### Adding
- Faker for GetNameServers

## v1.2.7

### Adding
- get nameservers command

## v1.2.6
### Changed
- Resolved bug in get_class DirectAdminApi

## v1.2.5

### Adding
- command CMD_SHOW_USERS used to fetch customers from reseller
- ShowResellerUsers command classs
- GetResellerUsersTest to create reseller with customers and check ShowReseller users and clean

### Changed
- Faker class DirectAdminApi extended with ShowResellers and ShowResellerUsers

## 1.2.4

### Changed
-  Updated ResellerTest cleanup on live.

## v1.2.3

### Adding
- Reseller properties to DirectAdmin Faker
- Reseller commands to DirectAdminAPI Faker

### Changed
- Updated Reseller live tests to leave server clean when done.

## v1.2.2

### Changed
- Reverted Faker to 1.2.0 version because of testing failures.

## v1.2.1

### Changed
- DirectAdmin Faker now extends main DirectAdmin.

## v1.2.0

### Adding
- CreateReseller command
- ShowReseller command
- ModifyDomain command
- ModifyReseller command
- Reseller Facade
- Reseller Package Facade

### Updated
- Guzzle client to `^7.0`

### Changed
- Changed test cases to also apply testing for reseller users
- Changed supported PHP version to 7.4 and 8.0
- CI to test both PHP 7.4 and 8.0
- Guzzle `http_error` set to `false` to not throw exceptions on http responses

### Removed
- `Laravel/helpers` dependency

## v1.0.1

### Changed
- Downgraded Guzzle package to satisfy `~6.2` dependency.

## v1.0.0

### Adding
-  Guzzle Library for doing HTTP calls
-  Connection Class for managing connecting with Direct Admin Server
-  Abstract Class and Interface for Commands
-  Custom exceptions for connection and command
-  Docker compose for testing database
-  Admin Stats command
-  Create, delete and show users commands
-  Create, delete, modfiy and show login keys commands
-  'login-as' on connection to login as different user
-  `composer style-check` and `composer style-fix` scripts with `phpcs`
-  `composer mess-check` scripts with `phpmd`
-  Custom wrapper class for packages to have clean API commands

### Changed
- API class is now constructed with the `DirectAdminServer` implementation instead of a `Connection`
- Namespace changed to Alliance
- Using phpunit.xml from secrets in CI
- Using PSR7 Request inside command classes, sending request using API
- Refactoring package to remove Laravel / Illuminate dependencies


### Removed
- Contracts/Interfaces of Realcloud application
- Custom implementation of url decoding from API responses
- Password confirmation checks, the user of the package should be validating his own input for passwords.
