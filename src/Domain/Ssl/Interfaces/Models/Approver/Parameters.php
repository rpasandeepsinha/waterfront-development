<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\Interfaces\Models\Approver;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;

/**
 * @see https://doc.openprovider.eu/API_Module_SSL_retrieveApproverEmailListSslCertRequest.
 */
class Parameters
{
    /** @var string */
    private $domain;

    /** @var int */
    private $productId;

    /** @var string[] */
    private static $requiredFields = [
        'domain',
        'productId',
    ];

    public static function create(array $data): Parameters
    {
        $data = array_filter($data);

        self::validateRequiredFields($data);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * @see https://doc.openprovider.eu/API_Format_SSL_Product_Ids.
     */
    public function setProductId(int|string $productId): void
    {
        $domain = $this->domain;

        Log::info(sprintf(
            'Attempting to create a ssl product with external id: %d for domain: %s',
            $productId,
            $domain
        ));

        $this->productId = intval($productId);
    }

    /**
     * @return int
     */
    public function getProductId()
    {
        return $this->productId;
    }

    /**
     * @param mixed[] $data
     */
    private static function validateRequiredFields(array $data): void
    {
        foreach (self::$requiredFields as $fieldName) {
            if (! array_key_exists($fieldName, $data)) {
                throw new InvalidArgumentException('Required field ' . $fieldName . ' is missing from the ssl approver data.');
            }
        }
    }
}
