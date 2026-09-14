<?php

declare(strict_types=1);

namespace Tests\Infra\RtrClient\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use RealtimeRegister\RealtimeRegister;
use RealtimeRegister\Support\AuthorizedClient;
use RealtimeRegister\Support\RealtimeRegisterResponse;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProductGroupFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Domains\Exceptions\ContactValidationRequiredException;
use Waterfront\Infra\RtrClient\Services\RtrService;

#[CoversClass(RtrService::class)]
class RtrServiceEnsureContactValidatedForDomainTest extends IntegrationTestCase
{
    private const string HANDLE = '1120154-FYEOMedicalGrou-OvXNJiRMn4f72URB';
    private const string DOMAIN_NU = 'example.nu';
    private const string DOMAIN_NL = 'example.nl';

    private AuthorizedClient&MockObject $mockRtr;

    protected function setUp(): void
    {
        parent::setUp();

        $domainGroup = new ProductGroupFactory()->extension()->createOne();
        new ProductFactory()
            ->nlDomain()
            ->for($domainGroup)
            ->createOne();

        $this->mockRtr = self::createMock(AuthorizedClient::class);

        $this->app
            ->when(RtrService::class)
            ->needs(RealtimeRegister::class)
            ->give(function () {
                $externalRtr = new RealtimeRegister('api-key');
                $externalRtr->setClient($this->mockRtr);

                return $externalRtr;
            });
    }

    #[Test]
    public function contactAlreadyValidatedDoesNotThrow(): void
    {
        $tldResponse = $this->buildTldInfoResponse('IisNu');
        $contactResponse = $this->buildContactResponse(self::HANDLE, ['IisNu']);

        $this->mockRtr
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($tldResponse, $contactResponse) {
                if (str_contains($url, 'v2/tlds/')) {
                    return new RealtimeRegisterResponse($tldResponse, [], 200);
                }

                return new RealtimeRegisterResponse($contactResponse, [], 200);
            });

        $this->mockRtr->expects(self::never())->method('post');

        $rtrService = self::resolve(RtrService::class);
        $rtrService->ensureContactValidatedForDomain(self::DOMAIN_NU, self::HANDLE);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function contactNotValidatedTriggersValidationAndThrows(): void
    {
        $tldResponse = $this->buildTldInfoResponse('IisNu');
        $contactResponse = $this->buildContactResponse(self::HANDLE, []);

        $this->mockRtr
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($tldResponse, $contactResponse) {
                if (str_contains($url, 'v2/tlds/')) {
                    return new RealtimeRegisterResponse($tldResponse, [], 200);
                }

                return new RealtimeRegisterResponse($contactResponse, [], 200);
            });

        $this->mockRtr
            ->expects(self::once())
            ->method('post')
            ->willReturn(new RealtimeRegisterResponse('{}', [], 200));

        self::expectException(ContactValidationRequiredException::class);

        $rtrService = self::resolve(RtrService::class);
        $rtrService->ensureContactValidatedForDomain(self::DOMAIN_NU, self::HANDLE);
    }

    #[Test]
    public function noValidationCategoryRequiredDoesNotThrow(): void
    {
        $tldResponse = $this->buildTldInfoResponse(null);

        $this->mockRtr
            ->expects(self::once())
            ->method('get')
            ->with(self::stringContains('v2/tlds/'))
            ->willReturn(new RealtimeRegisterResponse($tldResponse, [], 200));

        $this->mockRtr->expects(self::never())->method('post');

        $rtrService = self::resolve(RtrService::class);
        $rtrService->ensureContactValidatedForDomain(self::DOMAIN_NL, self::HANDLE);

        self::addToAssertionCount(1);
    }

    #[Test]
    public function contactValidatedForDifferentCategoryStillThrows(): void
    {
        $tldResponse = $this->buildTldInfoResponse('IisNu');
        $contactResponse = $this->buildContactResponse(self::HANDLE, ['General']);

        $this->mockRtr
            ->expects(self::exactly(2))
            ->method('get')
            ->willReturnCallback(function (string $url) use ($tldResponse, $contactResponse) {
                if (str_contains($url, 'v2/tlds/')) {
                    return new RealtimeRegisterResponse($tldResponse, [], 200);
                }

                return new RealtimeRegisterResponse($contactResponse, [], 200);
            });

        $this->mockRtr
            ->expects(self::once())
            ->method('post')
            ->willReturn(new RealtimeRegisterResponse('{}', [], 200));

        self::expectException(ContactValidationRequiredException::class);

        $rtrService = self::resolve(RtrService::class);
        $rtrService->ensureContactValidatedForDomain(self::DOMAIN_NU, self::HANDLE);
    }

    private function buildTldInfoResponse(?string $validationCategory): string
    {
        /** @var array{metadata: array<string, mixed>} $data */
        $data = json_decode(
            (string) file_get_contents(__DIR__ . '/../data/tld_metadata_nu.json'),
            true,
        );
        $data['metadata']['validationCategory'] = $validationCategory;

        return (string) json_encode($data);
    }

    /** @param array<int, string> $validatedCategories */
    private function buildContactResponse(string $handle, array $validatedCategories): string
    {
        /** @var array<string, mixed> $data */
        $data = json_decode(
            (string) file_get_contents(__DIR__ . '/../data/contact_validated.json'),
            true,
        );
        $data['handle'] = $handle;
        $data['validations'] = array_map(fn (string $category) => [
            'category' => $category,
            'version' => 1,
            'validatedOn' => '2026-01-01T00:00:00Z',
        ], $validatedCategories);

        return (string) json_encode($data);
    }
}
