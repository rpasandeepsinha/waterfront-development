<?php

declare(strict_types=1);

namespace Waterfront\Apps\Console\Commands\Debug;

use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use JsonException;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Waterfront\Domain\DNS\DnsService;
use Waterfront\Domain\DNS\Entities\ChangedDnsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\ARecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Exceptions\DnsDeploymentNotFoundException;
use Waterfront\Domain\DNS\Exceptions\DnsZoneNotFoundException;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\DNS\Services\DnsNameserverAssigner;
use Waterfront\Infra\PowerDnsClient\Exceptions\PdnsResponseException;

#[AsCommand(name: 'debug:pdns')]
#[Description('Run all functionality on the PDNS client for a given domain')]
#[Signature('debug:pdns {domain}')]
class PdnsCrud extends Command
{
    /**
     * @throws DnsZoneNotFoundException
     * @throws JsonException
     * @throws PdnsResponseException
     * @throws GuzzleException
     * @throws DnsDeploymentNotFoundException
     */
    public function handle(
        DnsService $dnsService,
        DnsNameserverAssigner $dnsNameserverAssigner,
        DnsDeploymentRepository $dnsDeploymentRepository
    ): int {
        /** @var string $domain */
        $domain = $this->argument('domain');

        $dnsDeployment = $dnsDeploymentRepository->getDnsDeploymentFromDomain($domain);

        if ($dnsDeployment === null) {
            throw new DnsDeploymentNotFoundException($domain);
        }

        $nameservers = $dnsDeploymentRepository->isNameserversAlreadyAssigned($dnsDeployment)
            ? $dnsDeploymentRepository->getNameservers($dnsDeployment)
            : $dnsNameserverAssigner->assign($dnsDeployment);

        $dnsZone = $dnsService->createDnsZone(
            domain: $domain,
            nameservers: $nameservers
        );

        $this->info('Created DNS zone:');
        $this->info(json_encode($dnsZone->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $this->info('---------------------------');

        $this->info('Adding an A record:');
        $dnsService->addDnsRecord(
            $domain,
            new ARecord(
                name: 'test.' . $domain,
                content: '127.0.0.1',
                ttl: 900
            )
        );
        $freshZone = $dnsService->getDnsZone($domain);
        $this->info(json_encode($freshZone->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $this->info('---------------------------');

        $this->info('Deleting record from zone:');
        /** @var ARecord $recordToRemove */
        $recordToRemove = $freshZone->getRecordOfType(DnsRecordType::A->value);
        $this->info(json_encode($recordToRemove->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $dnsService->removeDnsRecord($domain, $recordToRemove);
        $this->info('-------------------------');

        $this->info('Zone after removing record:');
        $freshZone = $dnsService->getDnsZone($domain);
        $this->info(json_encode($freshZone->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $this->info('-------------------------');

        $this->info('Re-adding record again:');
        $dnsService->addDnsRecord($domain, $recordToRemove);
        $freshZone = $dnsService->getDnsZone($domain);
        $this->info(json_encode($freshZone->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $this->info('-------------------------');

        $this->info('Change existing record This removes the localhost A record:');

        $dnsService->updateRecord(
            $domain,
            new ChangedDnsRecord(
                $recordToRemove,
                new DefaultRecord(
                    $recordToRemove->getType(),
                    'changed.' . $domain,
                    $recordToRemove->getContent(),
                    $recordToRemove->getTtl()
                )
            )
        );

        $this->info('Zone after changing record:');
        $freshZone = $dnsService->getDnsZone($domain);
        $this->info(json_encode($freshZone->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $this->info('-----------------------');

        $this->info('Enable DNSSEC and Zone Keys');
        $dnsService->enableDnssec($domain);
        $keys = $dnsService->getDnsZoneKey($domain);
        $this->info(json_encode($keys->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        $this->info('------------------------');

        $this->info('Disable DNSSEC and Zone Keys');
        $dnsService->disableDnssec($domain);
        $this->info('------------------------');

        $this->info('Set kind to Master and set SOA_EDIT + SOA_EDIT_API');
        $dnsService->changeToMasterAndEmptyMasters($domain);
        $this->info('------------------------');

        try {
            $dnsService->getDnsZoneKey($domain);
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'Unable to acquire DNSSEC key for given type: csk') {
                throw new RuntimeException('Fetching DNSSEC after disabling did not throw the expected error.', previous: $exception);
            }
            $this->info('The Keys no longer can be fetched as expected after disabling!');
        }
        $this->info('------------------------');

        $this->info('DONE!!!');
        return self::SUCCESS;
    }
}
