<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\SecurityBundle;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Waterfront\Domain\Products\Repositories\ProductExperimentOfferingRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;

class RedeemSecurityBundleRequest extends FormRequest
{
    /**
     * @return array<string, array<int, In|string>>
     */
    public function rules(): array
    {
        return [
            'products'   => ['required', 'array', 'min:1'],
            'products.*' => ['required', 'string', 'distinct', Rule::in($this->getOfferedProductSlugs())],
        ];
    }

    /**
     * @return non-empty-list<string>
     */
    public function getRequestedProductSlugs(): array
    {
        /** @var non-empty-list<string> $products */
        $products = array_values((array) $this->validated('products'));

        return $products;
    }

    /**
     * @return list<string>
     */
    private function getOfferedProductSlugs(): array
    {
        $customer = $this->container->make(AuthenticationManager::class)
            ->getAuthenticatedCustomer()
            ->customer;

        $offeringRepository = $this->container->make(ProductExperimentOfferingRepository::class);
        $offering = $offeringRepository->findOfferingForCustomer($customer);

        if ($offering === null) {
            return [];
        }

        return $offeringRepository->getOfferedProductSlugs($offering);
    }
}
