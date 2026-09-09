<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Integration;

use Illuminate\Contracts\Foundation\Application;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\Api\Interfaces\UserApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Domain\AccountHolder;
use SandwaveIo\BaseKit\Domain\Capabilities;
use SandwaveIo\BaseKit\Exceptions\BaseKitRequestException;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetBasekitUserByRefRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\BasekitUserResult;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;

#[CoversClass(GetBasekitUserByRefRequest::class)]
class GetBasekitByUserRefIntegrationTest extends IntegrationTestCase
{
    private ProvisionGateway $gateway;

    /** @var UserApiInterface&MockInterface */
    private UserApiInterface $mockUserApi;

    private ProvisioningResultRepository $resultRepository;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        /** @var UserApiInterface&MockInterface $mockUserApi */
        $mockUserApi = self::mock(UserApiInterface::class);
        $this->mockUserApi = $mockUserApi;

        $this->app->singleton(fn (): ConnectorConfig => new ConnectorConfig(
            baseUrl: 'https://api.basekit.com/',
            ssoUrl: 'https://flow.basekit.com/',
            username: 'username',
            password: 'password',
            brandReference: 1337,
        ));

        $this->app->singleton(function (Application $app) use ($mockUserApi): BaseKit {
            $basekit = new BaseKit(
                username: 'username',
                password: 'password',
                baseUrl: 'https://api.basekit.com/',
                logger: $app->make(LoggerInterface::class),
            );
            $basekit->userApi = $mockUserApi;
            return $basekit;
        });

        $this->gateway = $this->app->make(ProvisionGateway::class);
        $this->resultRepository = $this->app->make(ProvisioningResultRepository::class);
        $this->requestRepository = $this->app->make(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function getBasekitByUserRefRequest(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $originRequestUuid = Uuid::uuid4();
        $userRef = 7331;
        $email = 'hobo@sandwave.io';

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => 42,
        ]);

        BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::GET_BASEKIT_USER_BY_REF_REQUEST,
                                'context_uuid' => $contextUuid,
                                'uuid' => $originRequestUuid,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        $accountHolder = new AccountHolder(
            $userRef,
            0,
            'jan',
            'man',
            'janman',
            $email,
            0,
            false,
            'NL',
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            0,
            null,
            new Capabilities(),
            null,
            null,
            0,
            '',
            false,
            0,
            null,
            null,
            '',
            null,
        );

        $this->mockUserApi
            ->expects('get')
            ->once()
            ->with($userRef)
            ->andReturn($accountHolder);

        $request = new GetBasekitUserByRefRequest(
            context: $contextUuid,
            userRef: $userRef
        );

        $request->provider = ProvisionProvider::BASEKIT;

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(BasekitUserResult::class, $result);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_BASEKIT_USER_BY_REF_REQUEST, $savedRequest->request_name);
        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"userRef": %d}',
                $userRef
            )
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);
    }

    #[Test]
    public function getBasekitByUserRefRequestFailsExternal(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $userRef = 7331;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => 42,
        ]);

        BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::GET_BASEKIT_USER_BY_REF_REQUEST,
                                'context_uuid' => $contextUuid,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne();

        $expectedException = new BaseKitRequestException('Something went wrong');

        $this->mockUserApi
            ->expects('get')
            ->once()
            ->with($userRef)
            ->andThrow($expectedException);

        $request = new GetBasekitUserByRefRequest(
            context: $contextUuid,
            userRef: $userRef
        );

        $request->provider = ProvisionProvider::BASEKIT;

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(BasekitUserResult::class, $result);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_BASEKIT_USER_BY_REF_REQUEST, $savedRequest->request_name);
        self::assertSame(
            $savedRequest->request_data,
            sprintf(
                '{"userRef": %d}',
                $userRef
            )
        );

        $savedResult = $this->resultRepository
            ->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)
            ->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);
    }
}
