<?php

declare(strict_types=1);

namespace Tests\Domain\Ferry\Pipes;

use JsonException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Exception;
use Tests\Factories\ProviderFactory;
use Tests\Factories\ServerFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Ferry\Dto\Validation\ValidationPayload;
use Waterfront\Domain\Ferry\Pipes\SitebuilderMigrationPipe;
use Waterfront\Domain\Hosting\Actions\BaseKit\BaseKitGetSsoUrlAction;
use Waterfront\Domain\Hosting\Actions\Plesk\PleskGetSsoUrlAction;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitSiteResult;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitSite;
use Waterfront\Domain\Sitebuilder\DTO\BaseKitUser;
use Waterfront\Domain\Sitebuilder\SitebuilderService;
use Waterfront\Infra\DirectAdminClient\DTO\UserConfig;
use Waterfront\Infra\DirectAdminClient\Enums\HostingUserType;

#[CoversClass(SitebuilderMigrationPipe::class)]
#[AllowMockObjectsWithoutExpectations]
class SitebuilderMigrationPipeTest extends IntegrationTestCase
{
    private Server $sitebuilderServer;

    private Server $mailOnlyServer;

    private Server $mailOnlyServerPlesk;

    protected function setUp(): void
    {
        parent::setUp();

        ProviderFactory::new()->emailOnlyDirectAdmin()->createOne(['default' => true]);
        ProviderFactory::new()->emailOnlyPlesk()->createOne();
        ProviderFactory::new()->siteBuilderBaseKit()->createOne(['default' => true]);

        /** @var Server $sitebuilderServer */
        $sitebuilderServer = ServerFactory::new()
            ->sitebuilder()
            ->createOne(['hostname' => 'sitebuilder-server.test'])
            ->fresh();
        $this->sitebuilderServer = $sitebuilderServer;

        /** @var Server $mailOnlyServer */
        $mailOnlyServer = ServerFactory::new()
            ->directadminMail()
            ->createOne(['hostname' => 'mail-only-server.test'])
            ->fresh();
        $this->mailOnlyServer = $mailOnlyServer;

        /** @var Server $mailOnlyServerPlesk */
        $mailOnlyServerPlesk = ServerFactory::new()
            ->plesk()
            ->createOne(['hostname' => 'mail-only-server-plesk.test'])
            ->fresh();
        $this->mailOnlyServerPlesk = $mailOnlyServerPlesk;
    }

    /**
     * @param array<mixed> $expectedValidationResults
     *
     * @throws JsonException
     * @throws Exception
     */
    #[DataProvider('sitebuilderMigrationPipeProvider')]
    #[Test]
    public function sitebuilderMigrationPipe(
        array $expectedValidationResults
    ): void {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct.php');

        $reference = 'unique_reference_for_adf';

        $mockSitebuilderService = self::createStub(SitebuilderService::class);
        $mockSitebuilderService->method('getSiteFromRef')
            ->willReturn(new BaseKitSite(
                id: 456,
                domain: 'test-dns-intern-10.nl',
            ));

        $mockSitebuilderService->method('getUserFromRef')
            ->willReturn(new BaseKitUser(
                id: 123,
                email: 'test@email.test',
            ));

        $this->app->bind(SitebuilderService::class, fn () => $mockSitebuilderService);

        $mockSsoAction = self::createStub(BaseKitGetSsoUrlAction::class);
        $mockSsoAction->method('execute')
            ->willReturn('https://basekit.test/sso-test');
        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () => $mockSsoAction);

        $mockHostingService = self::createMock(HostingService::class);
        $mockHostingService->expects(self::once())
            ->method('getUserConfigAsDto')
            ->with(ProviderSlug::DIRECTADMIN->value, 'da1230', $this->mailOnlyServer)
            ->willReturn(
                new UserConfig(
                    dnscontrol: Parameters::STATE_ON,
                    ssl: Parameters::STATE_ON,
                    loginKeys: Parameters::STATE_ON,
                    vdomains: '2',
                    nemails: '5',
                    mysql: '3',
                    bandwidth: '10240',
                    quota: '2048',
                    package: 'custom',
                    usertype: HostingUserType::USER,
                    domain: 'test-dns-intern-10.nl',
                )
            );
        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $sitebuilderMigrationPipe = self::resolve(SitebuilderMigrationPipe::class);

        $validationPayload = $sitebuilderMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame($expectedValidationResults, $validationPayload->validationResults);
    }

    /**
     * @param array<mixed> $expectedValidationResults
     *
     * @throws JsonException
     * @throws Exception
     */
    #[DataProvider('sitebuilderMigrationPipeProvider')]
    #[Test]
    public function sitebuilderThroughGatewayMigrationPipe(
        array $expectedValidationResults
    ): void {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/subscriptions_correct.php');

        $customer['email'] = 'gatewaytest@sandwave.io';

        $reference = 'unique_reference_for_adf';

        $sitebuilderRequestResult = new BasekitSiteResult(
            provisionData: self::createStub(ProvisionRequestInterface::class),
            provisionStatus: ProvisionStatus::SUCCESS,
            siteRef: 1,
            domain: 'test-dns-intern-10.nl',
        );

        $provisionGatewayMock = $this->createMock(ProvisionGateway::class);
        $provisionGatewayMock->expects(self::once())
            ->method('request')
            ->willReturn($sitebuilderRequestResult);
        $this->app->bind(ProvisionGateway::class, fn () => $provisionGatewayMock);

        $mockSitebuilderService = self::createMock(SitebuilderService::class);

        $mockSitebuilderService->expects(self::never())
            ->method('getSiteFromRef');

        $mockSitebuilderService->expects(self::once())
            ->method('hasSitebuilderThroughGateway')
            ->with($customer['email'])
            ->willReturn(true);

        $mockSitebuilderService->method('getUserFromRef')
            ->willReturn(new BaseKitUser(
                id: 123,
                email: 'test@email.test',
            ));

        $this->app->bind(SitebuilderService::class, fn () => $mockSitebuilderService);

        $mockSsoAction = self::createStub(BaseKitGetSsoUrlAction::class);
        $mockSsoAction->method('execute')
            ->willReturn('https://basekit.test/sso-test');
        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () => $mockSsoAction);

        $mockHostingService = self::createMock(HostingService::class);
        $mockHostingService->expects(self::once())
            ->method('getUserConfigAsDto')
            ->with(ProviderSlug::DIRECTADMIN->value, 'da1230', $this->mailOnlyServer)
            ->willReturn(
                new UserConfig(
                    dnscontrol: Parameters::STATE_ON,
                    ssl: Parameters::STATE_ON,
                    loginKeys: Parameters::STATE_ON,
                    vdomains: '2',
                    nemails: '5',
                    mysql: '3',
                    bandwidth: '10240',
                    quota: '2048',
                    package: 'custom',
                    usertype: HostingUserType::USER,
                    domain: 'test-dns-intern-10.nl',
                )
            );
        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $sitebuilderMigrationPipe = self::resolve(SitebuilderMigrationPipe::class);

        $validationPayload = $sitebuilderMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame($expectedValidationResults, $validationPayload->validationResults);
    }

    /**
     * @return iterable<string, mixed>
     */
    public static function sitebuilderMigrationPipeProvider(): iterable
    {
        yield 'Pipeline success' => [
            'expectedValidationResults' => [
                'sitebuilder_migration' => [
                    [
                        'id' => 'sitebuilder_migration_passed',
                        'message' => 'sitebuilder_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function sitebuilderMigrationPipePlesk(): void
    {
        $customer = include(__DIR__ . '/data/customer_correct.php');
        $subscriptions = include(__DIR__ . '/data/sitebuilder_migration/sitebuilder_plesk_mail.php');

        $reference = 'unique_reference_for_adf';

        $mockSitebuilderService = self::createStub(SitebuilderService::class);
        $mockSitebuilderService->method('getSiteFromRef')
            ->willReturn(new BaseKitSite(
                id: 456,
                domain: 'test-dns-intern-10.nl',
            ));

        $mockSitebuilderService->method('getUserFromRef')
            ->willReturn(new BaseKitUser(
                id: 123,
                email: 'test@email.test',
            ));

        $this->app->bind(SitebuilderService::class, fn () => $mockSitebuilderService);

        $mockSsoAction = self::createStub(BaseKitGetSsoUrlAction::class);
        $mockSsoAction->method('execute')
            ->willReturn('https://basekit.test/sso-test');
        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () => $mockSsoAction);

        $mockHostingService = self::createMock(HostingService::class);
        $mockHostingService->expects(self::once())
            ->method('getUserConfigAsDto')
            ->with(ProviderSlug::PLESK->value, 'plesk1230', $this->mailOnlyServerPlesk)
            ->willReturn(
                new UserConfig(
                    dnscontrol: Parameters::STATE_ON,
                    ssl: Parameters::STATE_ON,
                    loginKeys: Parameters::STATE_ON,
                    vdomains: '2',
                    nemails: '5',
                    mysql: '3',
                    bandwidth: '10240',
                    quota: '2048',
                    package: 'custom',
                    usertype: HostingUserType::USER,
                    domain: 'test-dns-intern-10.nl',
                )
            );
        $this->app->bind(HostingService::class, fn () => $mockHostingService);

        $mockBaseKitSsoUrlAction = self::createMock(BaseKitGetSsoUrlAction::class);
        $mockBaseKitSsoUrlAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->sitebuilderServer, 123, 456)
            ->willReturn('https://basekit.test/sso-here');

        $this->app->bind(BaseKitGetSsoUrlAction::class, fn () => $mockBaseKitSsoUrlAction);

        $mockPleskSsoUrlAction = self::createMock(PleskGetSsoUrlAction::class);
        $mockPleskSsoUrlAction
            ->expects(self::once())
            ->method('execute')
            ->with($this->mailOnlyServerPlesk, 'plesk1230')
            ->willReturn('https://pleskmail.test/sso-here');

        $this->app->bind(PleskGetSsoUrlAction::class, fn () => $mockPleskSsoUrlAction);

        $validationPayload = new ValidationPayload(
            validationReference: $reference,
            customer: $customer,
            subscriptions: $subscriptions
        );

        $sitebuilderMigrationPipe = self::resolve(SitebuilderMigrationPipe::class);

        $validationPayload = $sitebuilderMigrationPipe->handle(
            $validationPayload,
            fn (ValidationPayload $validationPayload): ValidationPayload => $validationPayload
        );

        self::assertSame($reference, $validationPayload->validationReference);
        self::assertSame(
            [
                'sitebuilder_migration' => [
                    [
                        'id' => 'sitebuilder_migration_passed',
                        'message' => 'sitebuilder_migration reference: unique_reference_for_adf',
                    ],
                ],
            ],
            $validationPayload->validationResults
        );
    }
}
