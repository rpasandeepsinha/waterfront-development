<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

class Network
{
    /**
     * @param NetworkService[] $service List of services provided by the network.
     * @param Tag[]            $tags    A list of tags associated with the network.
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $state,
        public string $type,
        public string $gateway,
        public string $netmask,
        public string $cidr,
        public string $domain,
        public string $created,
        #[SerializedName('acltype')]
        public string $aclType,
        #[SerializedName('broadcastdomaintype')]
        public string $broadcastDomainType,
        #[SerializedName('canusefordeploy')]
        public bool $canUseForDeploy,
        #[SerializedName('displaytext')]
        public string $displayText,
        public string $dns1,
        public string $dns2,
        #[SerializedName('domainid')]
        public string $domainId,
        #[SerializedName('domainpath')]
        public string $domainPath,
        #[SerializedName('hasannotations')]
        public bool $hasAnnotations,
        #[SerializedName('ip6cidr')]
        public string $ip6Cidr,
        #[SerializedName('ip6dns1')]
        public string $ip6Dns1,
        #[SerializedName('ip6dns2')]
        public string $ip6Dns2,
        #[SerializedName('ip6gateway')]
        public string $ip6Gateway,
        #[SerializedName('ispersistent')]
        public bool $isPersistent,
        #[SerializedName('issystem')]
        public bool $isSystem,
        #[SerializedName('networkdomain')]
        public string $networkDomain,
        #[SerializedName('networkofferingavailability')]
        public string $networkOfferingAvailability,
        #[SerializedName('networkofferingconservemode')]
        public bool $networkOfferingConserveMode,
        #[SerializedName('networkofferingdisplaytext')]
        public string $networkOfferingDisplayText,
        #[SerializedName('networkofferingid')]
        public string $networkOfferingId,
        #[SerializedName('networkofferingname')]
        public string $networkOfferingName,
        #[SerializedName('physicalnetworkid')]
        public string $physicalNetworkId,
        #[SerializedName('receivedbytes')]
        public int $receivedBytes,
        #[SerializedName('redundantrouter')]
        public bool $redundantRouter,
        public string $related,
        #[SerializedName('restartrequired')]
        public bool $restartRequired,
        #[SerializedName('sentbytes')]
        public int $sentBytes,
        public array $service,
        #[SerializedName('specifyipranges')]
        public bool $specifyIpRanges,
        #[SerializedName('specifyvlan')]
        public bool $specifyVlan,
        #[SerializedName('strechedl2subnet')] // Note: JSON key has a typo
        public bool $stretchedL2Subnet,
        #[SerializedName('subdomainaccess')]
        public bool $subdomainAccess,
        #[SerializedName('supportsvmautoscaling')]
        public bool $supportsVmAutoscaling,
        public array $tags,
        #[SerializedName('traffictype')]
        public string $trafficType,
        #[SerializedName('zoneid')]
        public string $zoneId,
        #[SerializedName('zonename')]
        public string $zoneName,
    ) {
    }
}
