<?php

declare(strict_types=1);

namespace Waterfront\Domain\Cart\Validators\Rules;

use Closure;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Infra\Translation\TranslatorInterface;

class DomainRules
{
    public function __construct(
        private readonly DomainExtensionRule $domainExtensionRule,
        private readonly DomainIsUnclaimedRule $domainIsUnclaimedRule,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function getDomainRules(): array
    {
        return [
            'subscriptions.extension.*' => $this->domainExtensionRule,
            'subscriptions.extension.*.transfer_secret' => 'required_if:extension.*.status,transfer',
            'subscriptions.extension.*.domain' => [
                'required_unless:subscriptions.extension.*.status,prolongation',
                $this->domainIsUnclaimedRule,
            ],
            'subscriptions.extension.*.contact_id' => new NotAnonymousDomainContactRule($this->translator),

            'subscriptions.extension.*.children' => [
                'required_unless:subscriptions.extension.*.status,prolongation',
                'array:dns',
            ],
            'subscriptions.extension.*.children.*' =>
                // Only DNS children are allowed
                function (string $attribute, mixed $value, Closure $fail) {
                    // Example $attribute: subscriptions.domain.0.children.dns
                    $productGroup = explode('.', $attribute)[4];

                    if (ProductGroupType::from($productGroup) !== ProductGroupType::DNS) {
                        $fail($this->translator->translate('validation.in'));
                    }
                },
        ];
    }
}
