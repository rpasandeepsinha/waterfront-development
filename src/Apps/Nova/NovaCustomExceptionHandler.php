<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova;

use Laravel\Nova\Exceptions\NovaExceptionHandler;
use Throwable;
use Waterfront\Infra\Configuration\ConfigurationInterface;

class NovaCustomExceptionHandler extends NovaExceptionHandler
{
    /**
     * @return mixed[]
     */
    protected function convertExceptionToArray(Throwable $e): array
    {
        $configuration = $this->container->make(ConfigurationInterface::class);

        if ($configuration->getAsBoolean('app.debug')) {
            return parent::convertExceptionToArray($e);
        }

        return [
            'message' => $e->getMessage(),
        ];
    }
}
