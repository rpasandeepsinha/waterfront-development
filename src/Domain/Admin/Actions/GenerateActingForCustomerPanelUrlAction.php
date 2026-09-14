<?php

declare(strict_types=1);

namespace Waterfront\Domain\Admin\Actions;

use Waterfront\Infra\Configuration\ConfigurationInterface;

class GenerateActingForCustomerPanelUrlAction
{
    public function __construct(
        private readonly ConfigurationInterface $config,
    ) {
    }

    public function execute(int $customerNumber): string
    {
        $customerPanelBaseUrl = $this->config->getAsString('app.url');

        return sprintf('%s?actingForCustomerNumber=%s', $customerPanelBaseUrl, $customerNumber);
    }
}
