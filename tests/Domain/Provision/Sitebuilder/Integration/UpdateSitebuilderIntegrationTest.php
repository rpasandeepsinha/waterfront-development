<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use SandwaveIo\BaseKit\Api\Interfaces\PackagesApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountPackage;
use SandwaveIo\BaseKit\Domain\Package;
use SandwaveIo\BaseKit\Exceptions\UnexpectedValueException;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Sitebuilder\Models\BasekitContext;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderResult;
use Waterfront\Domain\Provision\Sitebuilder\Services\UpdateBasekitService;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;

#[CoversClass(UpdateSitebuilderRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
#[CoversClass(UpdateBasekitService::class)]
class UpdateSitebuilderIntegrationTest extends IntegrationTestCase
{
    private const string TAG = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    private const string CONTEXT = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';

    private const int USER_REFERENCE = 234;

    private const int SITE_REFERENCE = 567;

    private const int INITIAL_CONTRACT_PERIOD = 1;

    private const int BRAND_REFERENCE = 1234;

    private ProvisionGateway $gateway;

    private PackagesApiInterface&MockObject $packagesApi;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        $username = 'username';
        $password = 'password';
        $ssoUrl = $baseUrl = 'https://api.basekit.com';

        $this->app->singleton(fn (): ConnectorConfig => new ConnectorConfig(
            baseUrl: $baseUrl,
            ssoUrl: $ssoUrl,
            username: $username,
            password: $password,
            brandReference: self::BRAND_REFERENCE,
        ));

        $baseKit = new BaseKit($username, $password);
        $this->packagesApi = $baseKit->packageApi = $this->createMock(PackagesApiInterface::class);
        $this->app->bind(BaseKit::class, fn () => $baseKit);

        $this->gateway = self::resolve(ProvisionGateway::class);
        $this->requestRepository = self::resolve(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function updateSitebuilderRequestSuccessful(): void
    {
        $tag = Uuid::fromString(self::TAG);
        $request = new UpdateSitebuilderRequest(
            tagUuid: $tag,
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [1337, $newPackageId = 7331],
            contractPeriod: self::INITIAL_CONTRACT_PERIOD,
        );

        $basekitContext = $this->createBasekitDeploymentWithRequest($context, $tag);

        $this->packagesApi
            ->expects(self::once())
            ->method('listUserPackages')
            ->with($basekitContext->user_ref)
            ->willReturn(
                [
                    $this->getAccountPackageWithPackageRef(1337),
                    $removeAccountPackage = $this->getAccountPackageWithPackageRef(7330),
                ],
            );

        $this->packagesApi
            ->expects(self::once())
            ->method('deleteUserPackage')
            ->with($basekitContext->user_ref, $removeAccountPackage->ref);

        $this->packagesApi
            ->expects(self::once())
            ->method('addUserPackage')
            ->with(
                $basekitContext->user_ref,
                $newPackageId,
                $request->contractPeriod,
            );

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 2);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(SitebuilderResult::class, $result);

        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertInstanceOf(ProvisioningRequest::class, $savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($context));
        self::assertNotNull($savedRequest->context_uuid);
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::UPDATE_SITEBUILDER, $savedRequest->request_name);
        self::assertSame(
            sprintf(
                '{"packages": [1337, 7331], "contractPeriod": %d}',
                $request->contractPeriod,
            ),
            $savedRequest->request_data,
        );
    }

    #[Test]
    public function updateSitebuilderRequestFailsOnListUserPackages(): void
    {
        $tag = Uuid::fromString(self::TAG);
        $request = new UpdateSitebuilderRequest(
            tagUuid: $tag,
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [1337, $newPackageId = 7331],
            contractPeriod: self::INITIAL_CONTRACT_PERIOD,
        );

        $this->createBasekitDeploymentWithRequest($context, $tag);

        $this->packagesApi
            ->expects(self::once())
            ->method('listUserPackages')
            ->willThrowException(new UnexpectedValueException('User reference not found.'));

        $this->packagesApi->expects(self::never())->method('deleteUserPackage');

        $this->packagesApi->expects(self::never())->method('addUserPackage');

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertDatabaseCount(ProvisioningResult::class, 2);
        self::assertDatabaseCount(ProvisioningRequest::class, 2);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(SitebuilderResult::class, $result);

        self::assertNotNull($result->exception);
    }

    private function createBasekitDeploymentWithRequest(UuidInterface $context, UuidInterface $tag): BasekitContext
    {
        $basekitContext = SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $context,
            'user_ref' => self::USER_REFERENCE,
        ]);

        $provisioningRequest = ProvisioningRequestFactory::new()
            ->sitebuilder()
            ->state([
                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                'context_uuid' => $context,
                'tag' => $tag,
            ])
            ->createOne();

        $sitebuilderDeployment = SitebuilderDeploymentFactory::new()->for($provisioningRequest, 'request')->createOne();

        BasekitSitebuilderDeploymentFactory::new()->for($sitebuilderDeployment)->createOne([
            'site_ref' => self::SITE_REFERENCE,
        ]);

        ProvisioningResultFactory::new()->state([
            'request_id' => $provisioningRequest->id,
            'status' => ProvisionStatus::SUCCESS,
        ])->createOne();

        return $basekitContext;
    }

    private function getAccountPackageWithPackageRef(int $packageRef): AccountPackage
    {
        return new AccountPackage(
            ref: random_int(1, 99999),
            startDateTime: [],
            endDateTime: [],
            updated: [],
            deleteOnExpiry: 1,
            isFree: false,
            billingPeriodMonths: self::INITIAL_CONTRACT_PERIOD,
            isActive: true,
            package: new Package(
                ref: $packageRef,
                name: '',
                active: 1,
                global: 1,
                urlID: null,
                notifyMarketing: '',
                productType: '',
                type: '',
                contentType: '',
                offerRebillMonths: 1,
                offerRebillActive: 1,
                trialDays: 1,
                imageURL: null,
                requirePurchasedDomain: true,
                allowMultiplePurchase: true,
                showInStore: true,
                showInTemplatePicker: true,
                bannerHTML: null,
                affiliateLink: '',
                flowName: null,
                metadata: null,
                brandRef: 1,
                brandName: '',
                defaultCurrencyRef: 1,
                currencyCode: '',
                currencyName: '',
                currencyTitle: '',
                defaultCampaignRef: null,
                capabilities: [],
                templateGroupRef: null,
                templateGroup: null,
                displayOrder: null,
                domainProduct: null,
                prices: [],
                plugins: [],
            ),
            displayOrder: null,
        );
    }
}
