<?php

declare(strict_types=1);

namespace Tests\Domain\Provision\Services;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Serializer;
use Tests\TestCase;
use Waterfront\Domain\Provision\DTO\ProvisioningFilteredResult;
use Waterfront\Domain\Provision\DTO\ProvisioningResultQueryFilters;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionRequestName;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\RetryOriginNotFoundException;
use Waterfront\Domain\Provision\Factories\ProvisionSerializeFactory;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\ProvisionGateway;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\ProvisionRetryService;
use Waterfront\Domain\Provision\Sitebuilder\Requests\CreateSitebuilderRequest;

#[CoversClass(ProvisionRetryService::class)]
class ProvisionRetryServiceTest extends TestCase
{
    private ProvisionGateway&MockObject $provisionGatewayMock;

    private ProvisionSerializeFactory&MockObject $serializerFactoryMock;

    private ProvisionRetryService $provisionRetryService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provisionGatewayMock = self::createMock(ProvisionGateway::class);
        $this->serializerFactoryMock = self::createMock(ProvisionSerializeFactory::class);
        $this->provisionRetryService = new ProvisionRetryService(
            serializerFactory: $this->serializerFactoryMock,
            provisionGateway: $this->provisionGatewayMock,
        );
    }

    #[Test]
    public function retryReconstructsAndSendsProvisionRequest(): void
    {
        $retryOf = Uuid::uuid4();
        $retryRequester = Uuid::uuid4();
        $tag = Uuid::uuid4();
        $context = Uuid::uuid4();
        $originResult = $this->createOriginResult($retryOf, $tag, $context);
        $retryData = [
            'email' => 'retry@example.test',
            'name' => ProvisionRequestName::CREATE_BACKUP->value,
            'context' => Uuid::uuid4()->toString(),
            'tagUuid' => Uuid::uuid4()->toString(),
            'tag' => Uuid::uuid4()->toString(),
        ];
        $provisionRequest = new CreateSitebuilderRequest(
            domain: 'example.test',
            packages: [1],
            firstname: 'Retry',
            lastname: 'Requester',
            email: 'retry@example.test',
            contractPeriod: 12,
            context: $context,
        );
        $provisionRequest->tag = Uuid::uuid4();
        $provisionResult = new ProvisionResult($provisionRequest, ProvisionStatus::SUCCESS);

        $this->provisionGatewayMock
            ->expects(self::once())
            ->method('fetch')
            ->with(
                self::callback(
                    static fn (ProvisioningResultQueryFilters $filters): bool => (
                        $filters->requestUuid?->equals($retryOf) ?? false
                    ),
                ),
                1,
            )
            ->willReturn(new Collection([$originResult]));

        $serializerMock = self::createMock(Serializer::class);
        $serializerMock
            ->expects(self::once())
            ->method('denormalize')
            ->with([
                'email' => 'retry@example.test',
                'name' => ProvisionRequestName::CREATE_SITEBUILDER->value,
                'context' => $context->toString(),
                'tagUuid' => $tag->toString(),
                'tag' => $retryData['tag'],
            ], ProvisionRequestInterface::class)
            ->willReturn($provisionRequest);
        $this->serializerFactoryMock->expects(self::once())->method('get')->willReturn($serializerMock);

        $this->provisionGatewayMock
            ->expects(self::once())
            ->method('request')
            ->with(self::callback(
                static function (ProvisionRequestInterface $request) use (
                    $provisionRequest,
                    $tag,
                    $retryOf,
                    $retryRequester,
                ): bool {
                    self::assertSame($provisionRequest, $request);
                    self::assertSame($tag, $request->tag);
                    self::assertSame(ProvisionProvider::BASEKIT, $request->provider);
                    self::assertSame($retryOf, $request->retryOf);
                    self::assertSame($retryRequester, $request->retryRequester);
                    self::assertTrue($request->isRetry());

                    return true;
                },
            ))
            ->willReturn($provisionResult);

        $result = $this->provisionRetryService->retry($retryOf, $retryRequester, $retryData);

        self::assertSame($provisionResult, $result);
    }

    #[Test]
    public function retryThrowsWhenOriginRequestCannotBeFetched(): void
    {
        $retryOf = Uuid::uuid4();

        $this->provisionGatewayMock->expects(self::once())->method('fetch')->willReturn(new Collection());
        $this->provisionGatewayMock->expects(self::never())->method('request');
        $this->serializerFactoryMock->expects(self::never())->method('get');

        try {
            $this->provisionRetryService->retry($retryOf, Uuid::uuid4(), []);
            self::fail('Expected a retry-origin-not-found exception.');
        } catch (RetryOriginNotFoundException $exception) {
            self::assertSame($retryOf, $exception->requestUuid);
            self::assertSame(
                sprintf('Provision request [%s] could not be found.', $retryOf->toString()),
                $exception->getMessage(),
            );
        }
    }

    #[Test]
    public function retryDoesNotCallGatewayWhenRequestCannotBeDenormalized(): void
    {
        $retryOf = Uuid::uuid4();

        $this->provisionGatewayMock
            ->expects(self::once())
            ->method('fetch')
            ->willReturn(new Collection([
                $this->createOriginResult($retryOf, Uuid::uuid4(), Uuid::uuid4()),
            ]));
        $this->provisionGatewayMock->expects(self::never())->method('request');

        $serializerMock = self::createMock(Serializer::class);
        $serializerMock
            ->expects(self::once())
            ->method('denormalize')
            ->willThrowException(new NotNormalizableValueException('Invalid retry data'));
        $this->serializerFactoryMock->expects(self::once())->method('get')->willReturn($serializerMock);

        self::expectException(NotNormalizableValueException::class);

        $this->provisionRetryService->retry($retryOf, Uuid::uuid4(), []);
    }

    private function createOriginResult(
        UuidInterface $requestUuid,
        UuidInterface $tag,
        UuidInterface $context,
    ): ProvisioningFilteredResult {
        return new ProvisioningFilteredResult(
            resultId: 1,
            uuid: Uuid::uuid4(),
            tag: $tag,
            context: $context,
            response: '{}',
            status: ProvisionStatus::FAILED,
            createdAt: null,
            requestCreatedAt: null,
            requestUpdatedAt: null,
            requestData: '{}',
            requestUuid: $requestUuid,
            requestName: ProvisionRequestName::CREATE_SITEBUILDER,
            requestType: ProvisionType::SITEBUILDER,
            provider: ProvisionProvider::BASEKIT,
        );
    }
}
