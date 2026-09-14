<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Pipelines\CreateCertificate;

use Waterfront\Domain\Ssl\Interfaces\Models\Result;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;

class UpdateSubscriptionStep
{
    public function __construct(
        private readonly DeploymentRepository $subscriptionRepo,
    ) {
    }

    public function execute(Result $result, string $subscriptionUuid, bool $customCsr): SslDeployment
    {
        return $this->subscriptionRepo->updateOrCreate($result->toArray(), $subscriptionUuid, $customCsr);
    }
}
