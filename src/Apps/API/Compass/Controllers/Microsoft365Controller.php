<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Controllers;

use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Exception\Office365Exception;
use Symfony\Component\HttpFoundation\Response;
use Waterfront\Apps\API\Compass\Resources\Microsoft365\Microsoft365DeploymentResource;
use Waterfront\Apps\API\Compass\Resources\Microsoft365\Microsoft365TenantResource;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Microsoft365\Actions\RetryCreateKpnCustomerAction;
use Waterfront\Domain\Microsoft365\Actions\RetryOrderCreateAction;
use Waterfront\Domain\Microsoft365\Enums\Microsoft365RetryOrderCreateResult;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Repositories\Microsoft365CustomerInfoRepository;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class Microsoft365Controller
{
    public function __construct(
        private readonly RetryCreateKpnCustomerAction $retryCreateKpnCustomerAction,
        private readonly RetryOrderCreateAction $retryOrderCreateAction,
        private readonly Microsoft365CustomerInfoRepository $microsoft365CustomerInfoRepository,
        private readonly Microsoft365TenantResource $microsoft365TenantResource,
        private readonly Microsoft365DeploymentResource $microsoft365DeploymentResource,
        private readonly TranslatorInterface $translator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function retryCreateKpnCustomer(Microsoft365CustomerInfo $microsoft365CustomerInfo): JsonResponse
    {
        try {
            $successful = $this->retryCreateKpnCustomerAction->execute($microsoft365CustomerInfo);
        } catch (Office365Exception $exception) {
            $this->logger->error(
                'Retrying KPN customer creation failed for customer {customer.id}',
                [
                    LoggingContextKeys::CUSTOMER_ID => $microsoft365CustomerInfo->customer_id,
                    LoggingContextKeys::EXCEPTION => $exception,
                ]
            );

            return new JsonResponse([
                'message' => $this->translator->translate('microsoft365.retry-create-kpn-customer.failure'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if (! $successful) {
            return new JsonResponse([
                'message' => $this->translator->translate('microsoft365.retry-create-kpn-customer.failure'),
                'errors' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse([
            'message' => $this->translator->translate('microsoft365.retry-create-kpn-customer.success'),
        ], Response::HTTP_OK);
    }

    public function retryCreateOrder(Microsoft365Deployment $microsoft365Deployment): JsonResponse
    {
        $result = $this->retryOrderCreateAction->execute($microsoft365Deployment);

        [$translationKey, $status] = match ($result) {
            Microsoft365RetryOrderCreateResult::ORDER_CREATED => ['microsoft365.retry-create-order.success', Response::HTTP_OK],
            Microsoft365RetryOrderCreateResult::TENANT_CREATED => ['microsoft365.retry-create-order.tenant-created', Response::HTTP_OK],
            Microsoft365RetryOrderCreateResult::MCA_NOT_SIGNED => ['microsoft365.retry-create-order.mca-not-signed', Response::HTTP_UNPROCESSABLE_ENTITY],
            Microsoft365RetryOrderCreateResult::NO_SEATS => ['microsoft365.retry-create-order.no-seats', Response::HTTP_UNPROCESSABLE_ENTITY],
            Microsoft365RetryOrderCreateResult::ORDER_SUMMARY_RETRIEVAL_FAILED => ['microsoft365.retry-create-order.order-summary-retrieval-failed', Response::HTTP_INTERNAL_SERVER_ERROR],
            Microsoft365RetryOrderCreateResult::ORDER_CREATION_FAILED => ['microsoft365.retry-create-order.failure', Response::HTTP_INTERNAL_SERVER_ERROR],
        };

        return new JsonResponse([
            'message' => $this->translator->translate($translationKey),
            'errors' => [],
        ], $status);
    }

    public function customerOverview(Customer $customer): string
    {
        $microsoft365CustomerInfos = $this->microsoft365CustomerInfoRepository->findAllByCustomer($customer);

        return $this->microsoft365TenantResource->collectionToJson($microsoft365CustomerInfos);
    }

    public function tenantDetail(Microsoft365CustomerInfo $microsoft365CustomerInfo): string
    {
        $microsoft365CustomerInfo->loadMissing([
            'microsoft365Deployments.subscription.children.product',
            'microsoft365Deployments.subscription.product',
            'microsoft365Deployments.microsoft365CustomerInfo',
        ]);

        return $this->microsoft365TenantResource->toJson($microsoft365CustomerInfo);
    }

    public function deploymentDetail(Microsoft365Deployment $microsoft365Deployment): string
    {
        $microsoft365Deployment->loadMissing([
            'subscription.children.product',
            'subscription.product',
            'microsoft365CustomerInfo',
        ]);

        return $this->microsoft365DeploymentResource->toDetailJson($microsoft365Deployment);
    }

    public function subscriptionDeployment(Subscription $subscription): string|JsonResponse
    {
        $microsoft365Deployment = $subscription->microsoft365Deployment;

        if ($microsoft365Deployment === null) {
            return new JsonResponse([
                'message' => $this->translator->translate('microsoft365.deployment.not-found'),
                'errors' => [],
            ], Response::HTTP_NOT_FOUND);
        }

        $microsoft365Deployment->setRelation('subscription', $subscription);
        $microsoft365Deployment->loadMissing(['subscription.children.product', 'subscription.product', 'microsoft365CustomerInfo']);

        return $this->microsoft365DeploymentResource->toJson($microsoft365Deployment);
    }
}
