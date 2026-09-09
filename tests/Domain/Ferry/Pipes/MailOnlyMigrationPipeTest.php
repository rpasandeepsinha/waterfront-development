<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use GuzzleHttp\Exception\TransferException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\Factories\SpamExpertsClusterFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\MailOnlyMigrationPipe;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(MailOnlyMigrationPipe::class)]
class MailOnlyMigrationPipeTest extends IntegrationTestCase
{
    private const string REFERENCE = 'unique_reference_for_adf';

    #[Test]
    public function validateBadHostingPayload(): void
    {
        $username = 'i_dont_exist';

        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigration',
        ]);

        $providerDirectAdmin = ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();

        $serverDirectAdmin = ServerFactory::new()->directadminMail()->createOne(['hostname' => 'my_hostname.nl']);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $expectedPayload = [
            MigrationValidationPipes::MAIL_ONLY_MIGRATION->value => [
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
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED->value,
                    'message' => 'mail_only_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/mail_only_migration/mail_only_bad_payload_missing_fields.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(MailOnlyMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateFetchFailed(): void
    {
        $username = 'i_dont_exist';

        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigration',
        ]);

        $providerDirectAdmin = ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();

        $serverDirectAdmin = ServerFactory::new()->directadminMail()->createOne(['hostname' => 'my_mail_hostname.nl']);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $mock = self::createStub(HostingService::class);
        $mock->method('getUserConfigAsDto')
            ->willReturnCallback(
                fn (string $driver, string $userName, Server $server): SiteConfigInterface => match ([$driver, $userName, $server]) {
                    [ProviderSlug::DIRECTADMIN->value, $username, $serverDirectAdmin] => [],
                    default => throw new UnexpectedValueException(),
                }
            )
            ->willThrowException(new TransferException('unable to connect to server'));

        $this->app->bind(HostingService::class, fn () => $mock);

        $expectedPayload = [
            MigrationValidationPipes::MAIL_ONLY_MIGRATION->value => [
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_USER_FETCH_FAILED->value,
                    'message' => 'Mail Only instance with driver "integratedservice" on server "my_mail_hostname.nl" with username "i_dont_exist" not found',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED->value,
                    'message' => 'mail_only_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/mail_only_migration/mail_only_bad_payload_user.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(MailOnlyMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function ssoGenerateFailed(): void
    {
        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigration',
        ]);

        $providerDirectAdmin = ProviderFactory::new()->pleskHosting()->createOne();

        $serverDirectAdmin = ServerFactory::new()->directadminMail()->createOne(['hostname' => 'my_mail_hostname.nl']);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

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
                domain: 'test.nl',
            ));

        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockAction = self::createStub(GetSsoUrlAction::class);

        $mockAction->method('execute')
            ->willThrowException(new SsoResolveException('unable to connect to server'));

        $this->app->bind(GetSsoUrlAction::class, fn () => $mockAction);

        $expectedPayload = [
            MigrationValidationPipes::MAIL_ONLY_MIGRATION->value => [
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_USER_SSO_FAILED->value,
                    'message' => 'Mail only SSO with driver "integratedservice" on server "my_mail_hostname.nl" with username "i_dont_exist" could not be generated',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED->value,
                    'message' => 'mail_only_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/mail_only_migration/mail_only_bad_payload_user.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(MailOnlyMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function validateDenormalizeException(): void
    {
        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigration',
        ]);

        $providerDirectAdmin = ProviderFactory::new()->hostingDirectAdmin()->createOne();

        $serverDirectAdmin = ServerFactory::new()->directadminMail()->createOne(['hostname' => 'my_mail_hostname.nl']);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $expectedPayload = [
            MigrationValidationPipes::MAIL_ONLY_MIGRATION->value => [
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PAYLOAD_INVALID->value,
                    'message' => 'Unable to denormalize mail only migration validation payload',
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
                                'hostname' => 'my_mail_hostname.nl',
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
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED->value,
                    'message' => 'mail_only_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/mail_only_migration/mail_only_bad_payload_user_denormalize_exception.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(MailOnlyMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function isHostingMigrationReseller(): void
    {
        $username = 'i_do_exist_as_reseller';

        SpamExpertsClusterFactory::new()->createOne([
            'business_unit' => 'testmigration',
        ]);

        $providerDirectAdmin = ProviderFactory::new()->emailOnlyDirectAdmin()->createOne();

        $serverDirectAdmin = ServerFactory::new()->directadminMail()->createOne(['hostname' => 'my.mail.hostname.nl']);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $mock = self::createStub(HostingService::class);
        $mock->method('getUserConfigAsDto')
            ->willReturnCallback(
                fn (string $driver, string $userName, Server $server): SiteConfigInterface => match ([$driver, $userName, $server]) {
                    [ProviderSlug::DIRECTADMIN->value, $username, $serverDirectAdmin] => [],
                    default => throw new UnexpectedValueException(),
                }
            )
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
            MigrationValidationPipes::MAIL_ONLY_MIGRATION->value => [
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_IS_RESELLER->value,
                    'message' => 'Mail Only instance with driver "directadmin" on server "my.mail.hostname.nl" with username "i_dont_exist" is a reseller',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED->value,
                    'message' => 'mail_only_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/mail_only_migration/mail_only_bad_payload_matched_nameservers_external_domain.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $hostingMigrationPipe = self::resolve(MailOnlyMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }

    #[Test]
    public function noSpamExpertsCluster(): void
    {
        $expectedPayload = [
            MigrationValidationPipes::MAIL_ONLY_MIGRATION->value => [
                [
                    'id' => MigrationValidation::MAIL_ONLY_NO_LEGACY_SPAMEXPERTS_SERVERS_CONFIGURED->value,
                    'message' => 'There is no legacy SpamExperts servers configured for business unit testmigration',
                ],
                [
                    'id' => MigrationValidation::MAIL_ONLY_MIGRATION_PIPE_PASSED->value,
                    'message' => 'mail_only_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: [
                'mail-only' => [],
            ],
        );

        $hostingMigrationPipe = self::resolve(MailOnlyMigrationPipe::class);
        $processedPayload = $hostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }
}
