<?php

declare(strict_types=1);

namespace Waterfront\Domain\VPS\Rules;

use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Domain\VPS\Repositories\SshKeyRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

class CloudStackVirtualMachineRules
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ProductRepository $productRepository,
        private readonly ProductSpecRepository $productSpecRepository,
        private readonly SshKeyRepository $sshKeyRepository,
    ) {
    }

    /**
     * @return array<mixed>
     */
    public function getCloudStackVirtualMachineRules(Customer $customer): array
    {
        $sshKeyRequiredRule = new SshKeyValidationRule(
            customer: $customer,
            translator: $this->translator,
            productRepository: $this->productRepository,
            productSpecRepository: $this->productSpecRepository,
            sshKeyRepository: $this->sshKeyRepository,
        );

        return [
            'subscriptions.vps.*.children' => [
                'required',
                sprintf('array:%s', ProductGroupType::CLOUDSTACK_OS->value),
                'filled',
            ],
            'subscriptions.vps.*.children.cloudstack-os.*' => ['required', $sshKeyRequiredRule],
        ];
    }
}
