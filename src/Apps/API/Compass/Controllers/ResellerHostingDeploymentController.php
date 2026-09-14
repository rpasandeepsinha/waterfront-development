<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Resources\Subscription\ResellerHostingDeploymentResource;
use Waterfront\Domain\Subscriptions\Models\Subscription;

class ResellerHostingDeploymentController
{
    public function __construct(
        private readonly ResellerHostingDeploymentResource $resellerHostingDeploymentResource,
    ) {
    }

    public function deployment(Subscription $subscription): string|JsonResponse
    {
        $resellerHostingDeployment = $subscription->resellerHostingDeployment;
        if ($resellerHostingDeployment === null) {
            return new JsonResponse([
                'message' => 'The deployment could not be found',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->resellerHostingDeploymentResource->toJson($resellerHostingDeployment);
    }
}
