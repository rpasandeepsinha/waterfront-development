<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\DTO;

use Symfony\Component\Serializer\Attribute\SerializedName;

class Nic
{
    /**
     * @param string[] $secondaryIp
     * @param string[] $extraDhcpOption
     */
    public function __construct(
        public string $id,
        #[SerializedName('networkid')]
        public string $networkId,
        #[SerializedName('networkname')]
        public string $networkName,
        public string $netmask,
        public string $gateway,
        #[SerializedName('ipaddress')]
        public string $ipAddress,
        #[SerializedName('isolationuri')]
        public string $isolationUri,
        #[SerializedName('broadcasturi')]
        public string $broadcastUri,
        #[SerializedName('traffictype')]
        public string $trafficType,
        public string $type,
        #[SerializedName('isdefault')]
        public bool $isDefault,
        #[SerializedName('macaddress')]
        public string $macAddress,
        #[SerializedName('ip6gateway')]
        public string $ip6Gateway,
        #[SerializedName('ip6cidr')]
        public string $ip6Cidr,
        #[SerializedName('ip6address')]
        public string $ip6Address,
        #[SerializedName('secondaryip')]
        public array $secondaryIp,
        #[SerializedName('extradhcpoption')]
        public array $extraDhcpOption,
        #[SerializedName('deviceid')]
        public string $deviceId,
    ) {
    }
}
