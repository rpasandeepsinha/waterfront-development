<?php

declare(strict_types=1);

namespace Waterfront\Domain\Customers\Rules;

use Illuminate\Contracts\Cache\Repository;
use Propaganistas\LaravelPhone\Rules\Phone;
use Psr\Log\LoggerInterface;
use SandwaveIo\Vat\Vat;
use Waterfront\Apps\API\Waterfront\Requests\Customer\Rules\VatCode;
use Waterfront\Support\Helpers\ValidationRules\FilterSpecialChars;

class CustomerPatchRule
{
    public function __construct(
        private readonly CustomerAddressRule $customerAddressRule,
        private readonly Vat $vat,
        private readonly LoggerInterface $logger,
        private readonly Repository $cache,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function getRules(string $countryCode): array
    {
        return [
            'first_name'    => ['required', 'min:2, max:80', new FilterSpecialChars()],
            'last_name'     => ['required', 'min:2, max:80', new FilterSpecialChars()],
            'gender'        => ['sometimes', 'min:1', 'max:1'],
            'organization'  => ['nullable', 'min:2, max:191', new FilterSpecialChars('.&')],
            'department'    => ['nullable', 'min:2, max:191', new FilterSpecialChars('.&')],
            'coc_number'    => ['sometimes', 'nullable', 'min:2, max:32', new FilterSpecialChars()],
            'vat_number'    => ['sometimes', 'nullable', 'string', new VatCode($this->vat, $countryCode, $this->logger, $this->cache), new FilterSpecialChars()],
            'phone_number'  => ['required', (new Phone())],
            'purchase_reference' => ['nullable', 'string', new FilterSpecialChars(characters: '<>$%;~{}')],
        ];
    }

    /**
     * @param array<mixed> $additionalRules
     *
     * @return array<mixed>
     */
    public function withAdditionalRules(string $countryCode, array $additionalRules = []): array
    {
        return array_merge($this->getRules($countryCode), $additionalRules);
    }

    /**
     * @param array<mixed> $additionalRules
     *
     * @return array<mixed>
     */
    public function withAddressRules(string $countryCode, array $additionalRules = []): array
    {
        return array_merge($this->withAdditionalRules($countryCode, $this->customerAddressRule->getRules()), $additionalRules);
    }
}
