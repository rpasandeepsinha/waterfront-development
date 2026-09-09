<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Ferry\Request\Rules;

use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use SandwaveIo\Vat\Vat;
use Waterfront\Apps\API\Ferry\Request\MigrationValidationLibrary;
use Waterfront\Domain\Products\MigrationsPriceDiscounts;
use Waterfront\Domain\Products\ProductPrice\PriceResolver;
use Waterfront\Infra\Translation\Translator;

class CustomerMigrationRules
{
    public function __construct(
        private readonly MigrationsPriceDiscounts $migrationsPriceDiscounts,
        private readonly PriceResolver $priceResolver,
        private readonly Translator $translator,
        private readonly LoggerInterface $logger,
        private readonly Repository $cache,
        private readonly Vat $vat,
    ) {
    }

    /**
     * @param array<string, mixed> $customerData
     *
     * @return array<string, mixed>
     */
    public function getRules(
        array $customerData,
    ): array {
        return MigrationValidationLibrary::customerRules(
            $this->migrationsPriceDiscounts,
            $this->priceResolver,
            $this->translator,
            $this->logger,
            $this->cache,
            $this->vat,
            $customerData
        );
    }
}
