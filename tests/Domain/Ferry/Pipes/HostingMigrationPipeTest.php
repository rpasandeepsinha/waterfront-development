<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use GuzzleHttp\Exception\TransferException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\HostingMigrationPipe;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\DTO\DirectAdminUserPackage;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(HostingMigrationPipe::class)]
class HostingMigrationPipeTest extends IntegrationTestCase
{
    private const string REFERENCE = 'unique_reference_for_adf';

    protected function setUp(): void
    {
        parent::setUp();

        ProviderFactory::new()->hostingPlaceholder()->createOne();
        ProviderFactory::new()->hostingDirectAdmin()->createOne(['default' => true]);

        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => 'my_hostname.nl']);
        $serverDirectAdmin->fresh();
    }

    /**
     * @param array<string, mixed>        $subscriptions
     * @param array<string, array<mixed>> $expectedValidationResults
     */
    #[DataProvider('hostingMigrationPipeProvider')]
    #[Test]
    public function hostingMigrationPipe(
        array $subscriptions,
        array $expectedValidationResults,
    ): void {
        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: include(__DIR__ . '/data/customer_correct.php'),
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedValidationResults, $processedPayload->validationResults);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function hostingMigrationPipeProvider(): iterable
    {
        yield 'Validate missing fields' => [
            'subscriptions' => include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_missing_fields.php'),
            'expectedValidationResults' => [
                MigrationValidationPipes::HOSTING_MIGRATION->value => [
                    [
                        'id' => 'laravel_validation',
                        'message' => [
                            '0.hostname' => [
                                'Dit veld is verplicht.',
                            ],
                            '0.driver' => [
                                'Dit veld is verplicht.',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                        'message' => 'hosting_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Validate bad payload' => [
            'subscriptions' => include(__DIR__ . '/data/hosting_migration/hosting_bad_payload.php'),
            'expectedValidationResults' => [
                MigrationValidationPipes::HOSTING_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::DEFAULT_VALIDATION->value,
                        'message' => [
                            '0.hostname' => [
                                'Het geselecteerde veld is ongeldig.',
                            ],
                            '0.driver' => [
                                'Het geselecteerde veld is ongeldig.',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                        'message' => 'hosting_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Validate server invalid' => [
            'subscriptions' => include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_servers.php'),
            'expectedValidationResults' => [
                MigrationValidationPipes::HOSTING_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::DEFAULT_VALIDATION->value,
                        'message' => [
                            '0.hostname' => [
                                'Het geselecteerde veld is ongeldig.',
                            ],
                        ],
                    ],
                    [
                        'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                        'message' => 'hosting_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];

        yield 'Denormalization failed' => [
            'subscriptions' => include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_user_denormalize_exception.php'),
            'expectedValidationResults' => [
                MigrationValidationPipes::HOSTING_MIGRATION->value => [
                    [
                        'id' => MigrationValidation::HOSTING_MIGRATION_PAYLOAD_INVALID->value,
                        'message' => 'Unable to denormalize hosting migration validation payload',
                        'data' => [
                            'payload' => [
                                [
                                    'domain' => 'test-dns-intern-10.nl',
                                    'extension' => '.nl',
                                    'start_date' => '2023-02-06',
                                    'next_contract_date' => '2023-03-06',
                                    'next_billing_date' => '2023-03-06',
                                    'slug' => 'start',
                                    'reference_product_id' => 'legacy hosting basic',
                                    'reference_subscription_id' => '3535',
                                    'hostname' => 'my_hostname.nl',
                                    'driver' => 'directadmin',
                                    'server_data' => [
                                        'directadmin_customer_name' => 'i_dont_exist',
                                    ],
                                ],
                            ],
                            'exception' => 'Cannot create an instance of "Waterfront\Domain\Ferry\Dto\Validation\ValidationHostingSubscriptionPayload" from serialized data because its constructor requires the following parameters to be present : "$contractPeriod", "$billingPeriod".',
                        ],
                    ],
                    [
                        'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                        'message' => 'hosting_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function validateFetchFailed(): void
    {
        $mock = self::createStub(HostingService::class);
        $mock->method('getUserConfigAsDto')
            ->willThrowException(new TransferException('unable to connect to server'));

        $this->app->bind(HostingService::class, fn () => $mock);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_USER_FETCH_FAILED->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" not found',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_user.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function isHostingMigrationReseller(): void
    {
        $mock = self::createStub(HostingService::class);
        $mock->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::RESELLER,
                domain: 'testupgradefixversio.nl',
            ));

        $this->app->bind(HostingService::class, fn () => $mock);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_IS_RESELLER->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" is a reseller',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_matched_nameservers_external_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validationSsoFailed(): void
    {
        $mockHostingService = self::createStub(HostingService::class);
        $mockHostingService->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
                domain: 'testupgradefixversio.nl',
            ));

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);

        $mockAction->method('execute')
            ->willThrowException(new SsoResolveException('unable to connect to server'));

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_USER_SSO_FAILED->value,
                    'message' => 'Hosting SSO with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" could not be generated',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_user.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validationSameNameserverAsServerHostnameWithExternalDomain(): void
    {
        $username = 'i_dont_exist';
        $hostname = 'my_hostname.nl';
        $ipv4AddressForDaServer = '34.35.36.37';
        $differentIpv4Address = '72.66.22.88';

        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => $hostname, 'ipv4' => $ipv4AddressForDaServer]);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $userData = include __DIR__ . '/data/hosting_migration/directadmin_show_user_response.php';

        $mockHostingService = self::createStub(HostingService::class);
        $mockHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new DirectAdminUserPackage(
                vdomains: '2',
                nemails: '5',
                mysql: '3',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
            ));
        $mockHostingService->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
                domain: 'testupgradefixversio.nl',
            ));

        $mockHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn(true);

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);
        $mockAction->method('execute')
            ->willReturnCallback(
                fn (Server $server, string $userName, string $ipAddress): string => match ([$server, $userName, $ipAddress]) {
                    [$serverDirectAdmin, $username, '127.0.0.1'] => 'my_sso_link',
                    default => throw new UnexpectedValueException()
                }
            )
            ->willReturn('my_sso_link');

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" attempting to migrate as a basic offering.',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 2,
                            'max_amount_mail_accounts' => 5,
                            'max_amount_databases' => 3,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3535}, internal nameservers: {1}, domain is present in extension subscriptions or is null {0}, results in DNS setting: {1}',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_matched_nameservers_external_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validationDifferentNameserverAsServerHostnameWithExternalDomain(): void
    {
        $username = 'i_dont_exist';
        $hostname = 'my_hostname.nl';
        $ipv4AddressForDaServer = '34.35.36.37dd';
        $differentIpv4Address = '72.66.22.88';

        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => $hostname, 'ipv4' => $ipv4AddressForDaServer]);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $userData = include __DIR__ . '/data/hosting_migration/directadmin_show_user_response.php';

        $mockHostingService = self::createStub(HostingService::class);
        $mockHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new DirectAdminUserPackage(
                vdomains: '2',
                nemails: '5',
                mysql: '3',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
            ));
        $mockHostingService->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
                domain: 'testupgradefixversio.nl',
            ));
        $mockHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn(false);

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);
        $mockAction->method('execute')
            ->willReturnCallback(
                fn (Server $server, string $userName, string $ipAddress): string => match ([$server, $userName, $ipAddress]) {
                    [$serverDirectAdmin, $username, '127.0.0.1'] => 'my_sso_link',
                    default => throw new UnexpectedValueException()
                }
            )
            ->willReturn('my_sso_link');

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" attempting to migrate as a basic offering.',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 2,
                            'max_amount_mail_accounts' => 5,
                            'max_amount_databases' => 3,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3535}, internal nameservers: {0}, domain is present in extension subscriptions or is null {0}, results in DNS setting: {0}',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_matched_nameservers_external_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validationSameNameserverAsServerHostnameWithInternalDomain(): void
    {
        $username = 'i_dont_exist';
        $hostname = 'my_hostname.nl';
        $ipv4AddressForDaServer = '34.35.36.37';
        $differentIpv4Address = '72.66.22.88';

        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => $hostname, 'ipv4' => $ipv4AddressForDaServer]);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $userData = include __DIR__ . '/data/hosting_migration/directadmin_show_user_response.php';

        $mockHostingService = self::createStub(HostingService::class);
        $mockHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new DirectAdminUserPackage(
                vdomains: '2',
                nemails: '5',
                mysql: '3',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
            ));
        $mockHostingService->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
                domain: 'testupgradefixversio.nl',
            ));
        $mockHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn(true);

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);
        $mockAction->method('execute')
            ->willReturnCallback(
                fn (Server $server, string $userName, string $ipAddress): string => match ([$server, $userName, $ipAddress]) {
                    [$serverDirectAdmin, $username, '127.0.0.1'] => 'my_sso_link',
                    default => throw new UnexpectedValueException()
                }
            )
            ->willReturn('my_sso_link');

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" attempting to migrate as a basic offering.',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 2,
                            'max_amount_mail_accounts' => 5,
                            'max_amount_databases' => 3,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3535}, internal nameservers: {1}, domain is present in extension subscriptions or is null {1}, results in DNS setting: {0}',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_matched_nameservers_internal_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validationHostingPackageNotInSync(): void
    {
        $username = 'i_dont_exist';
        $hostname = 'my_hostname.nl';
        $ipv4AddressForDaServer = '34.35.36.37';
        $differentIpv4Address = '72.66.22.88';

        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => $hostname, 'ipv4' => $ipv4AddressForDaServer]);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $userData = include __DIR__ . '/data/hosting_migration/directadmin_show_user_response.php';

        $mockHostingService = self::createStub(HostingService::class);
        $mockHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new DirectAdminUserPackage(
                vdomains: '2',
                nemails: '5',
                mysql: '3',
                bandwidth: '1024',
                quota: '1024',
                package: 'web-mini',
            ));
        $mockHostingService->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'web-mini',
                usertype: HostingUserType::USER,
                domain: 'testupgradefixversio.nl',
            ));
        $mockHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn(true);

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);
        $mockAction->method('execute')
            ->willReturnCallback(
                fn (Server $server, string $userName, string $ipAddress): string => match ([$server, $userName, $ipAddress]) {
                    [$serverDirectAdmin, $username, '127.0.0.1'] => 'my_sso_link',
                    default => throw new UnexpectedValueException()
                }
            )
            ->willReturn('my_sso_link');

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" attempting to migrate as a basic offering.',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 2,
                            'max_amount_mail_accounts' => 5,
                            'max_amount_databases' => 3,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3535}, internal nameservers: {1}, domain is present in extension subscriptions or is null {1}, results in DNS setting: {0}',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_NOT_IN_SYNC->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" service plan remote "web-mini" is not in sync with the local product "basic"',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'remote_package' => 'web-mini',
                        'payload_package' => 'basic',
                        'username' => 'i_dont_exist',
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_matched_nameservers_internal_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validationSameNameserverAsServerHostnameNoDomainInBackend(): void
    {
        $username = 'i_dont_exist';
        $hostname = 'my_hostname.nl';
        $ipv4AddressForDaServer = '34.35.36.37';
        $differentIpv4Address = '72.66.22.88';

        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => $hostname, 'ipv4' => $ipv4AddressForDaServer]);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $userData = include __DIR__ . '/data/hosting_migration/directadmin_show_user_response.php';

        $mockHostingService = self::createStub(HostingService::class);
        $mockHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new DirectAdminUserPackage(
                vdomains: '2',
                nemails: '5',
                mysql: '3',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
            ));
        $mockHostingService->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
            ));
        $mockHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn(true);

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);
        $mockAction->method('execute')
            ->willReturnCallback(
                fn (Server $server, string $userName, string $ipAddress): string => match ([$server, $userName, $ipAddress]) {
                    [$serverDirectAdmin, $username, '127.0.0.1'] => 'my_sso_link',
                    default => throw new UnexpectedValueException()
                }
            )
            ->willReturn('my_sso_link');

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" attempting to migrate as a basic offering.',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 2,
                            'max_amount_mail_accounts' => 5,
                            'max_amount_databases' => 3,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3535}, internal nameservers: {1}, domain is present in extension subscriptions or is null {1}, results in DNS setting: {0}',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_matched_nameservers_internal_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validationDifferentNameserverAsServerHostnameWithInternalDomain(): void
    {
        $username = 'i_dont_exist';
        $hostname = 'my_hostname.nl';
        $ipv4AddressForDaServer = '34.35.36.37';
        $differentIpv4Address = '72.66.22.88';

        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => $hostname, 'ipv4' => $ipv4AddressForDaServer]);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $userData = include __DIR__ . '/data/hosting_migration/directadmin_show_user_response.php';

        $mockHostingService = self::createStub(HostingService::class);
        $mockHostingService->method('getPackageOnServerAsDto')
            ->willReturn(new DirectAdminUserPackage(
                vdomains: '2',
                nemails: '5',
                mysql: '3',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
            ));
        $mockHostingService->method('getUserConfigAsDto')
            ->willReturn(new UserConfig(
                dnscontrol: 'ON',
                ssl: 'ON',
                loginKeys: 'ON',
                vdomains: '10',
                nemails: '10',
                mysql: '10',
                bandwidth: '1024',
                quota: '1024',
                package: 'basic',
                usertype: HostingUserType::USER,
                domain: 'testupgradefixversio.nl',
            ));
        $mockHostingService
            ->method('isUsingHostingServerAsNameserver')
            ->willReturn(false);

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);
        $mockAction->method('execute')
            ->willReturnCallback(
                fn (Server $server, string $userName, string $ipAddress): string => match ([$server, $userName, $ipAddress]) {
                    [$serverDirectAdmin, $username, '127.0.0.1'] => 'my_sso_link',
                    default => throw new UnexpectedValueException()
                }
            )
            ->willReturn('my_sso_link');

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PACKAGE_STATE->value,
                    'message' => 'Hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_dont_exist" attempting to migrate as a basic offering.',
                    'reference_subscription_id' => '3535',
                    'data' => [
                        'user_config' => [
                            'max_amount_domains' => 10,
                            'max_amount_mail_accounts' => 10,
                            'max_amount_databases' => 10,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                        'user_stats' => [],
                        'package_config' => [
                            'max_amount_domains' => 2,
                            'max_amount_mail_accounts' => 5,
                            'max_amount_databases' => 3,
                            'max_network_traffic_in_MB' => 1024,
                            'max_disk_space_in_MB' => 1024,
                        ],
                    ],
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_DNS_MANAGEMENT_STATE->value,
                    'message' => 'Hosting migration for subscription reference ID: {3535}, internal nameservers: {0}, domain is present in extension subscriptions or is null {1}, results in DNS setting: {0}',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::HOSTING_MIGRATION_PIPE_PASSED->value,
                    'message' => 'hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/hosting_migration/hosting_bad_payload_matched_nameservers_internal_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(HostingMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }
}
