<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use UnexpectedValueException;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Enums\MigrationValidation;
use Waterfront\Domain\Ferry\Enums\MigrationValidationPipes;
use Waterfront\Domain\Ferry\Pipes\ResellerHostingMigrationPipe;
use Waterfront\Domain\Hosting\Interfaces\Hosting\SiteConfigInterface;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(ResellerHostingMigrationPipe::class)]
class ResellerHostingMigrationPipeTest extends IntegrationTestCase
{
    private const string REFERENCE = 'unique_reference_for_adf';

    #[Test]
    public function isHostingMigrationReseller(): void
    {
        $username = 'i_am_a_non_reseller_user';

        ProviderFactory::new()->hostingDirectAdmin()->createOne();

        $serverHostname = 'my_hostname.nl';
        $serverDirectAdmin = ServerFactory::new()->directadmin()->createOne(['hostname' => $serverHostname]);
        $serverDirectAdmin = $serverDirectAdmin->fresh();

        $mock = self::createStub(HostingService::class);
        $mock->method('getUserConfigAsDto')
            ->willReturnCallback(
                fn (string $driver, string $userName, Server $server): SiteConfigInterface => match ([$driver, $userName, $server->hostname]) {
                    [ProviderSlug::DIRECTADMIN->value, $username, $serverHostname] => new UserConfig(
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
                    ),
                    default => throw new UnexpectedValueException(),
                }
            );

        $this->app->bind(HostingService::class, fn () => $mock);

        $expectedPayload = [
            MigrationValidationPipes::RESELLER_HOSTING_MIGRATION->value => [
                [
                    'id' => MigrationValidation::RESELLER_HOSTING_MIGRATION_IS_NOT_RESELLER->value,
                    'message' => 'Reseller hosting instance with driver "directadmin" on server "my_hostname.nl" with username "i_am_a_non_reseller_user" is not a reseller',
                    'reference_subscription_id' => '3535',
                ],
                [
                    'id' => MigrationValidation::RESELLER_HOSTING_PIPE_PASSED->value,
                    'message' => 'reseller_hosting_migration reference: unique_reference_for_adf',
                ],
            ],
        ];

        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/reseller_hosting_migration/reseller_user_not_a_reseller.php');

        $validationPayload = new ValidationPayload(
            validationReference: self::REFERENCE,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $resellerHostingMigrationPipe = self::resolve(ResellerHostingMigrationPipe::class);
        $processedPayload = $resellerHostingMigrationPipe->handle($validationPayload, fn ($result): ValidationPayload => $result);

        self::assertSame($expectedPayload, $processedPayload->validationResults);
    }
}
