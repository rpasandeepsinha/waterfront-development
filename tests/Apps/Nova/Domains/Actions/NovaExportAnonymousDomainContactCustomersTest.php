<?php

declare(strict_types=1);

namespace Tests\Apps\Nova\Domains\Actions;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\Responses\DownloadFile;
use Laravel\Nova\Fields\ActionFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\Factories\CustomerFactory;
use Tests\Factories\DomainContactAnonymousHandleFactory;
use Tests\Factories\DomainContactFactory;
use Tests\Factories\DomainDeploymentFactory;
use Tests\Factories\ProductFactory;
use Tests\Factories\ProviderFactory;
use Tests\Factories\SubscriptionFactory;
use Tests\IntegrationTestCase;
use Waterfront\Apps\Nova\Domains\Actions\NovaExportAnonymousDomainContactDomains;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Enums\ProviderType;
use Waterfront\Infra\Translation\TranslatorInterface;

#[CoversClass(NovaExportAnonymousDomainContactDomains::class)]
class NovaExportAnonymousDomainContactCustomersTest extends IntegrationTestCase
{
    #[Test]
    public function exportAnonymousDomainContactCustomers(): void
    {
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
        $anonymousHandle = '25B-BUNAME-anonymous';

        DomainContactAnonymousHandleFactory::new()->create(['handle' => $anonymousHandle]);

        $anonymizedContact = DomainContactFactory::new()->for($customer)->createOne();
        $anonymizedContact->providers()->attach(
            $domainProvider,
            [
                'external_contact' => $anonymousHandle,
            ],
        );

        $product = ProductFactory::new()->nlDomain()->createOne();

        $subscription = SubscriptionFactory::new()->for($customer)->for($product)->createOne();
        $subscription2 = SubscriptionFactory::new()->for($customer)->for($product)->createOne();

        DomainDeploymentFactory::new()
            ->for($anonymizedContact, 'contactOwner')
            ->for($subscription)
            ->for($domainProvider, 'provider')
            ->createOne();
        DomainDeploymentFactory::new()
            ->for($anonymizedContact, 'contactOwner')
            ->for($subscription2)
            ->for($domainProvider, 'provider')
            ->createOne();

        $expectedName = 'customer-anonymous-domain-contacts.csv';
        $expectedPutPath = 'exports/customer-anonymous-domain-contacts.csv';
        $expectedDownloadPath = '/export/customer-anonymous-domain-contacts.csv';

        $fields = new ActionFields(new Collection([]), new Collection([]));
        $models = new Collection([]);

        $fileSystem = self::createMock(Filesystem::class);
        $fileSystem
            ->expects(self::once())
            ->method('put')
            ->with($expectedPutPath, self::callback(function (string $data) use ($subscription, $subscription2) {
                $domains = explode("\n", $data);
                self::assertCount(2, $domains);
                self::assertEqualsCanonicalizing([$subscription->domain, $subscription2->domain], $domains);

                return true;
            }))
            ->willReturn(null);

        $fileSystemManager = self::createStub(FilesystemManager::class);
        $fileSystemManager->method('disk')->willReturn($fileSystem);

        $novaExportAnonymousDomainContactCustomers = new NovaExportAnonymousDomainContactDomains(
            $fileSystemManager,
            self::resolve(TranslatorInterface::class),
        );

        $result = $novaExportAnonymousDomainContactCustomers->handle($fields, $models);

        self::assertInstanceOf(ActionResponse::class, $result);
        $download = $result['download'];
        self::assertInstanceOf(DownloadFile::class, $download);
        self::assertSame($expectedName, $download->name);
        self::assertSame($expectedDownloadPath, $download->url);
    }
}
