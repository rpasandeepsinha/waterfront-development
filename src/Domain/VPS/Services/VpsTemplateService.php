<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Services;

use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
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
use Waterfront\Infra\CloudStackClient\DTO\Template;
use Waterfront\Support\Enums\LoggingContextKeys;

class VpsTemplateService
{
    public function __construct(
        public readonly ProductRepository $productRepository,
        public readonly AdminClientFactory $adminClientFactory,
        public readonly ProductSpecRepository $productSpecRepository,
        public readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws NonVpsOsProductException
     * @throws NoCloudstackTemplateFoundFromProduct
     */
    public function getTemplateByProduct(Product $product, Environment $environment): Template
    {
        if (! $this->productRepository->slugExistsForGroup($product->slug, ProductGroupType::CLOUDSTACK_OS)) {
            throw new NonVpsOsProductException($product);
        }

        $cloudstackTemplateSlug = $this->productSpecRepository->getStringValueOfSpecification(
            product: $product,
            specification: ProductSpecName::VPS_CLOUDSTACK_TEMPLATE_SLUG,
        );

        if ($cloudstackTemplateSlug === null) {
            $cloudstackTemplateSlug = $this->convertProductToCloudstackTemplate($product->slug);
            $this->logger->debug(
                sprintf(
                    'No Cloudstack template set as product spec, searching for template slug %s from product slug %s on environment %s',
                    $product->slug,
                    $cloudstackTemplateSlug,
                    $environment->slug,
                ),
                [
                    LoggingContextKeys::PRODUCT_SLUG => $product->slug,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::META => [
                        'template_slug_cloudstack' => $cloudstackTemplateSlug,
                        'environment_slug' => $environment->slug,
                    ],
                ],
            );
        }

        $client = $this->adminClientFactory->create($environment);
        $templates = $client->listTemplates($cloudstackTemplateSlug);
        $templates = $this->filterSshOrPasswordFromProduct($templates, $product);

        if ($templates === []) {
            throw new NoCloudstackTemplateFoundFromProduct($cloudstackTemplateSlug, $product);
        }

        $templateCount = count($templates);

        if ($templateCount > 1) {
            $this->logger->warning(
                sprintf(
                    'Found multiple templates with value [%s] for single slug [%s].',
                    $cloudstackTemplateSlug,
                    $product->slug,
                ),
                [
                    LoggingContextKeys::PRODUCT_SLUG => $product->slug,
                    LoggingContextKeys::PROVISIONING_PROVIDER => ProvisionProvider::CLOUDSTACK,
                    LoggingContextKeys::PROVISIONING_TYPE => ProvisionType::VPS,
                    LoggingContextKeys::META => [
                        'template_slug_cloudstack' => $cloudstackTemplateSlug,
                        'total_found_templates' => $templateCount,
                        'template_list' => array_map(
                            static fn (Template $template) => $template->id,
                            $templates,
                        ),
                    ],
                ],
            );
        }

        return current($templates);
    }

    public function convertProductToCloudstackTemplate(string $productSlug): string
    {
        return Str::of($productSlug)
            ->lower()
            ->replace('_', '-')
            ->rtrim('-ssh')
            ->ucfirst()
            ->toString();
    }

    /**
     * @param Template[] $templates
     *
     * @return Template[]
     */
    private function filterSshOrPasswordFromProduct(array $templates, Product $product): array
    {
        $productUsesSsh = $this->productSpecRepository->booleanSpecificationIsTrue(
            $product,
            ProductSpecName::SSH_KEY_REQUIRED,
        );

        return $productUsesSsh
            ? array_filter($templates, fn (Template $template) => $template->sshKeyEnabled)
            : array_filter($templates, fn (Template $template) => $template->passwordEnabled);
    }
}
