<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Rules;

use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\Rules\DomainNameRule;
use Waterfront\Infra\Common\PublicSuffixList;

class RedirectMigrationRules
{
    public function __construct(
        private readonly DomainNameRule $domainNameRule,
        private readonly PublicSuffixList $publicSuffixList,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getRules(
        Customer|null $customer,
        string|null $domain,
    ): array {
        return MigrationValidationLibrary::getRedirectBaseRules(
            $this->domainNameRule,
            $this->publicSuffixList,
            $customer,
            $domain
        );
    }
}
