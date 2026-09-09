<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Sitebuilder\Integration;

use Illuminate\Contracts\Foundation\Application;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SandwaveIo\BaseKit\Api\Interfaces\LoginApiInterface;
use SandwaveIo\BaseKit\BaseKit;
use SandwaveIo\BaseKit\Exceptions\UnexpectedValueException;
use Tests\Factories\BasekitSitebuilderDeploymentFactory;
use Tests\Factories\ProvisioningRequestFactory;
use Tests\Factories\ProvisioningResultFactory;
use Tests\Factories\SitebuilderContextBasekitFactory;
use Tests\Factories\SitebuilderDeploymentFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Models\ProvisioningRequest;
use Waterfront\Domain\Provision\Models\ProvisioningResult;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Repositories\ProvisioningRequestRepository;
use Waterfront\Domain\Provision\Repositories\ProvisioningResultRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\ProvisionTraceabilityService;
use Waterfront\Domain\Provision\Sitebuilder\Requests\GetSitebuilderSsoRequest;
use Waterfront\Domain\Provision\Sitebuilder\Results\SitebuilderSsoResult;
use Waterfront\Infra\Basekit\Config\ConnectorConfig;
use Waterfront\Infra\Translation\Translator;

#[CoversClass(GetSitebuilderSsoRequest::class)]
#[CoversClass(ProvisionGateway::class)]
#[CoversClass(ProvisionTraceabilityService::class)]
#[CoversClass(ProvisioningResultRepository::class)]
#[CoversClass(ProvisioningRequestRepository::class)]
class GetSsoIntegrationTest extends IntegrationTestCase
{
    private const string SSO_URL = 'https://flow.basekit.com';

    private ProvisionGateway $gateway;

    private LoginApiInterface&MockInterface $mockLoginApi;

    private ProvisioningResultRepository $resultRepository;

    private ProvisioningRequestRepository $requestRepository;

    public function setUp(): void
    {
        parent::setUp();

        $username = 'user';
        $password = 'pass';
        $baseUrl = 'https://api.basekit.com';
        $ssoUrl = self::SSO_URL;

        $this->app->singleton(fn (): ConnectorConfig => new ConnectorConfig(
            baseUrl: $baseUrl,
            ssoUrl: $ssoUrl,
            username: 'user',
            password: 'pass',
            brandReference: 1337
        ));

        $mockLoginApi = self::mock(LoginApiInterface::class);
        $this->mockLoginApi = $mockLoginApi;

        $this->app->singleton(function (Application $app) use ($username, $password, $baseUrl, $mockLoginApi): BaseKit {
            $basekit = new BaseKit(
                username: $username,
                password: $password,
                baseUrl: $baseUrl,
                logger: $this->app->make(LoggerInterface::class),
            );

            $basekit->loginApi = $mockLoginApi;
            return $basekit;
        });

        $this->gateway = $this->app->make(ProvisionGateway::class);
        $this->resultRepository = $this->app->make(ProvisioningResultRepository::class);
        $this->requestRepository = $this->app->make(ProvisioningRequestRepository::class);
    }

    #[Test]
    public function getSsoRequest(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $userRef = 1337;
        $siteRef = 7331;
        $responseHash = '0123456789abcdef';

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => $userRef,
        ]);

        BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                        ->sitebuilder()
                        ->state([
                            'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                            'context_uuid' => $contextUuid,
                            'tag' => $tag,
                        ])
                        ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne(['site_ref' => $siteRef]);

        $expectedSsoUrl = sprintf('%s/login?hash=%s&siteRef=%s', self::SSO_URL, $responseHash, $siteRef);

        $this->mockLoginApi
            ->expects('autoLogin')
            ->once()
            ->with($userRef)
            ->andReturn($responseHash);

        $request = new GetSitebuilderSsoRequest(
            context: $contextUuid,
            tagUuid: $tag
        );

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::SUCCESS, $result->provisionStatus);
        self::assertInstanceOf(SitebuilderSsoResult::class, $result);
        self::assertSame($expectedSsoUrl, $result->ssoUrl);
        self::assertNull($result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_SITEBUILDER_SSO, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::SUCCESS, $savedResult->status);
    }

    #[Test]
    public function getSsoRequestValidationFails(): void
    {
        $translator = $this->app->make(Translator::class);
        $doesntExistsMessage = $translator->translate('validation.exists');

        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();

        $request = new GetSitebuilderSsoRequest(
            context: $contextUuid,
            tagUuid: $tag
        );

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $result->provisionStatus);
        self::assertInstanceOf(ProvisionResult::class, $result);
        self::assertNull($result->exception);
        self::assertNotNull($result->validationResult?->messages);

        self::assertCount(2, $result->validationResult->messages);
        self::assertArrayHasKey('context', $result->validationResult->messages);
        self::assertArrayHasKey('tag', $result->validationResult->messages);
        self::assertSame([$doesntExistsMessage], $result->validationResult->messages['context']);
        self::assertSame(['No create request with this tag in the [sitebuilder] type.'], $result->validationResult->messages['tag']);

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $savedRequest = $this->requestRepository->findById($request->requestId);
        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_SITEBUILDER_SSO, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::VALIDATION_ERROR, $savedResult->status);
    }

    #[Test]
    public function getSsoRequestFailsExternal(): void
    {
        $contextUuid = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $userRef = 1337;
        $siteRef = 7331;

        SitebuilderContextBasekitFactory::new()->createOne([
            'context_uuid' => $contextUuid,
            'user_ref' => $userRef,
        ]);

        BasekitSitebuilderDeploymentFactory::new()
            ->for(
                SitebuilderDeploymentFactory::new()
                    ->for(
                        ProvisioningRequestFactory::new()
                            ->sitebuilder()
                            ->state([
                                'request_name' => ProvisionRequestName::CREATE_SITEBUILDER,
                                'context_uuid' => $contextUuid,
                                'tag' => $tag,
                            ])
                            ->has(ProvisioningResultFactory::new()->success(), 'result'),
                        'request'
                    )
            )
            ->createOne(['site_ref' => $siteRef]);

        $expectedException = new UnexpectedValueException('Something went wrong');

        $this->mockLoginApi
            ->expects('autoLogin')
            ->once()
            ->with($userRef)
            ->andThrows($expectedException);

        $request = new GetSitebuilderSsoRequest(
            context: $contextUuid,
            tagUuid: $tag
        );

        self::assertDatabaseCount(ProvisioningResult::class, 1);
        self::assertDatabaseCount(ProvisioningRequest::class, 1);

        $result = $this->gateway->request($request);

        self::assertSame(ProvisionStatus::FAILED, $result->provisionStatus);
        self::assertInstanceOf(SitebuilderSsoResult::class, $result);
        self::assertSame($expectedException, $result->exception);

        $savedRequest = $this->requestRepository->findById($request->requestId);

        self::assertNotNull($savedRequest);
        self::assertNotNull($savedRequest->context_uuid);
        self::assertTrue($savedRequest->context_uuid->equals($contextUuid));
        self::assertSame(ProvisionType::SITEBUILDER, $savedRequest->request_type);
        self::assertSame(ProvisionRequestName::GET_SITEBUILDER_SSO, $savedRequest->request_name);
        self::assertSame(
            '[]',
            $savedRequest->request_data
        );

        $savedResult = $this->resultRepository->fetchProvisioningResults(new ProvisioningResultQueryFilters(requestUuid: $savedRequest->uuid), 1)->first();
        self::assertNotNull($savedResult);
        self::assertSame(ProvisionStatus::FAILED, $savedResult->status);
    }
}
