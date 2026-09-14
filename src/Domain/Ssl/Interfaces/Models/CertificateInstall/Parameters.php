<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces\Models\CertificateInstall;

use InvalidArgumentException;
use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;

class Parameters
{
    /** @var string */
    private $name;

    /** @var string */
    private $domain;

    /** @var string */
    private $csr;

    /**
     * The private key.
     *
     * @var string
     */
    private $pvt;

    /** @var string */
    private $cert;

    /** @var string */
    private $certAuth;

    /** @var string[] */
    private static $requiredFields = [
        'domain',
        'csr',
        'pvt',
        'cert',
        'ca',
    ];

    public static function create(array $data): Parameters
    {
        $data = array_filter($data, fn (mixed $value): bool => (bool) $value);

        self::validateRequiredFields($data);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    public function getName(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }

        return $this->domain . '-certificate';
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getCsr(): string
    {
        return $this->csr;
    }

    public function setCsr(string $csr): void
    {
        $this->csr = $csr;
    }

    public function getPvt(): string
    {
        return $this->pvt;
    }

    public function setPvt(string $pvt): void
    {
        $this->pvt = $pvt;
    }

    public function getCert(): string
    {
        return $this->cert;
    }

    public function setCert(string $cert): void
    {
        $this->cert = $cert;
    }

    public function getCa(): string
    {
        return $this->certAuth;
    }

    public function setCa(string $certAuth): void
    {
        $this->certAuth = $certAuth;
    }

    /**
     * @return string[]
     */
    public function toArray(): array
    {
        $hydrator = new Hydrator();

        /** @var string[] $extracted */
        $extracted = $hydrator->extract($this);

        return $extracted;
    }

    /**
     * @param string[] $data
     *
     * @throws InvalidArgumentException
     */
    private static function validateRequiredFields(array $data): void
    {
        foreach (self::$requiredFields as $fieldName) {
            if (! array_key_exists($fieldName, $data)) {
                throw new InvalidArgumentException('Required field ' . $fieldName . ' is missing from the ssl data.');
            }
        }
    }
}
