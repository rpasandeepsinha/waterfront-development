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
use RealtimeRegister\RealtimeRegister;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\Infra\RtrClient\Helpers\MockedClientFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Domains\Actions\NovaFetchDomainAndContactFromRtr;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\RtrClient\Services\RtrService;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaFetchDomainAndContactFromRtr::class)]
class NovaFetchDomainAndContactFromRtrTest extends IntegrationTestCase
{
    #[Test]
    public function fetchDomainAndContactFromRtr(): void
    {
        Http::fake();

        $incomingOutgoingResponse = json_encode(
            include __DIR__ . '/data/domain_details_valid.php',
            JSON_THROW_ON_ERROR,
        );
        $contactResponse = json_encode(include __DIR__ . '/data/contact_valid.php', JSON_THROW_ON_ERROR);
        $financialResponse = json_encode(include __DIR__ . '/data/contact_valid_financial.php', JSON_THROW_ON_ERROR);

        $sdk = MockedClientFactory::makeSdkWithMultipleReponses([
            // main domain fetch
            new Response(
                status: 200,
                body: $incomingOutgoingResponse,
            ),
            // 2 local db entries fetched from rtr
            new Response(
                status: 200,
                body: $contactResponse,
            ),
            new Response(
                status: 200,
                body: $financialResponse,
            ),
            // registrant faked
            new Response(
                status: 200,
                body: $financialResponse,
            ),
            // 3 times from contact array
            new Response(
                status: 200,
                body: $financialResponse,
            ),
            new Response(
                status: 200,
                body: $financialResponse,
            ),
            new Response(
                status: 200,
                body: $financialResponse,
            ),
        ]);

        $rtrService = self::resolve(RtrService::class);
        $rtrService = $rtrService->setClient($sdk);

        $this->app->bind(RealtimeRegister::class, fn () => $sdk);
        $this->app->bind(RtrService::class, fn (): RtrService => $rtrService);

        $customer = new CustomerFactory()
            ->withAddress()
            ->createOne([
                'phone_country_code' => '31',
                'phone_area_code' => '6',
                'phone_subscriber_number' => '12345678',
            ]);
        $domainProvider = ProviderFactory::new()->createOne([
            'type' => ProviderType::DOMAIN,
            'slug' => ProviderSlug::REALTIME_REGISTER,
            'enabled' => true,
            'default' => true,
        ]);

        $handle = '25B-BUNAME-handle_normal';
        $handle2 = '25B-BUNAME-handle_normal2';

        $domainContact = DomainContactFactory::new()->for($customer)->createOne();

        $domainContact->providers()->attach(
            $domainProvider,
            [
                'external_contact' => $handle,
            ],
        );

        $domainContact->providers()->attach(
            $domainProvider,
            [
                'external_contact' => $handle2,
            ],
        );

        $product = ProductFactory::new()->nlDomain()->createOne();

        $subscription = SubscriptionFactory::new()->for($customer)->for($product)->createOne();

        $domainDeployment = DomainDeploymentFactory::new()
            ->for($domainContact, 'contactOwner')
            ->for($subscription)
            ->for($domainProvider, 'provider')
            ->createOne();

        $fields = new ActionFields(new Collection([]), new Collection([]));
        $models = new Collection([$domainDeployment]);

        $novaFetchDomainAndContactFromRtr = new NovaFetchDomainAndContactFromRtr(
            translator: self::resolve(TranslatorInterface::class),
            domainService: self::resolve(DomainService::class),
            businessUnitRepository: self::resolve(DomainProviderBusinessUnitRepository::class),
        );

        $result = $novaFetchDomainAndContactFromRtr->handle($fields, $models);

        self::assertInstanceOf(ActionResponse::class, $result);
        $modal = $result['modal'];
        self::assertInstanceOf(Modal::class, $modal);
        self::assertIsString($modal->payload['code']);
        self::assertSame('Fetched domain details and handles from Rtr with response:', $modal->payload['title']);
        self::assertSame(
            include __DIR__ . '/data/domain_and_contact_fetch_response.php',
            json_decode($modal->payload['code'], true),
        );
    }
}
