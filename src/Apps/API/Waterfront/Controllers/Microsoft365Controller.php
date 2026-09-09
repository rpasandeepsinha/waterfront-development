<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Waterfront\Apps\API\Waterfront\Policies\CustomerPolicy;
use Waterfront\Apps\API\Waterfront\Requests\Microsoft365\UpdatePrimaryDomainRequest;
use Waterfront\Domain\Domains\Repositories\DomainDeploymentRepository;
use Waterfront\Domain\Microsoft365\Enums\PrimaryDomainStatus;
use Waterfront\Domain\Microsoft365\Exceptions\MicrosoftCustomerAgreementException;
use Waterfront\Domain\Microsoft365\Exceptions\MicrosoftCustomerNotFoundException;
use Waterfront\Domain\Microsoft365\Jobs\SetPrimaryDomainJob;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Microsoft365\Services\Microsoft365SerializerFactory;
use Waterfront\Domain\Microsoft365\Services\Microsoft365Service;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class Microsoft365Controller
{
    public function __construct(
        private readonly AuthenticationManager $authenticationManager,
        private readonly Microsoft365Service $microsoft365Service,
        private readonly Microsoft365CustomerInfoRepository $customerInfoRepository,
        private readonly DomainDeploymentRepository $domainDeploymentRepository,
        private readonly TranslatorInterface $translator,
        private readonly Dispatcher $dispatcher,
        private readonly Microsoft365SerializerFactory $serializerFactory,
        private readonly CustomerPolicy $customerPolicy,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws ExceptionInterface
     */
    public function microsoftInformation(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageM365();

        $microsoftData = $this->microsoft365Service->gatherMicrosoftData($customer);

        return new JsonResponse([
            'data' => $this->serializerFactory->get()->normalize($microsoftData) ?? ['available_actions' => []],
        ]);
    }

    public function updatePrimaryDomain(UpdatePrimaryDomainRequest $request): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageM365();
        $customerInfo = $this->customerInfoRepository->findActiveByCustomer($customer);
        $subscriptionUuid = $request->input('subscription');
        Assert::string($subscriptionUuid);
        $domainDeployment = $this->domainDeploymentRepository->getActiveDomainDeploymentBySubscriptionUuidAndCustomer($customer, $subscriptionUuid);

        if ($customerInfo === null) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.validation.customer-info-not-found'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        if (! in_array($customerInfo->primary_domain_status, PrimaryDomainStatus::allowedToChangeDomainStatus(), true)) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.validation.status-not-allowed-to-update-domain'),
                    'errors' => [],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        if ($domainDeployment?->subscription->domain === null) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.validation.domain-not-found'),
                    'errors' => [],
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->dispatcher->dispatch(new SetPrimaryDomainJob($domainDeployment->subscription->domain, $customerInfo));

        return new JsonResponse(
            [
                'message' => $this->translator->translate('microsoft365.primary-domain-coupled-success'),
            ]
        );
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     */
    public function getMicrosoftCustomerAgreementUrl(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageM365();

        try {
            $response = $this->microsoft365Service->getMicrosoftCustomerAgreementUrl($customer);
        } catch (MicrosoftCustomerNotFoundException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.error.customer-not-ready'),
                    'errors' => [],
                ],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        } catch (MicrosoftCustomerAgreementException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.error.mca-url-retrieval-failed'),
                    'errors' => [],
                ],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        return new JsonResponse([
            'mcaUrl' => $response->attestationUrl,
        ]);
    }

    /**
     * @throws AuthenticationException
     * @throws AuthorizationException
     * @throws MicrosoftCustomerNotFoundException
     */
    public function microsoftCustomerAgreementSigned(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $this->customerPolicy->assertCanManageM365();

        try {
            $customerAgreement = $this->microsoft365Service->getMicrosoftCustomerAgreement($customer);
        } catch (MicrosoftCustomerAgreementException) {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.error.mca-url-retrieval-failed'),
                    'errors' => [],
                ],
                Response::HTTP_SERVICE_UNAVAILABLE
            );
        }

        if (strtolower((string) $customerAgreement->attestationStatus) !== 'accepted') {
            return new JsonResponse(
                [
                    'message' => $this->translator->translate('microsoft365.error.mca-not-signed'),
                    'errors' => [
                        'mca' => [
                            $this->translator->translate('microsoft365.error.mca-not-signed'),
                        ],
                    ],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $microsoft365CustomerInfo = $this->customerInfoRepository->findByCustomer($customer);
        Assert::notNull($microsoft365CustomerInfo);

        $microsoft365CustomerInfo->mca_signed_at = CarbonImmutable::now();
        $microsoft365CustomerInfo->save();

        $this->microsoft365Service->prepareOrders($microsoft365CustomerInfo);

        return new JsonResponse([], Response::HTTP_NO_CONTENT);
    }
}
