<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use SandwaveIo\LighthouseAuthBase\Permissions\Permissions;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Requests\StoreInternalNameserverRequest;
use Waterfront\Apps\API\Compass\Requests\UpdateInternalNameserverRequest;
use Waterfront\Apps\API\Compass\Resources\Migration\InternalNameserverResource;
use Waterfront\Domain\Ferry\Models\FerryInternalNameserver;
use Waterfront\Domain\Ferry\Repositories\InternalNamserverRepository;
use Waterfront\Infra\Authentication\Attributes\RequirePermission;
use Waterfront\Infra\Translation\TranslatorInterface;

class FerryInternalNameserverController
{
    public function __construct(
        private readonly InternalNamserverRepository $internalNameserverRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[RequirePermission(Permissions::CAN_SEE_MIGRATIONS)]
    public function index(Request $request): ResourceCollection
    {
        $pageSize = is_numeric($request->input('pageSize')) ? (int) $request->input('pageSize') : 100;
        $nameservers = $this->internalNameserverRepository->paginate($pageSize);
        $nameservers->appends('pageSize', (string) $pageSize);

        return InternalNameserverResource::collection($nameservers);
    }

    #[RequirePermission(Permissions::CAN_CONFIGURE_MIGRATIONS)]
    public function store(StoreInternalNameserverRequest $request): JsonResponse
    {
        $this->internalNameserverRepository->store($request->nameserver_hostname);

        return new JsonResponse([
            'message' => $this->translator->translate('internal-nameserver.created'),
            'errors' => [],
        ], Response::HTTP_CREATED);
    }

    #[RequirePermission(Permissions::CAN_CONFIGURE_MIGRATIONS)]
    public function update(
        UpdateInternalNameserverRequest $request,
        FerryInternalNameserver $ferryInternalNameserver,
    ): JsonResponse {
        $this->internalNameserverRepository->updateHostname(
            $ferryInternalNameserver,
            $request->nameserver_hostname,
        );

        return new JsonResponse([
            'message' => $this->translator->translate('internal-nameserver.updated'),
            'errors' => [],
        ], Response::HTTP_OK);
    }

    #[RequirePermission(Permissions::CAN_CONFIGURE_MIGRATIONS)]
    public function destroy(FerryInternalNameserver $ferryInternalNameserver): JsonResponse
    {
        $this->internalNameserverRepository->delete($ferryInternalNameserver);

        return new JsonResponse([
            'message' => $this->translator->translate('internal-nameserver.deleted'),
            'errors' => [],
        ], Response::HTTP_OK);
    }
}
