<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Microsoft365\Services;

use Exception;
use SandwaveIo\Microsoft\Graph\Models\Domain;
use SandwaveIo\Microsoft\Graph\Models\DomainDnsRecordCollectionResponse;
use Waterfront\Domain\Provision\Enums\ProvisionStatus;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotCreatedException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotFoundException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotPromotedException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\DomainNotVerifiedException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\ServiceDnsRecordsNotFoundException;
use Waterfront\Domain\Provision\Microsoft365\Exceptions\VerificationDnsRecordsNotFoundException;
use Waterfront\Domain\Provision\Microsoft365\Interfaces\Microsoft365ProvisionServiceInterface;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365CreateDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365DeleteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetServiceDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365GetVerificationDnsRecordsRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365PromoteDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365SetDomainAsDefaultDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Requests\Microsoft365VerifyDomainRequest;
use Waterfront\Domain\Provision\Microsoft365\Results\CreateDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\DeleteDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\GetDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\PromoteDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\ServiceDnsRecordsResult;
use Waterfront\Domain\Provision\Microsoft365\Results\SetDomainAsDefaultDomainResult;
use Waterfront\Domain\Provision\Microsoft365\Results\VerificationDnsRecordsResult;
use Waterfront\Domain\Provision\Microsoft365\Results\VerifyDomainResult;
use Waterfront\Infra\Microsoft\Graph\Factory\GraphServiceClientFactory;

readonly class MicrosoftGraphService implements Microsoft365ProvisionServiceInterface
{
    public function __construct(
        private GraphServiceClientFactory $graphClientFactory,
    ) {
    }

    public function getDomain(Microsoft365GetDomainRequest $request): GetDomainResult
    {
        try {
            $domain = $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->byDomainId($request->domainName)
                ->get()
                ->wait();

            if ($domain === null) {
                throw new DomainNotFoundException($request->domainName);
            }

            return new GetDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                domain: $domain,
            );

            // @phpstan-ignore-next-line GraphClient doesn't have clear exceptions
        } catch (DomainNotFoundException|Exception $exception) {
            return new GetDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }

    public function verifyDomain(Microsoft365VerifyDomainRequest $request): VerifyDomainResult
    {
        try {
            $domain = $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->byDomainId($request->domainName)
                ->verify()
                ->post()
                ->wait();

            if ($domain === null || $domain->getIsVerified() !== true) {
                throw new DomainNotVerifiedException($request->domainName);
            }

            return new VerifyDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                domain: $domain,
            );

            // @phpstan-ignore-next-line GraphClient doesn't have clear exceptions
        } catch (DomainNotVerifiedException|Exception $exception) {
            return new VerifyDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }

    public function createDomain(Microsoft365CreateDomainRequest $request): CreateDomainResult
    {
        try {
            $requestBody = new Domain();
            $requestBody->setId($request->domainName);

            $createDomain = $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->post($requestBody)
                ->wait();

            if ($createDomain === null) {
                throw new DomainNotCreatedException($request->domainName);
            }

            return new CreateDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                domain: $createDomain,
            );

            // @phpstan-ignore-next-line GraphClient doesn't have clear exceptions
        } catch (DomainNotCreatedException|Exception $exception) {
            return new CreateDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }

    public function promoteDomain(Microsoft365PromoteDomainRequest $request): PromoteDomainResult
    {
        try {
            $promoted = $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->byDomainId($request->domainName)
                ->promote()
                ->post()
                ->wait();

            if ($promoted === null || $promoted->getValue() !== true) {
                throw new DomainNotPromotedException($request->domainName);
            }

            return new PromoteDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                isPromoted: true,
            );

            // @phpstan-ignore-next-line GraphClient doesn't have clear exceptions
        } catch (DomainNotPromotedException|Exception $exception) {
            return new PromoteDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                isPromoted: false,
                exception: $exception,
            );
        }
    }

    public function deleteDomain(Microsoft365DeleteDomainRequest $request): DeleteDomainResult
    {
        try {
            $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->byDomainId($request->domainName)
                ->delete()
                ->wait();

            return new DeleteDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                isDeleted: true,
            );

            // @phpstan-ignore thecodingmachine.exceptionMustBeRethrown
        } catch (Exception $exception) {
            return new DeleteDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                isDeleted: false,
                exception: $exception,
            );
        }
    }

    public function setDomainToDefaultDomain(Microsoft365SetDomainAsDefaultDomainRequest $request): SetDomainAsDefaultDomainResult
    {
        try {
            $requestBody = new Domain();
            $requestBody->setIsDefault(true);
            $requestBody->setSupportedServices(['Email']);

            $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->byDomainId($request->domainName)
                ->patch($requestBody)
                ->wait();

            return new SetDomainAsDefaultDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                isDefault: true,
            );

            // @phpstan-ignore-next-line GraphClient doesn't have clear exceptions
        } catch (DomainNotPromotedException|Exception $exception) {
            return new SetDomainAsDefaultDomainResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                isDefault: false,
                exception: $exception,
            );
        }
    }

    public function getServiceDnsRecords(Microsoft365GetServiceDnsRecordsRequest $request): ServiceDnsRecordsResult
    {
        try {
            $domainDnsRecordCollection = $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->byDomainId($request->domainName)
                ->serviceConfigurationRecords()
                ->get()
                ->wait();

            if (
                ! $domainDnsRecordCollection instanceof DomainDnsRecordCollectionResponse
                || $domainDnsRecordCollection->getValue() === null
            ) {
                throw new ServiceDnsRecordsNotFoundException($request->domainName);
            }

            return new ServiceDnsRecordsResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                records: $domainDnsRecordCollection->getValue(),
            );

            // @phpstan-ignore-next-line GraphClient doesn't have clear exceptions
        } catch (ServiceDnsRecordsNotFoundException|Exception $exception) {
            return new ServiceDnsRecordsResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }

    public function getVerificationDnsRecords(Microsoft365GetVerificationDnsRecordsRequest $request): VerificationDnsRecordsResult
    {
        try {
            $domainDnsRecordCollection = $this->graphClientFactory
                ->createForCustomer($request->context->toString())
                ->domains()
                ->byDomainId($request->domainName)
                ->verificationDnsRecords()
                ->get()
                ->wait();

            if (
                ! $domainDnsRecordCollection instanceof DomainDnsRecordCollectionResponse
                || $domainDnsRecordCollection->getValue() === null
            ) {
                throw new VerificationDnsRecordsNotFoundException($request->domainName);
            }

            return new VerificationDnsRecordsResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::SUCCESS,
                records: $domainDnsRecordCollection->getValue(),
            );

            // @phpstan-ignore-next-line GraphClient doesn't have clear exceptions
        } catch (VerificationDnsRecordsNotFoundException|Exception $exception) {
            return new VerificationDnsRecordsResult(
                provisionData: $request,
                provisionStatus: ProvisionStatus::FAILED,
                exception: $exception,
            );
        }
    }
}
