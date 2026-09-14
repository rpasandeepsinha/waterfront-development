<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

use Exception;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

/**
 * Rule for checking that a domain extension is the same as in the domain name within the same array.
 */
class DomainExtensionRule extends AbstractValidator
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly PremiumDomainService $premiumDomainProducts,
        private readonly DomainServiceFactory $domainDriverFactory,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws Exception
     */
    protected function passes(string $attribute, mixed $value): bool
    {
        if (
            ! is_array($value)
            || ! array_key_exists('slug', $value)
            || $value['slug'] === ''
            || ! array_key_exists('domain', $value)
            || $value['domain'] === ''
        ) {
            return false; // if slug or domain is an empty string or does not exist validation should not pass.
        }

        /** @var string $domain */
        $domain = $value['domain'];

        /** @var string $slug */
        $slug = $value['slug'];

        $product = $this->productRepository->findProductBySlug($slug);

        if ($this->isPremiumDomain($domain)) {
            return $product->slug === $this->premiumDomainProducts->getPremiumDomainProductSlug($domain);
        }

        [, $tld] = explode('.', $domain, 2);

        return '.' . $tld === $product->name;
    }

    protected function message(): string
    {
        return $this->translator->translate('validation.domain_name');
    }

    /**
     * @throws Exception
     */
    private function isPremiumDomain(string $domain): bool
    {
        return $this->domainDriverFactory->defaultDriver()->check($domain)->isPremium() === true;
    }
}
