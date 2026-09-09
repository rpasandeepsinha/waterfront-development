<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Domain\Marketing\Service\HubspotService;
use Webmozart\Assert\Assert;

class HubspotController
{
    public function __construct(
        private readonly HubspotService $hubspotService,
    ) {
    }

    public function getCustomerData(Request $request): JsonResponse
    {
        $subscriptionUuid = (string) $request->string('sw_uuid');
        Assert::uuid($subscriptionUuid);

        $data = $this->hubspotService->getContactFromSubscriptionUuid($subscriptionUuid);

        if ($data === null) {
            return new JsonResponse([], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse(['data' => $data]);
    }
}
