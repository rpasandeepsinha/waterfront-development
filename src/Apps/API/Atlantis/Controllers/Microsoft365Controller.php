<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Atlantis\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Atlantis\Requests\Microsoft365\TenantCheckRequest;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Infra\Translation\TranslatorInterface;

class Microsoft365Controller
{
    public function __construct(
        private readonly Microsoft365Service $microsoft365Service,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function tenantCheck(TenantCheckRequest $request): JsonResponse
    {
        $tenantName = (string) $request->string('tenantName');
        $tenantId = $this->microsoft365Service->getTenantIdByName($tenantName);

        if ($tenantId === null) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.validation.tenant-not-found'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND,
            );
        }

        if (! $this->microsoft365Service->hasDomainOwnership($tenantId)) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.validation.tenant-not-authorized'),
                    'errors' => [],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return new JsonResponse(['tenantId' => $tenantId]);
    }
}
