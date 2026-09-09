<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Domains\Actions;

use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\Modal;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactAnonymousHandleFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\ProviderFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Domains\Actions\NovaFetchAnonymousDomainContactFromRtr;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaFetchAnonymousDomainContactFromRtr::class)]
class NovaFetchAnonymousDomainContactFromRtrTest extends IntegrationTestCase
{
    #[Test]
    public function fetchAnonymousDomainContactFromRtr(): void
    {
        Http::fake();

        $contactResponse = json_encode(include __DIR__ . '/data/contact_valid.php', JSON_THROW_ON_ERROR);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            new Response(
                status: 200,
                body: $contactResponse
            ),
        ]);

        $rtrService = self::resolve(RtrService::class);
        $rtrService = $rtrService->setClient($sdk);

        $customer = new CustomerFactory()->withAddress()->createOne([
            'phone_country_code' => '31',
            'phone_area_code' => '6',
            'phone_subscriber_number' => '12345678',
        ]);
        $domainProvider = ProviderFactory::new()->createOne(['type' => ProviderType::DOMAIN, 'slug' => ProviderSlug::REALTIME_REGISTER, 'enabled' => true, 'default' => true]);

        $anonymousHandle = '25B-BUNAME-anonymous';

        $anonymousHandleModel = DomainContactAnonymousHandleFactory::new()
            ->createOne(['handle' => $anonymousHandle]);

        $domainContact = DomainContactFactory::new()
            ->for($customer)
            ->createOne();

        $domainContact->providers()->attach(
            $domainProvider,
            [
                'external_contact' => $anonymousHandle,
            ]
        );

        $fields = new ActionFields(new Collection([]), new Collection([]));
        $models = new Collection([$anonymousHandleModel]);

        $novaFetchAnonymousContactFromRtr = new NovaFetchAnonymousDomainContactFromRtr(
            translator: self::resolve(TranslatorInterface::class),
            rtrService: $rtrService
        );

        $result = $novaFetchAnonymousContactFromRtr->handle($fields, $models);

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertIsString($modal->payload['code']);
        self::assertSame('Fetched anonymous domain contact with handle {25B-BUNAME-anonymous} from Rtr with response:', $modal->payload['title']);
        self::assertSame(include __DIR__ . '/data/anonymous_fetch_response.php', json_decode($modal->payload['code'], true));
    }
}
