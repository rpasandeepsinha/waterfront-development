<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Messages;

use Waterfront\Domain\Domains\DTO\Domain;
use Waterfront\Domain\Domains\DTO\Handles;
use Waterfront\Domain\Domains\DTO\RetrieveResult;
use Waterfront\Domain\Domains\Enums\DomainStatus;

class DomainGetResponse extends BaseResponse
{
    public function getResult(string $domain): RetrieveResult
    {
        $attributes = $this->getAttributes();
        $result = new RetrieveResult();

        $result->setDomain(new Domain($domain));
        $result->setStatus(DomainStatus::ACTIVE->value);

        $createDate = $this->stringAttribute('registry_createdate');
        if ($createDate !== null) {
            $result->setOrderDate($createDate);
            $result->setActiveDate($createDate);
        }

        $expireDate = $this->stringAttribute('expiredate');
        if ($expireDate !== null) {
            $result->setExpirationDate($expireDate);
        }

        $registryExpireDate = $this->stringAttribute('registry_expiredate');
        if ($registryExpireDate !== null) {
            $result->setExpirationDateOpenprovider($registryExpireDate);
        }

        $result->setAutoRenew(($attributes['auto_renew'] ?? '0') === '1');
        $result->setIsLocked(($attributes['lock_state'] ?? '0') === '1');
        $result->setIsPrivateWhoisEnabled(($attributes['whois_privacy_state'] ?? 'disabled') === 'enabled');
        $result->setIsDnssecEnabled(false);

        $authCode = $this->getAuthCode();
        if ($authCode !== null) {
            $result->setAuthCode($authCode);
        }

        $nameServers = $this->nameServers();
        if ($nameServers !== []) {
            $result->setNameServers($nameServers);
        }

        $handles = $this->handles();
        if ($handles !== null) {
            $result->setHandles($handles);
        }

        return $result;
    }

    public function getAuthCode(): ?string
    {
        $attributes = $this->getAttributes();

        foreach (['domain_auth_info', 'registrar_auth', 'auth_info'] as $key) {
            $value = $attributes[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function nameServers(): array
    {
        $list = $this->getAttributes()['nameserver_list'] ?? null;

        if (! is_array($list)) {
            return [];
        }

        $nameServers = [];
        foreach ($list as $nameServer) {
            $name = match (true) {
                is_string($nameServer) => $nameServer,
                is_array($nameServer) && is_string($nameServer['name'] ?? null) => $nameServer['name'],
                default => null,
            };

            if ($name !== null && $name !== '') {
                $nameServers[] = ['name' => $name];
            }
        }

        return $nameServers;
    }

    private function handles(): ?Handles
    {
        $contactSet = $this->getAttributes()['contact_set'] ?? null;

        if (! is_array($contactSet)) {
            return null;
        }

        $identifier = static function (mixed $contact): ?string {
            $email = is_array($contact) ? ($contact['email'] ?? null) : null;

            return is_string($email) && $email !== '' ? $email : null;
        };

        $owner = $identifier($contactSet['owner'] ?? null);
        if ($owner === null) {
            return null;
        }

        return new Handles(
            $owner,
            $identifier($contactSet['admin'] ?? null),
            $identifier($contactSet['tech'] ?? null),
            $identifier($contactSet['billing'] ?? null),
        );
    }

    private function stringAttribute(string $key): ?string
    {
        $value = $this->getAttributes()[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
