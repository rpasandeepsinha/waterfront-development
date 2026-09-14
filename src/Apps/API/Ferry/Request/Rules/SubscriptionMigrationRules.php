<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Rules;

use Psr\Log\LoggerInterface;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Apps\API\Ferry\Rules\NonMigratedSubscriptionAlreadyExists;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Rules\DomainHasNoSubdomainRule;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Infra\Translation\Translator;

class SubscriptionMigrationRules
{
    public function __construct(
        private readonly DomainNameRule $domainNameRule,
        private readonly DomainHasNoSubdomainRule $domainHasNoSubdomainRule,
        private readonly NonMigratedSubscriptionAlreadyExists $nonMigratedSubscriptionAlreadyExistsRule,
        private readonly PriceResolver $priceResolver,
        private readonly Translator $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(
        Customer $customer,
        bool $pipelineRun = false,
    ): array {
        return MigrationValidationLibrary::subscriptionRules(
            $this->domainNameRule,
            $this->domainHasNoSubdomainRule,
            $this->nonMigratedSubscriptionAlreadyExistsRule,
            $this->priceResolver,
            $this->translator,
            $this->logger,
            $customer,
            $pipelineRun,
        );
    }
}
