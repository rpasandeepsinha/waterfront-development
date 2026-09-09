<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Customers\Rules;

use Illuminate\Support\Facades\Log;
use SandwaveIo\Vat\Exceptions\VatNumberValidateFailedException;
use SandwaveIo\Vat\Vat;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Infra\Validation\AbstractValidator;

class VatNovaCode extends AbstractValidator
{
    private readonly Vat $vatService;

    private bool $exception = false;

    private string $exceptionMessage = '';

    public function __construct(private readonly int $customerNumber)
    {
        $this->vatService = new Vat();
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        if (! is_string($value) || $this->customerNumber === 0) {
            return false;
        }

        $value = strtoupper($value);

        $customer = Customer::where('customer_number', $this->customerNumber)->firstOrFail();
        assert($customer->address !== null);

        $countryCode = $customer->address->country_code;

        if (! $this->vatService->countryInEurope($countryCode)) {
            return true;
        }

        try {
            return $this->vatService->validateEuropeanVatNumber($value, $countryCode);
        } catch (VatNumberValidateFailedException $exception) {
            Log::error(
                self::class . '::Vat number - status code: ' . $exception->getCode()
                . ', message: ' . $exception->getMessage()
                . ', trace: ' . $exception->getTraceAsString()
            );
            $this->exceptionMessage = $exception->getMessage();
            $this->exception = true;
            return false;
        }
    }

    protected function message(): string
    {
        if ($this->exception) {
            return $this->exceptionMessage;
        }

        return resolve(TranslatorInterface::class)->translate('validation.vat_number');
    }
}
