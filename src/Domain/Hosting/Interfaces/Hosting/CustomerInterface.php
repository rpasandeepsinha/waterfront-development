<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting;

use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\CreateCustomer\Result as CustomerCreateResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Parameters as CustomerDeleteParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer\Result as CustomerDeleteResult;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as CustomerCreateParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Parameters as HostingParameters;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result;
use Waterfront\Infra\PleskClient\Messages\CustomerGetDomainList\CustomerGetDomainListResult;

interface CustomerInterface extends ClientInterface
{
    public function createCustomer(CustomerCreateParameters $parameters): CustomerCreateResult;

    public function deleteCustomer(CustomerDeleteParameters $parameters): CustomerDeleteResult;

    public function fetchCustomer(HostingParameters $parameters): Result;

    public function getDomainList(string $customerLogin): CustomerGetDomainListResult;
}
