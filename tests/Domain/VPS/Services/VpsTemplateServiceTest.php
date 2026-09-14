<?php

declare(strict_types=1);

namespace Tests\Domain\VPS\Services;

use Generator;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Tests\Factories\CloudstackEnvironmentFactory;
use Tests\Factories\ProductFactory;
use Tests\IntegrationTestCase;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Enums\ProductSpecName;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\VPS\Exceptions\NoCloudstackTemplateFoundFromProduct;
use Waterfront\Domain\VPS\Exceptions\NonVpsOsProductException;
use Waterfront\Domain\VPS\Models\Environment;
use Waterfront\Domain\VPS\Services\AdminClientFactory;
use Waterfront\Domain\VPS\Services\VpsTemplateService;
use Waterfront\Infra\CloudStackClient\CloudStackBaseClient;
use Waterfront\Infra\CloudStackClient\CloudStackClient;
use Waterfront\Infra\CloudStackClient\DTO\TemplateTag;
use Waterfront\Infra\CloudStackClient\Serializers\CloudstackSerializerFactory;
use Waterfront\Support\Enums\LoggingContextKeys;

#[CoversClass(VpsTemplateService::class)]
class VpsTemplateServiceTest extends IntegrationTestCase
{
    private const string CLOUDSTACK_TEMPLATE_SLUG = 'Ubuntu-24.04';
    private const string WF_PRODUCT_SLUG = 'ubuntu-24.04-ssh';

    private ProductRepository&MockInterface $productRepository;

    private AdminClientFactory&MockInterface $adminClientFactory;

    private ProductSpecRepository&MockInterface $productSpecRepository;

    private LoggerInterface&MockInterface $logger;

    private VpsTemplateService $service;

    private Product $product;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productRepository = self::mock(ProductRepository::class);
        $this->adminClientFactory = self::mock(AdminClientFactory::class);
        $this->productSpecRepository = self::mock(ProductSpecRepository::class);
        $this->logger = self::mock(LoggerInterface::class);

        $this->service = new VpsTemplateService(
            $this->productRepository,
            $this->adminClientFactory,
            $this->productSpecRepository,
            $this->logger,
        );

        $this->environment = new CloudstackEnvironmentFactory()->createOne();
        $this->product = new ProductFactory()
            ->ubuntu()
            ->createOne(['slug' => self::WF_PRODUCT_SLUG]);
    }

    #[Test]
    public function throwsExceptionIfProductIsNotVps(): void
    {
        $this->productRepository
            ->shouldReceive('slugExistsForGroup')
            ->with($this->product->slug, ProductGroupType::CLOUDSTACK_OS)
            ->once()
            ->andReturn(false);

        $this->expectException(NonVpsOsProductException::class);

        $this->service->getTemplateByProduct($this->product, $this->environment);
    }

    #[Test]
    public function throwsExceptionIfNoTemplateFound(): void
    {
        $this->productRepository
            ->shouldReceive('slugExistsForGroup')
            ->once()
            ->with($this->product->slug, ProductGroupType::CLOUDSTACK_OS)
            ->andReturn(true);

        $clientMock = self::mock(CloudStackClient::class);
        $clientMock->shouldReceive('listTemplates')->once()->andReturn([]);

        $this->adminClientFactory->shouldReceive('create')->once()->andReturn($clientMock);

        $this->productSpecRepository
            ->shouldReceive('getStringValueOfSpecification')
            ->once()
            ->with($this->product, ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG)
            ->andReturn(self::CLOUDSTACK_TEMPLATE_SLUG);

        $this->productSpecRepository
            ->shouldReceive('booleanSpecificationIsTrue')
            ->once()
            ->with($this->product, ProductSpecName::SSH_KEY_REQUIRED)
            ->andReturn(true);

        $this->expectException(NoCloudstackTemplateFoundFromProduct::class);

        $this->service->getTemplateByProduct($this->product, $this->environment);
    }

    #[Test]
    public function nonSshTemplatesFilteredIfProductSpecIsOn(): void
    {
        $this->productRepository
            ->shouldReceive('slugExistsForGroup')
            ->once()
            ->with($this->product->slug, ProductGroupType::CLOUDSTACK_OS)
            ->andReturn(true);

        $mockBaseClient = self::mock(CloudStackBaseClient::class);

        $this->adminClientFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn(new CloudStackClient($mockBaseClient, CloudstackSerializerFactory::get()));

        $this->productSpecRepository
            ->shouldReceive('getStringValueOfSpecification')
            ->once()
            ->with($this->product, ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG)
            ->andReturn(self::CLOUDSTACK_TEMPLATE_SLUG);

        $templateResponse = json_decode(
            json: (string) file_get_contents(__DIR__
            . '/../data/templates/list-templates-ubuntu-with-and-without-ssh.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $mockBaseClient
            ->shouldReceive('execute')
            ->once()
            ->with(
                'listTemplates',
                [
                    'templatefilter' => 'featured',
                    'listall' => true,
                    'tags' => [
                        [
                            'key' => 'template_slug',
                            'value' => self::CLOUDSTACK_TEMPLATE_SLUG,
                        ],
                    ],
                ],
            )
            ->andReturn($templateResponse);

        $this->productSpecRepository
            ->shouldReceive('booleanSpecificationIsTrue')
            ->once()
            ->with($this->product, ProductSpecName::SSH_KEY_REQUIRED)
            ->andReturn(true);

        $template = $this->service->getTemplateByProduct($this->product, $this->environment);

        $templateTag = current(array_filter(
            $template->tags,
            fn (TemplateTag $templateTag) => $templateTag->key === 'template_slug',
        ));

        self::assertTrue($template->sshKeyEnabled);
        self::assertFalse($template->passwordEnabled);
        self::assertInstanceOf(TemplateTag::class, $templateTag);
        self::assertSame(self::CLOUDSTACK_TEMPLATE_SLUG, $templateTag->value);
    }

    #[Test]
    public function generateTemplateSlugIfNoProductSpec(): void
    {
        $this->productRepository
            ->shouldReceive('slugExistsForGroup')
            ->once()
            ->with($this->product->slug, ProductGroupType::CLOUDSTACK_OS)
            ->andReturn(true);

        $this->productSpecRepository
            ->shouldReceive('getStringValueOfSpecification')
            ->once()
            ->with($this->product, ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG)
            ->andReturn(null);

        $this->logger
            ->shouldReceive('debug')
            ->once()
            ->with(
                sprintf(
                    'No Cloudstack template set as product spec, searching for template slug %s from product slug %s on environment %s',
                    $this->product->slug,
                    self::CLOUDSTACK_TEMPLATE_SLUG,
                    $this->environment->slug,
                ),
                [
                    LoggingContextKeys::PRODUCT_SLUG => $this->product->slug,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::META => [
                        'template_slug_cloudstack' => self::CLOUDSTACK_TEMPLATE_SLUG,
                        'environment_slug' => $this->environment->slug,
                    ],
                ],
            );

        $mockBaseClient = self::mock(CloudStackBaseClient::class);

        $this->adminClientFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn(new CloudStackClient($mockBaseClient, CloudstackSerializerFactory::get()));

        $templateResponse = json_decode(
            json: (string) file_get_contents(__DIR__
            . '/../data/templates/list-templates-ubuntu-with-and-without-ssh.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $mockBaseClient
            ->shouldReceive('execute')
            ->once()
            ->with(
                'listTemplates',
                [
                    'templatefilter' => 'featured',
                    'listall' => true,
                    'tags' => [
                        [
                            'key' => 'template_slug',
                            'value' => self::CLOUDSTACK_TEMPLATE_SLUG,
                        ],
                    ],
                ],
            )
            ->andReturn($templateResponse);

        $this->productSpecRepository
            ->shouldReceive('booleanSpecificationIsTrue')
            ->once()
            ->with($this->product, ProductSpecName::SSH_KEY_REQUIRED)
            ->andReturn(true);

        $template = $this->service->getTemplateByProduct($this->product, $this->environment);

        $templateTag = current(array_filter(
            $template->tags,
            fn (TemplateTag $templateTag) => $templateTag->key === 'template_slug',
        ));

        self::assertTrue($template->sshKeyEnabled);
        self::assertFalse($template->passwordEnabled);
        self::assertInstanceOf(TemplateTag::class, $templateTag);
        self::assertSame(self::CLOUDSTACK_TEMPLATE_SLUG, $templateTag->value);
    }

    #[Test]
    public function sshTemplatesFilteredIfProductSpecIsOff(): void
    {
        $this->productRepository
            ->shouldReceive('slugExistsForGroup')
            ->once()
            ->with($this->product->slug, ProductGroupType::CLOUDSTACK_OS)
            ->andReturn(true);

        $mockBaseClient = self::mock(CloudStackBaseClient::class);

        $this->adminClientFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn(new CloudStackClient($mockBaseClient, CloudstackSerializerFactory::get()));

        $this->productSpecRepository
            ->shouldReceive('getStringValueOfSpecification')
            ->once()
            ->with($this->product, ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG)
            ->andReturn(self::CLOUDSTACK_TEMPLATE_SLUG);

        $templateResponse = json_decode(
            json: (string) file_get_contents(__DIR__
            . '/../data/templates/list-templates-ubuntu-with-and-without-ssh.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $mockBaseClient
            ->shouldReceive('execute')
            ->once()
            ->with(
                'listTemplates',
                [
                    'templatefilter' => 'featured',
                    'listall' => true,
                    'tags' => [
                        [
                            'key' => 'template_slug',
                            'value' => self::CLOUDSTACK_TEMPLATE_SLUG,
                        ],
                    ],
                ],
            )
            ->andReturn($templateResponse);

        $this->productSpecRepository
            ->shouldReceive('booleanSpecificationIsTrue')
            ->once()
            ->with($this->product, ProductSpecName::SSH_KEY_REQUIRED)
            ->andReturn(false);

        $template = $this->service->getTemplateByProduct($this->product, $this->environment);

        $templateTag = current(array_filter(
            $template->tags,
            fn (TemplateTag $templateTag) => $templateTag->key === 'template_slug',
        ));

        self::assertFalse($template->sshKeyEnabled);
        self::assertTrue($template->passwordEnabled);
        self::assertInstanceOf(TemplateTag::class, $templateTag);
        self::assertSame(self::CLOUDSTACK_TEMPLATE_SLUG, $templateTag->value);
    }

    #[Test]
    public function logsWarningWhenMultipleTemplatesFound(): void
    {
        $this->productRepository
            ->shouldReceive('slugExistsForGroup')
            ->once()
            ->with($this->product->slug, ProductGroupType::CLOUDSTACK_OS)
            ->andReturn(true);

        $this->productSpecRepository
            ->shouldReceive('getStringValueOfSpecification')
            ->once()
            ->with($this->product, ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG)
            ->andReturn(self::CLOUDSTACK_TEMPLATE_SLUG);

        $mockBaseClient = self::mock(CloudStackBaseClient::class);

        $this->adminClientFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn(new CloudStackClient($mockBaseClient, CloudstackSerializerFactory::get()));

        $templateResponse = json_decode(
            json: (string) file_get_contents(__DIR__
            . '/../data/templates/list-templates-ubuntu-with-duplicate-tags.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $mockBaseClient
            ->shouldReceive('execute')
            ->once()
            ->with(
                'listTemplates',
                [
                    'templatefilter' => 'featured',
                    'listall' => true,
                    'tags' => [
                        [
                            'key' => 'template_slug',
                            'value' => self::CLOUDSTACK_TEMPLATE_SLUG,
                        ],
                    ],
                ],
            )
            ->andReturn($templateResponse);

        $this->productSpecRepository
            ->shouldReceive('booleanSpecificationIsTrue')
            ->once()
            ->with($this->product, ProductSpecName::SSH_KEY_REQUIRED)
            ->andReturn(true);

        $this->logger
            ->shouldReceive('warning')
            ->once()
            ->with(
                sprintf(
                    'Found multiple templates with value [%s] for single slug [%s].',
                    self::CLOUDSTACK_TEMPLATE_SLUG,
                    self::WF_PRODUCT_SLUG,
                ),
                [
                    LoggingContextKeys::PRODUCT_SLUG => self::WF_PRODUCT_SLUG,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::META => [
                        'template_slug_cloudstack' => self::CLOUDSTACK_TEMPLATE_SLUG,
                        'total_found_templates' => 3, // 3 templates with same tag in the json test file.
                        'template_list' => [
                            '9948bc15-31ab-4762-939e-7e02d082126a', // template ids from the json test file.
                            '4fd4c8e9-9139-4c54-aad6-6044c5f4c082',
                            'a008d6fe-39a8-4d28-bfd7-e77fff177618',
                        ],
                    ],
                ],
            );

        $this->service->getTemplateByProduct($this->product, $this->environment);
    }

    #[Test]
    public function returnsFirstTemplateIfOnlyOneFound(): void
    {
        $this->productRepository
            ->shouldReceive('slugExistsForGroup')
            ->once()
            ->with($this->product->slug, ProductGroupType::CLOUDSTACK_OS)
            ->andReturn(true);

        $this->productSpecRepository
            ->shouldReceive('getStringValueOfSpecification')
            ->once()
            ->with($this->product, ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG)
            ->andReturn(self::CLOUDSTACK_TEMPLATE_SLUG);

        $mockBaseClient = self::mock(CloudStackBaseClient::class);

        $this->adminClientFactory
            ->shouldReceive('create')
            ->once()
            ->andReturn(new CloudStackClient($mockBaseClient, CloudstackSerializerFactory::get()));

        $templateResponse = json_decode(
            json: (string) file_get_contents(__DIR__ . '/../data/templates/list-templates-single-item.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $mockBaseClient
            ->shouldReceive('execute')
            ->once()
            ->with(
                'listTemplates',
                [
                    'templatefilter' => 'featured',
                    'listall' => true,
                    'tags' => [
                        [
                            'key' => 'template_slug',
                            'value' => self::CLOUDSTACK_TEMPLATE_SLUG,
                        ],
                    ],
                ],
            )
            ->andReturn($templateResponse);

        $this->productSpecRepository
            ->shouldReceive('booleanSpecificationIsTrue')
            ->once()
            ->with($this->product, ProductSpecName::SSH_KEY_REQUIRED)
            ->andReturn(false);

        $template = $this->service->getTemplateByProduct($this->product, $this->environment);

        $templateTag = current(array_filter(
            $template->tags,
            fn (TemplateTag $templateTag) => $templateTag->key === 'template_slug',
        ));

        self::assertTrue($template->passwordEnabled);
        self::assertFalse($template->sshKeyEnabled);
        self::assertInstanceOf(TemplateTag::class, $templateTag);
        self::assertSame(self::CLOUDSTACK_TEMPLATE_SLUG, $templateTag->value);
    }

    #[DataProvider('vpsTemplateSlugProvider')]
    #[Test]
    public function createCloudstackTemplateSlugFromProduct(string $wfSlug, string $expectedCsSlug): void
    {
        $csSlug = $this->service->convertProductToCloudstackTemplate($wfSlug);
        self::assertSame($expectedCsSlug, $csSlug);
    }

    /**
     * @return Generator<string[]>
     */
    public static function vpsTemplateSlugProvider(): Generator
    {
        yield ['ubuntu-24.04-ssh', 'Ubuntu-24.04'];
        yield ['ubuntu-24.04', 'Ubuntu-24.04'];
        yield ['ubuntu_24.04_ssh', 'Ubuntu-24.04'];
        yield ['ubuntu_24.04', 'Ubuntu-24.04'];
        yield ['ALMAlinux-9-ssh', 'Almalinux-9'];
        yield ['deBiAn-12', 'Debian-12'];
    }
}
