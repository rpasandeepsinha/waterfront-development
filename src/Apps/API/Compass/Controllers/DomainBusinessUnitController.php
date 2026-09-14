<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Requests\UpdateDomainBusinessUnitRequest;
use Waterfront\Apps\API\Compass\Resources\Domains\DomainProviderBusinessUnitResource;
use Waterfront\Domain\Domains\Jobs\SetBusinessUnitOnDomainDeploymentsJob;
use Waterfront\Domain\Domains\Repositories\DomainProviderBusinessUnitRepository;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Translation\TranslatorInterface;

class DomainBusinessUnitController
{
    public function __construct(
        private readonly DomainProviderBusinessUnitRepository $businessUnitRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly Dispatcher $dispatcher,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function index(): ResourceCollection
    {
        return DomainProviderBusinessUnitResource::collection($this->businessUnitRepository->getAll());
    }

    public function update(UpdateDomainBusinessUnitRequest $request, string $domain): JsonResponse
    {
        $slug = $request->string('business_unit')->toString();
        $businessUnitId = null;

        if ($slug !== '') {
            try {
                $businessUnitId = $this->businessUnitRepository->findBySlug($slug)->id;
            } catch (ModelNotFoundException) {
                return new JsonResponse([
                    'message' => $this->translator->translate('domain-business-unit.validation.not-found'),
                    'errors' => [],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $subscription = $this->subscriptionRepository->findByDomainAndType($domain, ProductGroupType::EXTENSION);
        } catch (ModelNotFoundException) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-deployment.validation.not-found'),
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        $domainDeployment = $subscription->domainDeployment;

        if ($domainDeployment === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('domain-deployment.validation.not-found'),
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        $this->dispatcher->dispatch(new SetBusinessUnitOnDomainDeploymentsJob(
            businessUnitId: $businessUnitId,
            domainDeploymentIds: [$domainDeployment->id],
        ));

        return new JsonResponse([
            'message' => $this->translator->translate('domain-business-unit.update-success'),
            'errors' => [],
        ], Response::HTTP_OK);
    }
}
