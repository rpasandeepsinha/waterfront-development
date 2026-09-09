<?php

declare(strict_types=1);

namespace Tests\Apps\API\Waterfront\Lighthouse;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Lighthouse\Helper\RequestHelper;
use Waterfront\Domain\Lighthouse\Serialize\IdentitySerializerFactory;
use Waterfront\Domain\Lighthouse\Services\LighthouseApiService;
use Waterfront\Infra\Configuration\ConfigurationInterface;

#[CoversClass(LighthouseApiService::class)]
class LighthouseApiServiceTest extends IntegrationTestCase
{
    private LighthouseApiService $lighthouseApiService;

    public function setUp(): void
    {
        parent::setUp();

        // Creating the instance manually because lighthouse is mocked by default
        $this->lighthouseApiService = new LighthouseApiService(
            self::resolve(ConfigurationInterface::class),
            self::resolve(RequestHelper::class),
            self::resolve(IdentitySerializerFactory::class),
        );

        Config::set('app.hydra.client_id', 'test');
        Config::set('app.hydra.secret', 'test');
    }

    #[Test]
    public function getIdentities(): void
    {
        $identity = (string) file_get_contents(__DIR__ . '/../Customers/data/kratosIdentity.json');
        Http::fake([
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/search?customerNumber=1' => Http::response((string) json_encode([json_decode($identity)])),
            'https://api.lighthouse.sandwaveio.dev/kratos/identities/john@example.dev' => Http::response($identity),
        ]);

        $resultByCustomerNumber = $this->lighthouseApiService->getKratosIdentitiesByCustomerNumber(1);
        $resultByIdentifier = $this->lighthouseApiService->getKratosIdentityByIdentifier('john@example.dev');

        self::assertEquals($resultByCustomerNumber[0], $resultByIdentifier);
        self::assertSame('8de11411-6e39-4a5a-9634-8393c36d63a2', $resultByIdentifier->id);
        self::assertSame('8de11411-6e39-4a5a-9634-8393c36d63a2', $resultByCustomerNumber[0]->id);
    }
}
