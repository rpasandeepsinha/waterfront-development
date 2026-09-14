<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Services;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\Api\Interfaces\PackagesApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountPackage;
use SandwaveIo\BaseKit\Domain\Package;
use SandwaveIo\BaseKit\Exceptions\UnexpectedValueException;
use Tests\Factories\BasekitContextFactory;
use Tests\TestCase;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\BasekitUserRefNotFoundForContextException;
use Waterfront\Domain\Provision\Sitebuilder\Exceptions\UpdateBasekitException;
use Waterfront\Domain\Provision\Sitebuilder\Repositories\BasekitContextRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\UpdateSitebuilderRequest;
use Waterfront\Domain\Provision\Sitebuilder\Services\UpdateBasekitService;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(UpdateBasekitService::class)]
#[AllowMockObjectsWithoutExpectations]
class UpdateBasekitServiceTest extends TestCase
{
    private const string TAG = 'aaaaaaaa-aaaa-4aaa-aaaa-aaaaaaaaaaaa';

    private const string CONTEXT = 'bbbbbbbb-bbbb-4bbb-bbbb-bbbbbbbbbbbb';

    private const int USER_REFERENCE = 234;

    private const int INITIAL_CONTRACT_PERIOD = 1;

    private PackagesApiInterface&MockObject $packagesApi;

    private LoggerInterface&MockObject $logger;

    private BasekitContextRepository&MockObject $baseKitContextRepository;

    private UpdateBasekitService $updateBasekitService;

    protected function setUp(): void
    {
        parent::setUp();

        $baseKit = new BaseKit('username', 'password');
        $this->packagesApi = $baseKit->packageApi = $this->createMock(PackagesApiInterface::class);
        $this->baseKitContextRepository = self::createMock(BasekitContextRepository::class);

        $this->updateBasekitService = new UpdateBasekitService(
            baseKitClient: $baseKit,
            logger: $this->logger = self::createMock(LoggerInterface::class),
            baseKitContextRepository: $this->baseKitContextRepository,
        );
    }

    #[Test]
    public function updateSitebuilderRequestSuccessful(): void
    {
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString(self::TAG),
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [1337, $newPackageId = 7331],
            contractPeriod: self::INITIAL_CONTRACT_PERIOD,
        );
        $existingAccountPackageToBeKept = $this->getAccountPackageWithPackageRef(1337); // 1337 is in the update request
        $existingAccountPackageToBeRemoved = $this->getAccountPackageWithPackageRef(7330); // 7330 is not in the update request, so will be removed

        $basekitContext = BasekitContextFactory::new()->makeOne(['user_ref' => self::USER_REFERENCE]);
        $this->baseKitContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with($context)
            ->willReturn($basekitContext);

        $this->packagesApi
            ->expects(self::once())
            ->method('listUserPackages')
            ->with($basekitContext->user_ref)
            ->willReturn(
                [
                    $existingAccountPackageToBeKept,
                    $existingAccountPackageToBeRemoved,
                ],
            );

        $this->packagesApi
            ->expects(self::once())
            ->method('deleteUserPackage')
            ->with($basekitContext->user_ref, $existingAccountPackageToBeRemoved->ref);

        $this->packagesApi
            ->expects(self::once())
            ->method('addUserPackage')
            ->with(
                $basekitContext->user_ref,
                $newPackageId,
                $request->contractPeriod,
            );

        $result = $this->updateBasekitService->update($request);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function updateSitebuilderRequestSuccessfulWithContractPeriodChange(): void
    {
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString(self::TAG),
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [$packageId = 1337],
            contractPeriod: $newContractPeriod = 12,
        );

        $basekitContext = BasekitContextFactory::new()->makeOne(['user_ref' => self::USER_REFERENCE]);
        $this->baseKitContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with($context)
            ->willReturn($basekitContext);

        $this->packagesApi
            ->expects(self::once())
            ->method('listUserPackages')
            ->with($basekitContext->user_ref)
            ->willReturn(
                [
                    $accountPackage = $this->getAccountPackageWithPackageRef(1337),
                ],
            );

        $this->packagesApi
            ->expects(self::once())
            ->method('deleteUserPackage')
            ->with($basekitContext->user_ref, $accountPackage->ref);

        $this->packagesApi
            ->expects(self::once())
            ->method('addUserPackage')
            ->with(
                $basekitContext->user_ref,
                $packageId,
                $newContractPeriod,
            );

        $result = $this->updateBasekitService->update($request);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function updateSitebuilderRequestSuccessfulWithContractPeriodChangeAndUpgrade(): void
    {
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString(self::TAG),
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: $packages = [1337, 7331],
            contractPeriod: $newContractPeriod = 12,
        );

        $basekitContext = BasekitContextFactory::new()->makeOne(['user_ref' => self::USER_REFERENCE]);
        $this->baseKitContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with($context)
            ->willReturn($basekitContext);

        $this->packagesApi
            ->expects(self::once())
            ->method('listUserPackages')
            ->with($basekitContext->user_ref)
            ->willReturn(
                $accountPackages = [
                    $this->getAccountPackageWithPackageRef(1337),
                    $this->getAccountPackageWithPackageRef(7330),
                ],
            );

        $index = 0;
        $this->packagesApi
            ->expects(self::exactly(count($accountPackages)))
            ->method('deleteUserPackage')
            ->with(
                $basekitContext->user_ref,
                self::callback(function ($accountPackageReference) use ($accountPackages, &$index): bool {
                    return $accountPackageReference === $accountPackages[$index++]->ref;
                }),
            );

        $packageIndex = 0;
        $this->packagesApi
            ->expects(self::exactly(count($packages)))
            ->method('addUserPackage')
            ->with(
                $basekitContext->user_ref,
                self::callback(function ($packageId) use ($packages, &$packageIndex): bool {
                    return $packageId === $packages[$packageIndex++];
                }),
                $newContractPeriod,
            );

        $result = $this->updateBasekitService->update($request);
        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertNull($result->exception);
    }

    #[Test]
    public function updateSitebuilderRequestFailsOnContextNotFound(): void
    {
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString(self::TAG),
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [1337, 7331],
            contractPeriod: self::INITIAL_CONTRACT_PERIOD,
        );
        $request->provider = ProvisionProvider::BASEKIT;

        BasekitContextFactory::new()->makeOne(['user_ref' => self::USER_REFERENCE]);
        $this->baseKitContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with($context)
            ->willReturn(null);

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf(
                    'Failed to retrieve context "%s"',
                    $request->context,
                ),
                [
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 0,
                    LoggingContextKeys::EXCEPTION => new BasekitUserRefNotFoundForContextException($request->context),
                ],
            );

        $this->packagesApi->expects(self::never())->method('listUserPackages');

        $this->packagesApi->expects(self::never())->method('deleteUserPackage');

        $this->packagesApi->expects(self::never())->method('addUserPackage');

        $result = $this->updateBasekitService->update($request);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNotNull($result->exception);
        self::assertInstanceOf(BasekitUserRefNotFoundForContextException::class, $result->exception);
    }

    #[Test]
    public function updateSitebuilderRequestFailsOnListUserPackages(): void
    {
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString(self::TAG),
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [1337, 7331],
            contractPeriod: self::INITIAL_CONTRACT_PERIOD,
        );
        $request->provider = ProvisionProvider::BASEKIT;

        $basekitContext = BasekitContextFactory::new()->makeOne([
            'user_ref' => self::USER_REFERENCE,
            'context_uuid' => $context,
        ]);
        $this->baseKitContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with($context)
            ->willReturn($basekitContext);

        $this->packagesApi
            ->expects(self::once())
            ->method('listUserPackages')
            ->willThrowException($exception = new UnexpectedValueException('User reference not found.'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf(
                    'Failed to retrieve user packages for user %d',
                    $basekitContext->user_ref,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 0,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'basekit_user_ref' => $basekitContext->user_ref,
                    ],
                ],
            );

        $this->packagesApi->expects(self::never())->method('deleteUserPackage');

        $this->packagesApi->expects(self::never())->method('addUserPackage');

        $result = $this->updateBasekitService->update($request);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNotNull($result->exception);
        self::assertInstanceOf(UpdateBasekitException::class, $result->exception);
    }

    #[Test]
    public function updateSitebuilderRequestFailsOnDeletePackage(): void
    {
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString(self::TAG),
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [1337, 7331],
            contractPeriod: self::INITIAL_CONTRACT_PERIOD,
        );
        $request->provider = ProvisionProvider::BASEKIT;

        $basekitContext = BasekitContextFactory::new()->makeOne([
            'user_ref' => self::USER_REFERENCE,
            'context_uuid' => $context,
        ]);
        $this->baseKitContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with($context)
            ->willReturn($basekitContext);

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
            ->willThrowException($exception = new UnexpectedValueException('Account package reference not found.'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf(
                    'Failed to delete account package %d for user %d',
                    $removeAccountPackage->package->ref,
                    $basekitContext->user_ref,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 0,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'basekit_account_package_ref' => $removeAccountPackage->ref,
                        'basekit_package_ref' => $removeAccountPackage->package->ref,
                        'basekit_user_ref' => $basekitContext->user_ref,
                    ],
                ],
            );

        $this->packagesApi->expects(self::never())->method('addUserPackage');

        $result = $this->updateBasekitService->update($request);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNotNull($result->exception);
        self::assertInstanceOf(UpdateBasekitException::class, $result->exception);
    }

    #[Test]
    public function updateSitebuilderRequestFailsOnAddPackage(): void
    {
        $request = new UpdateSitebuilderRequest(
            tagUuid: Uuid::fromString(self::TAG),
            context: $context = Uuid::fromString(self::CONTEXT),
            packages: [1337, $newPackageId = 7331],
            contractPeriod: self::INITIAL_CONTRACT_PERIOD,
        );
        $request->provider = ProvisionProvider::BASEKIT;

        $basekitContext = BasekitContextFactory::new()->makeOne([
            'user_ref' => self::USER_REFERENCE,
            'context_uuid' => $context,
        ]);
        $this->baseKitContextRepository
            ->expects(self::once())
            ->method('findByContext')
            ->with($context)
            ->willReturn($basekitContext);

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
            ->willThrowException($exception = new UnexpectedValueException('Package reference not found.'));

        $this->logger
            ->expects(self::once())
            ->method('error')
            ->with(
                sprintf(
                    'Failed to add package %d for user %d',
                    $newPackageId,
                    $basekitContext->user_ref,
                ),
                [
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::SITEBUILDER,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::BASEKIT,
                    LoggingContextKeys::PROVISIONING_REQUEST_ID => 0,
                    LoggingContextKeys::PROVISIONING_CONTEXT => $context,
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'basekit_package_ref' => $newPackageId,
                        'basekit_user_ref' => $basekitContext->user_ref,
                        'basekit_subscription_period' => $request->contractPeriod,
                    ],
                ],
            );

        $result = $this->updateBasekitService->update($request);
        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertNotNull($result->exception);
        self::assertInstanceOf(UpdateBasekitException::class, $result->exception);
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
