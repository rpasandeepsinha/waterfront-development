<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models;

use InvalidArgumentException;
use Waterfront\Domain\Servers\Models\Server;
use Waterfront\Support\Traits\HydrateableTrait;

class Parameters
{
    use HydrateableTrait {
        toArray as private toHydrateArray;
    }

    public const string STATE_ON = 'ON';

    public const string STATE_OFF = 'OFF';

    public const int STATUS_ACTIVE = 0;

    public const int STATUS_BACKUP = 4;

    public const int STATUS_DISABLED_ADMIN = 16;

    public const int STATUS_DISABLED_RESELLER = 32;

    public const int STATUS_DISABLED_CUSTOMER = 64;

    public const int STATUS_EXPIRED = 256;

    private Server|null $server = null;

    /** @var string|null */
    private $companyName;

    /** @var string */
    private $contactPersonName;

    /** @var string */
    private $emailAddress;

    /** @var string */
    private $username;

    /** @var string */
    private $password;

    /** @var string */
    private $domain;

    /** @var string|null */
    private $ipv4Address;

    /** @var string|null */
    private $ipv6Address;

    private ?string $customerId = null;

    /** @var string|null */
    private $phpVersion;

    /** @var int */
    private $status = self::STATUS_ACTIVE;

    /** @var mixed[][] */
    private $specs = [];

    /** @var string|null */
    private $forwardingUrl;

    /** @var string|null */
    private $enableDns;

    /** @var string|null */
    private $enableSsl;

    /** @var string|null */
    private $enableSsh;

    /** @var string|null */
    private $enableLoginKeys;

    /** @var string|null */
    private $directAdminUserName;

    /** @var string */
    private $notify;

    /** @var string */
    private $package;

    /** @var string */
    private $featureSet = '';

    /** @var bool */
    private $mailOnlyHosting = false;

    /**
     * @return string[]
     */
    public static function getRequiredFields(): array
    {
        return [
            'contactPersonName',
            'emailAddress',
            'domain',
            'ipv4Address',
        ];
    }

    public function setCompanyName(string $companyName): void
    {
        $this->companyName = $companyName;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setContactPersonName(string $contactPersonName): void
    {
        $this->contactPersonName = $contactPersonName;
    }

    public function getContactPersonName(): string
    {
        return $this->contactPersonName;
    }

    public function setEmailAddress(string $emailAddress): self
    {
        $this->emailAddress = $emailAddress;

        return $this;
    }

    public function getEmailAddress(): string
    {
        return $this->emailAddress;
    }

    public function setUsername(string $username): void
    {
        $this->username = $username;
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setIpv4Address(string $ipv4Address): void
    {
        $this->ipv4Address = $ipv4Address;
    }

    public function getIpv4Address(): ?string
    {
        return $this->ipv4Address;
    }

    public function setIpv6Address(string $ipv6Address): void
    {
        $this->ipv6Address = $ipv6Address;
    }

    public function getIpv6Address(): ?string
    {
        return $this->ipv6Address;
    }

    public function setCustomerId(?string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function getCustomerId(): ?string
    {
        return $this->customerId;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setStatus(int $status): void
    {
        $validStatuses = [
            self::STATUS_ACTIVE,
            self::STATUS_BACKUP,
            self::STATUS_DISABLED_ADMIN,
            self::STATUS_DISABLED_RESELLER,
            self::STATUS_DISABLED_CUSTOMER,
            self::STATUS_EXPIRED,
        ];

        if (! in_array($status, $validStatuses, true)) {
            throw new InvalidArgumentException('The status is not valid.');
        }

        $this->status = $status;
    }

    /**
     * @return mixed[][]
     */
    public function getSpecs(): array
    {
        return $this->specs;
    }

    /**
     * @param mixed[][] $specs
     */
    public function setSpecs(array $specs): void
    {
        $this->specs = $specs;
    }

    public function getForwardingUrl(): ?string
    {
        return $this->forwardingUrl;
    }

    public function setForwardingUrl(string $forwardingUrl): void
    {
        $this->forwardingUrl = $forwardingUrl;
    }

    public function getPhpVersion(): ?string
    {
        return $this->phpVersion;
    }

    public function setPhpVersion(string $phpVersion): void
    {
        $this->phpVersion = $phpVersion;
    }

    public function getDirectAdminUserName(): ?string
    {
        return $this->directAdminUserName;
    }

    public function setDirectAdminUserName(string $directAdminUserName): void
    {
        $this->directAdminUserName = $directAdminUserName;
    }

    public function setEnableDns(bool|string $enableDns): self
    {
        if (is_bool($enableDns)) {
            $this->enableDns = $enableDns ? self::STATE_ON : self::STATE_OFF;
        } else {
            $this->enableDns = $enableDns;
        }

        return $this;
    }

    public function getEnableDns(): ?string
    {
        return $this->enableDns;
    }

    public function setEnableLoginKeys(bool|string $enableLoginKeys): self
    {
        if (is_bool($enableLoginKeys)) {
            $this->enableLoginKeys = $enableLoginKeys ? self::STATE_ON : self::STATE_OFF;
        } else {
            $this->enableLoginKeys = $enableLoginKeys;
        }

        return $this;
    }

    public function getEnableLoginKeys(): ?string
    {
        return $this->enableLoginKeys;
    }

    public function getEnableSsh(): ?string
    {
        return $this->enableSsh;
    }

    public function setEnableSsh(bool|string $enableSsh): self
    {
        if (is_bool($enableSsh)) {
            $this->enableSsh = $enableSsh ? self::STATE_ON : self::STATE_OFF;
        } else {
            $this->enableSsh = $enableSsh;
        }

        return $this;
    }

    public function getEnableSsl(): ?string
    {
        return $this->enableSsl;
    }

    public function setEnableSsl(bool|string $enableSsl): self
    {
        if (is_bool($enableSsl)) {
            $this->enableSsl = $enableSsl ? self::STATE_ON : self::STATE_OFF;
        } else {
            $this->enableSsl = $enableSsl;
        }

        return $this;
    }

    public function getNotify(): ?string
    {
        return $this->notify;
    }

    public function setNotify(bool|string $notify): Parameters
    {
        if (is_bool($notify)) {
            $this->notify = $notify ? 'yes' : 'no';
        } else {
            $this->notify = $notify;
        }

        return $this;
    }

    public function getPackage(): ?string
    {
        return $this->package;
    }

    public function setPackage(string $package): void
    {
        $this->package = $package;
    }

    public function setMailOnlyHosting(bool $mailOnlyHosting): void
    {
        $this->mailOnlyHosting = $mailOnlyHosting;
    }

    public function getMailOnlyHosting(): bool
    {
        return $this->mailOnlyHosting;
    }

    public function setFeatureSet(string $featureSet): void
    {
        $this->featureSet = $featureSet;
    }

    public function getFeatureSet(): string
    {
        return $this->featureSet;
    }

    /**
     * @return array{
     *     username: string,
     *     email: string,
     *     passwd: string,
     *     domain: string,
     *     ip: string|null,
     *     package: string|null,
     *     ssl_enabled: string|null
     * }
     */
    public function toDirectAdminUserSpecs(): array
    {
        return [
            'username' => $this->getUsername(),
            'email' => $this->getEmailAddress(),
            'passwd' => $this->getPassword(),
            'domain' => $this->getDomain(),
            'ip' => $this->getIpv4Address() ?? $this->getIpv6Address(),
            'package' => $this->getPackage(),
            'ssl_enabled' => $this->getEnableSsl(),
        ];
    }

    /**
     * @return mixed[]
     */
    public function toArray(bool $excludeUsernameAndPassword = true): array
    {
        $data = $this->toHydrateArray();
        if ($excludeUsernameAndPassword) {
            unset($data['password'], $data['username']);
        }

        return $data;
    }

    public function getServer(): ?Server
    {
        return $this->server;
    }

    public function setServer(?Server $server): void
    {
        $this->server = $server;
    }
}
