<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\DomainNames\Coupling\Services;

use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\CreateDomainNameCoupleDeploymentException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\DeleteDomainNameCoupleDeploymentException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\ServiceNotInstanceOfDomainNameCoupleInterfaceException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\UnknownDomainNameCoupleProvisionTypeException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Exceptions\UnknownDomainNameCoupleRequestException;
use Waterfront\Domain\Provision\DomainNames\Coupling\Interfaces\DomainNameCoupleInterface;
use Waterfront\Domain\Provision\DomainNames\Coupling\Repositories\DomainNameCoupleRepository;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameCoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Requests\DomainNameDecoupleRequest;
use Waterfront\Domain\Provision\DomainNames\Coupling\Results\DomainNameCoupleResult;
use Waterfront\Domain\Provision\DomainNames\Coupling\Validators\DomainNameCoupleValidator;
use Waterfront\Domain\Provision\Enums\ProvisionProvider;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Enums\ProvisionType;
use Waterfront\Domain\Provision\Exceptions\DeploymentNotFoundException;
use Waterfront\Domain\Provision\Exceptions\MissingRequestIdException;
use Waterfront\Domain\Provision\Hosting\Services\HostingProvisionService;
use Waterfront\Domain\Provision\Interfaces\ProvisionRequestInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionResultInterface;
use Waterfront\Domain\Provision\Interfaces\ProvisionServiceInterface;
use Waterfront\Domain\Provision\Repositories\ProvisioningDeploymentRepository;
use Waterfront\Domain\Provision\Results\ProvisionResult;
use Waterfront\Domain\Provision\Services\AbstractProvisionService;

class DomainNameCoupleService extends AbstractProvisionService implements ProvisionServiceInterface
{
    public function __construct(
        private readonly DomainNameCoupleValidator $validator,
        private readonly HostingProvisionService $hostingService,
        private readonly DomainNameCoupleRepository $coupleRepository,
        private readonly ProvisioningDeploymentRepository $deploymentRepository,
    ) {
    }

    public function getDefaultProvider(): ProvisionProvider
    {
        return ProvisionProvider::INTERNAL;
    }

    /**
     * @throws UnknownDomainNameCoupleRequestException
     */
    public function validate(ProvisionRequestInterface $provisionData): ?ProvisionResultInterface
    {
        $validator = $this->validator->getValidatorByRequest($provisionData);

        if ($validator->fails()) {
            return $this->createFailedValidationResult($provisionData, $validator);
        }

        return null;
    }

    /**
     * @throws DeploymentNotFoundException
     * @throws MissingRequestIdException
     * @throws ServiceNotInstanceOfDomainNameCoupleInterfaceException
     * @throws UnknownDomainNameCoupleProvisionTypeException
     * @throws UnknownDomainNameCoupleRequestException
     */
    public function send(ProvisionRequestInterface $provisionData): ProvisionResultInterface
    {
        return match($provisionData::class) {
            DomainNameCoupleRequest::class => $this->coupleDomainName($provisionData),
            DomainNameDecoupleRequest::class => $this->decoupleDomainName($provisionData),
            default => throw new UnknownDomainNameCoupleRequestException($provisionData),
        };
    }

    /**
     * @throws ServiceNotInstanceOfDomainNameCoupleInterfaceException
     * @throws UnknownDomainNameCoupleProvisionTypeException
     */
    public function getServiceFromProvisionType(ProvisionType $provisionType): DomainNameCoupleInterface
    {
        $service = match ($provisionType) {
            ProvisionType::HOSTING => $this->hostingService,
            ProvisionType::REDIRECT => $this->hostingService, // TODO replace with RedirectProvisionService when implemented
            default => throw new UnknownDomainNameCoupleProvisionTypeException($provisionType)
        };

        if (! $service instanceof DomainNameCoupleInterface) {
            throw new ServiceNotInstanceOfDomainNameCoupleInterfaceException($service);
        }

        return $service;
    }

    /**
     * @throws UnknownDomainNameCoupleProvisionTypeException
     * @throws ServiceNotInstanceOfDomainNameCoupleInterfaceException
     * @throws MissingRequestIdException
     * @throws DeploymentNotFoundException
     */
    private function coupleDomainName(DomainNameCoupleRequest $provisionData): DomainNameCoupleResult
    {
        $requestId = $provisionData->requestId;

        $deployment = $this->deploymentRepository->findDeploymentByRequestUuid($provisionData->requestUuid);

        if ($deployment === null) {
            throw new DeploymentNotFoundException(
                sprintf('No deployment found for the given request UUID [%s].', $provisionData->requestUuid)
            );
        }

        $coupleType = $deployment->request->request_type;
        $domainNameCoupleDeployment = null;

        try {
            $domainNameCoupleDeployment = $this->coupleRepository->create(
                domain: $provisionData->domain,
                coupleType: $coupleType,
                deploymentUuid: $deployment->uuid,
                requestId: $requestId
            );
        } catch (CreateDomainNameCoupleDeploymentException $exception) {
            $result = new DomainNameCoupleResult($provisionData, ProvisionStatus::FAILED);
            $result->domain = $provisionData->domain;
            $result->exception = $exception;
            return $result;
        }

        $domainNameCoupleService = $this->getServiceFromProvisionType($coupleType);

        $couple = $domainNameCoupleService->coupleToDomainName($provisionData);
        $couple->domain = $provisionData->domain;
        return $couple;
    }

    /**
     * @throws UnknownDomainNameCoupleProvisionTypeException
     * @throws ServiceNotInstanceOfDomainNameCoupleInterfaceException
     * @throws DeploymentNotFoundException
     */
    private function decoupleDomainName(DomainNameDecoupleRequest $provisionData): ProvisionResult
    {
        $deployment = $this->deploymentRepository->findDeploymentByRequestUuid($provisionData->requestUuid);

        if ($deployment === null) {
            throw new DeploymentNotFoundException(
                sprintf('No deployment found for the given request UUID [%s].', $provisionData->requestUuid)
            );
        }

        $coupleType = $deployment->request->request_type;
        $domainNameCoupleService = $this->getServiceFromProvisionType($coupleType);

        $result = $domainNameCoupleService->decoupleDomainName($provisionData);

        if ($result->failed) {
            return $result;
        }

        try {
            $this->coupleRepository->delete(
                domain: $provisionData->domain,
                coupleType: $coupleType,
                deploymentUuid: $deployment->uuid,
            );
        } catch (DeleteDomainNameCoupleDeploymentException $exception) {
            $result->provisionStatus = ProvisionStatus::FAILED;
            $result->exception = $exception;
        }

        return $result;
    }
}
