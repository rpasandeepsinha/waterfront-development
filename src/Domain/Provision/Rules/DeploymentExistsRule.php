<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Rules;

use Ramsey\Uuid\UuidInterface;
use Waterfront\Domain\Provision\Enums\ProvisionErrorMessage;
use Waterfront\Domain\Provision\Models\ProvisionDeployment;
use Waterfront\Domain\Provision\Repositories\ProvisioningDeploymentRepository;
use Waterfront\Infra\Validation\AbstractValidator;
use Webmozart\Assert\Assert;

class DeploymentExistsRule extends AbstractValidator
{
    public function __construct(
        private readonly ProvisioningDeploymentRepository $provisioningDeploymentRepository,
    ) {
    }

    protected function passes(string $attribute, mixed $value): bool
    {
        Assert::isInstanceOf($value, UuidInterface::class);

        if (
            !
                $this->provisioningDeploymentRepository->findDeploymentByRequestUuid($value)
                instanceof ProvisionDeployment

        ) {
            return false;
        }

        return true;
    }

    protected function message(): string
    {
        return ProvisionErrorMessage::DEPLOYMENT_NOT_FOUND->value;
    }
}
