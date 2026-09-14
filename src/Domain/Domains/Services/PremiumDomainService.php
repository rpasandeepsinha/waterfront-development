<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Services;

use Carbon\CarbonImmutable;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Domains\DTO\CheckResult;
use Waterfront\Domain\Domains\Mailers\PremiumDomainPriceRequested;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Pricing\Enums\PriceComponentType;
use Waterfront\Domain\Pricing\Models\ProductPriceComponent;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductGroup;
use Waterfront\Domain\Products\Models\ProductPeriod;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class PremiumDomainService
{
    private const int DEFAULT_PERIOD = 12;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ConfigurationInterface $configuration,
    ) {
    }

    public function requestPremiumPricePerEmail(string $domainName, string $customerEmailAddress): void
    {
        $recipient = new Recipient(
            'Support',
            $this->configuration->getAsString('bu.support_email'),
            Uuid::fromString($this->configuration->getAsString('auth.console_identity_uuid')),
        );
        $this->mailer->send(
            recipients: [$recipient],
            template: new PremiumDomainPriceRequested(
                $domainName,
                $customerEmailAddress,
            ),
        );
    }

    public function createProductPriceForPremiumDomain(CheckResult $checkResult, int $marginPercent): Product
    {
        // You might instinctively think margin cannot be >100%, but in case of margins that would be a valid value.
        if ($marginPercent < 0) {
            throw new InvalidArgumentException('Margin for premium domains must be larger than 0%');
        }

        if ($checkResult->isPremium() !== true) {
            throw new InvalidArgumentException('Domain must be a premium domain before a product can be generated.');
        }

        $price = $checkResult->getPrice();
        if ($price === null || $price <= 0) {
            throw new InvalidArgumentException(
                'Premium domain must have a positive price before a product can be generated.',
            );
        }

        if ($this->doesProductExistForPremiumDomain($checkResult->getDomain())) {
            throw new InvalidArgumentException('Premium domain already has a price.');
        }

        $price *= 1 + ($marginPercent / 100);
        $price = (int) round($price);
        assert($price > 0);
        $productGroup = ProductGroup::where('slug', ProductGroupType::EXTENSION)->firstOrFail();

        $product = new Product();
        $product->product_group_id = $productGroup->id;
        $product->name = $this->getPremiumDomainProductName($checkResult->getDomain());
        $product->slug = $this->getPremiumDomainProductSlug($checkResult->getDomain());
        $product->description = $this->getPremiumDomainProductName($checkResult->getDomain());
        $product->orderable = true;
        $product->weight = 1;
        $product->save();

        $productPeriod = new ProductPeriod();
        $productPeriod->product_id = $product->id;
        $productPeriod->billing_period = self::DEFAULT_PERIOD;
        $productPeriod->contract_period = self::DEFAULT_PERIOD;
        $productPeriod->save();

        $productPrice = new ProductPriceComponent();
        $productPrice->product_id = $product->id;
        $productPrice->type = PriceComponentType::REGISTRATION;
        $productPrice->price = $price;
        $productPrice->contract_period = self::DEFAULT_PERIOD;
        $productPrice->billing_period = self::DEFAULT_PERIOD;
        $productPrice->orderable = true;
        $productPrice->starts_at = CarbonImmutable::now();
        $productPrice->save();

        return $product;
    }

    public function doesProductExistForPremiumDomain(string $domain): bool
    {
        $slug = $this->getPremiumDomainProductSlug($domain);

        return Product::where('slug', $slug)->exists();
    }

    public function getPremiumDomainProductSlug(string $domainName): string
    {
        if (! str_contains($domainName, '.')) {
            return sprintf('extension_premium_%s', $domainName);
        }

        [$domain, $tld] = explode('.', $domainName, 2);

        return sprintf('extension_premium_%s', $domain . '_' . $tld);
    }

    public function getPremiumDomainProductName(string $domainName): string
    {
        return sprintf('Premium Domain (%s)', $domainName);
    }

    public function getPremiumDomainRtrProduct(string $domainName): string
    {
        [, $tld] = explode('.', $domainName, 2);

        return sprintf('domain_%s_premium', $tld);
    }
}
