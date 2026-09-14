<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Customer\Rules;

use Illuminate\Container\Container;
use Illuminate\Contracts\Cache\Repository;
use Psr\Log\LoggerInterface;
use SandwaveIo\Vat\Exceptions\VatFetchFailedException;
use SandwaveIo\Vat\Exceptions\VatNumberValidateFailedException;
use SandwaveIo\Vat\Vat;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;
use Waterfront\Support\Enums\LoggingContextKeys;

class VatCode extends AbstractValidator
{
    private bool $error = false;

    public function __construct(
        private readonly Vat $vatService,
        private readonly string $code,
        private readonly LoggerInterface $logger,
        private readonly Repository $cache,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        if (! $this->vatService->countryInEurope($this->code)) {
            return true;
        }

        $vatNumber = strtoupper($value);
        $countryCode = strtoupper($this->code);
        $cacheKey = "vat.number.validation.$vatNumber";

        try {
            if ($this->cache->has($cacheKey)) {
                return (bool) $this->cache->get($cacheKey);
            }

            $valid = $this->vatService->validateEuropeanVatNumber($vatNumber, $countryCode);

            if ($valid) {
                // Only cache if it's valid, because new VAT numbers might be invalid now but valid the next day
                $this->cache->put(
                    key: $cacheKey,
                    value: true,
                    ttl: 1209600, // two weeks
                );
            }

            return $valid;
        } catch (VatNumberValidateFailedException|VatFetchFailedException $exception) {
            $this->logger->error(
                'VAT number validation check error, validating as true',
                [
                    LoggingContextKeys::EXCEPTION => $exception,
                    LoggingContextKeys::META => [
                        'vat_number' => $vatNumber,
                        'country_code' => $countryCode,
                    ],
                ],
            );

            $this->error = false;

            return true;
        }
    }

    protected function message(): string
    {
        /** @var TranslatorInterface $translator */
        $translator = Container::getInstance()->make(TranslatorInterface::class);

        if ($this->error) {
            return $translator->translate('validation.vat_number.down');
        }

        return $translator->translate('validation.vat_number');
    }
}
