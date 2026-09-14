<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use JsonException;
use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use ValueError;
use Waterfront\Domain\DNS\DTO\DnsRecordChangeDTO;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Domain\DNS\Entities\DnsZone;
use Waterfront\Domain\DNS\Enums\DnsAgentType;
use Waterfront\Domain\DNS\Enums\DnsChangeType;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Repository\DnsRecordChangeRepository;
use Waterfront\Domain\Subscriptions\Exceptions\SubscriptionNotFoundException;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Repositories\SubscriptionRepository;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\DTO\AuthenticatedCustomer;
use Waterfront\Infra\Authentication\DTO\AuthenticatedEmployee;
use Waterfront\Infra\Authentication\DTO\AuthenticatedSystem;
use Waterfront\Support\Helpers\SystemHelper;
use Webmozart\Assert\Assert;

class DnsLogService
{
    public const DEFAULT_TTL = 3600;

    public function __construct(
        private readonly DnsRecordChangeRepository $dnsRecordChangeRepository,
        private readonly SubscriptionRepository $subscriptionRepository,
        private readonly SystemHelper $systemHelper,
        private readonly AuthenticationManager $authenticationManager,
    ) {
    }

    /**
     * @throws SubscriptionNotFoundException
     * @throws JsonException
     */
    public function log(
        DnsRecordChangeDTO $dnsRecordChangeDTO,
        string $domain,
    ): void {
        // In DNS there might be a trailing '.' for FQDN, here we ensure we remove this to match our subscription.
        $trimmedDomain = rtrim($domain, '.');

        try {
            $subscription = $this->subscriptionRepository->getActiveDnsSubscription($trimmedDomain);
        } catch (ModelNotFoundException $exception) {
            throw new SubscriptionNotFoundException(
                sprintf(
                    'Cannot find subscription with domain %s for DNS log',
                    $trimmedDomain,
                ),
                $exception->getCode(),
                $exception,
            );
        }

        try {
            $authenticatedSubject = $this->authenticationManager->getAuthenticatedSubject();
        } catch (AuthenticationException) {
            // @ignoreException
            $authenticatedSubject = null;
        }

        if (
            $this->systemHelper->isRunningInConsole()
            || $authenticatedSubject === null
            || $authenticatedSubject instanceof AuthenticatedSystem
        ) {
            $this->logSystemDnsRecordChange($dnsRecordChangeDTO, DnsAgentType::SYSTEM, $subscription);

            return;
        }

        Assert::true($authenticatedSubject instanceof AuthenticatedCustomer
        || $authenticatedSubject instanceof AuthenticatedEmployee);

        $agentType = $authenticatedSubject->identitySchema->schemaId === SchemaId::EMPLOYEE
            ? DnsAgentType::CS_AGENT
            : DnsAgentType::CUSTOMER;

        $this->logUserDnsRecordChange(
            $dnsRecordChangeDTO,
            $agentType,
            $subscription,
            $this->systemHelper->getClientIp(),
            $authenticatedSubject,
        );
    }

    public function logSingleRecordOfDnsZone(DnsRecordInterface $record, string $domain, DnsChangeType $type): void
    {
        // The value error is if there is DnsRecordType we do not want to log
        try {
            $this->log(
                dnsRecordChangeDTO: $this->fillDnsRecordChangeDTO($record, $type),
                domain: $domain,
            );
        } catch (ValueError) {
            return;
        }
    }

    public function logMultipleRecordsOfDnsZone(DnsZone $dnsZone, string $domain, DnsChangeType $type): void
    {
        foreach ($dnsZone->getRecords() as $record) {
            $this->logSingleRecordOfDnsZone($record, $domain, $type);
        }
    }

    /**
     * @param DnsRecordInterface[] $records
     */
    public function logMultipleRecords(array $records, string $domain, DnsChangeType $type): void
    {
        foreach ($records as $record) {
            $this->logSingleRecordOfDnsZone($record, $domain, $type);
        }
    }

    private function fillDnsRecordChangeDTO(DnsRecordInterface $record, DnsChangeType $type): DnsRecordChangeDTO
    {
        $priority = null;
        $weight = null;
        $port = null;

        if ($record instanceof SrvRecord) {
            $priority = $record->getPriority();
            $weight = $record->getWeight();
            $port = $record->getPort();
        }

        if ($record instanceof MxRecord) {
            $priority = $record->getPriority();
        }

        return new DnsRecordChangeDTO(
            record_type: DnsRecordType::from($record->getType()),
            change_type: $type,
            name: $record->getName(),
            content: $record->getContent(),
            // Default TTL if it cannot be found
            // https://doc.powerdns.com/authoritative/settings.html#default-ttl
            ttl: $record->getTtl() ?? self::DEFAULT_TTL,
            priority: $priority,
            weight: $weight,
            port: $port,
        );
    }

    /**
     * @throws JsonException
     * @throws SubscriptionNotFoundException
     */
    private function logSystemDnsRecordChange(
        DnsRecordChangeDTO $dnsRecordChangeDTO,
        DnsAgentType $dnsAgentType,
        Subscription $subscription,
    ): void {
        $this->dnsRecordChangeRepository->createDnsRecordChange(
            $dnsRecordChangeDTO,
            $dnsAgentType,
            $subscription,
            '127.0.0.1',
        );
    }

    /**
     * @throws JsonException
     * @throws SubscriptionNotFoundException
     */
    private function logUserDnsRecordChange(
        DnsRecordChangeDTO $dnsRecordChangeDTO,
        DnsAgentType $dnsAgentType,
        Subscription $subscription,
        string $ip_address,
        AuthenticatedCustomer|AuthenticatedEmployee $authenticatedSubject,
    ): void {
        $this->dnsRecordChangeRepository->createDnsRecordChange(
            $dnsRecordChangeDTO,
            $dnsAgentType,
            $subscription,
            $ip_address,
            $authenticatedSubject,
        );
    }
}
