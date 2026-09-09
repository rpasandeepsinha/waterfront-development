<?php

declare(strict_types=1);

namespace Waterfront\Domain\Payments\Managers;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Payments\Models\MollieCustomer;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerRequestDTO;
use Waterfront\Infra\MollieClient\DTO\Customers\MollieCustomerResponseDTO;
use Waterfront\Infra\MollieClient\Exceptions\MollieCustomerApiException;
use Waterfront\Infra\MollieClient\MollieCustomerClient;

class MollieCustomerManager
{
    public function __construct(
        private readonly MollieCustomerClient $mollieCustomerClient,
    ) {
    }

    /**
     * @throws MollieCustomerApiException
     */
    public function findByMollieCustomer(MollieCustomer $mollieCustomer): MollieCustomerResponseDTO
    {
        return $this->mollieCustomerClient->getCustomerById($mollieCustomer->mollie_customer_reference_id);
    }

    /**
     * @throws MollieCustomerApiException
     */
    public function findOrCreate(MollieCustomerRequestDTO $mollieCustomerCreate, Customer $customer): MollieCustomerResponseDTO
    {
        $existingMollieCustomer = $customer->mollieCustomer;

        if ($existingMollieCustomer instanceof MollieCustomer) {
            return $this->findByMollieCustomer($existingMollieCustomer);
        }

        $createdCustomer = $this->mollieCustomerClient->createCustomer($mollieCustomerCreate);

        $mollieCustomer = new MollieCustomer();
        $mollieCustomer->mollie_customer_reference_id = $createdCustomer->id;

        $customer->mollieCustomer()->save($mollieCustomer);

        return $createdCustomer;
    }

    /**
     * @throws MollieCustomerApiException
     */
    public function updateCustomer(MollieCustomer $mollieCustomer, MollieCustomerRequestDTO $mollieCustomerUpdateDTO): MollieCustomerResponseDTO
    {
        $updatedCustomer = $this->mollieCustomerClient->updateCustomer($mollieCustomer->mollie_customer_reference_id, $mollieCustomerUpdateDTO);

        $mollieCustomer->touch();

        return $updatedCustomer;
    }
}
