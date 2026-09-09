<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Services;

use Illuminate\Support\Facades\Cache;
use Psr\Log\LoggerInterface;
use SandwaveIo\Vat\Exceptions\VatFetchFailedException;
use SandwaveIo\Vat\Exceptions\VatNumberValidateFailedException;
use SandwaveIo\Vat\Vat;
use Waterfront\Domain\Customers\DTO\VatDTO;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Config\ApplicationConfig;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

readonly class CustomerVatService
{
    private const string COUNTRY_CODE_NL = 'NL';

    public function __construct(
        private Vat $vat,
        private ConfigurationInterface $configuration,
        private ApplicationConfig $applicationConfig,
        private LoggerInterface $logger,
    ) {
    }

    /**
     *
     * @throws InvalidCountryCodeException
     *
     */
    public function getCustomerVatData(Customer $customer): VatDTO
    {
        if ($customer->vat_rate === null) {
            $this->updateCustomerVat($customer);
        }
        Assert::notNull($customer->vat_rate);
        $countryCode = $customer->address->country_code ?? self::COUNTRY_CODE_NL;
        $vatCode = $countryCode . (int) $customer->vat_rate;

        if ($customer->vat_rate === 0.0) {
            $vatCode = $customer->icp ? 'ICP0' : 'EXP0';
        }

        return new VatDTO(
            vatCode: $vatCode,
            vatRate: $customer->vat_rate,
        );
    }

    /**
     *
     * @throws InvalidCountryCodeException
     * @throws VatNumberValidateFailedException
     * @throws VatFetchFailedException
     */
    public function updateCustomerVat(Customer $customer): void
    {
        $countryCode = $customer->address->country_code ?? self::COUNTRY_CODE_NL;
        $vatRate = $this->getEuropeanVatRateCached($customer, $countryCode);

        $customer->icp = $this->validateEuropeanVatNumber($customer, $countryCode)
            && $this->vat->countryInEurope($countryCode)
            && $countryCode !== self::COUNTRY_CODE_NL;

        // ICP customers have no vat rate regardless of origin (except for NL).
        $customer->vat_rate = $customer->icp ? 0.0 : $vatRate;
        $customer->save();
    }

    /**
     *
     * @throws InvalidCountryCodeException
     */
    private function getEuropeanVatRateCached(Customer $customer, string $countryCode): float
    {
        if ($countryCode === '') {
            $this->logger->critical(
                sprintf('Failed to get VAT rate for customer %s, because they country code provided was an empty string.', $customer->id),
                [LoggingContextKeys::CUSTOMER_ID => $customer->id]
            );

            throw new InvalidCountryCodeException($countryCode);
        }

        $cacheKey = "vat.rate.$countryCode";

        if (Cache::has($cacheKey)) {
            $cachedRate = Cache::get($cacheKey);

            if (is_numeric($cachedRate)) {
                return (float) $cachedRate;
            }
        }

        $vatRate = $this->vat->europeanVatRate($countryCode);

        if ($vatRate > 0) {
            Cache::put($cacheKey, $vatRate, $this->configuration->getAsInteger('cache.vat_rate_lifetime'));
        } elseif ($this->vat->countryInEurope($countryCode)) {
            $this->logger->alert(
                sprintf('VIES service returned a vat rate of %f%% for country %s. Falling back to
                %s for customer %s', $vatRate, $countryCode, $this->applicationConfig->defaultTaxRate, $customer->id),
                [LoggingContextKeys::CUSTOMER_ID => $customer->id]
            );

            return (float) $this->applicationConfig->defaultTaxRate;
        }

        return $vatRate;
    }

    /**
     *
     * @throws VatFetchFailedException
     * @throws VatNumberValidateFailedException
     */
    private function validateEuropeanVatNumber(Customer $customer, string $countryCode): bool
    {
        try {
            $isValid = false;

            if ($customer->vat_number !== null) {
                $cacheKey = "vat.number.validation.$customer->vat_number";

                if (Cache::has($cacheKey)) {
                    return (bool) Cache::get($cacheKey);
                }

                $isValid = $this->vat->validateEuropeanVatNumber($customer->vat_number, $countryCode);

                if ($isValid) {
                    // Only cache if it's valid, because new VAT numbers might be invalid now but valid the next day
                    Cache::put(
                        key: $cacheKey,
                        value: true,
                        ttl: 1209600 // two weeks
                    );
                }
            }
        } catch (VatFetchFailedException|VatNumberValidateFailedException $exception) {
            $customer->vatErrors()->create([
                'status_code' => $exception->getCode(),
                'message' => $exception->getMessage(),
            ]);
            $this->logger->critical(
                sprintf('VAT number validation failed for customer %s, Exception: %s', $customer->id, $exception->getMessage()),
                [
                    LoggingContextKeys::CUSTOMER_ID => $customer->id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );
        }

        return $isValid;
    }
}
