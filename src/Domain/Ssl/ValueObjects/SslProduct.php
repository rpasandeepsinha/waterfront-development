<?php

declare(strict_types=1);

namespace Waterfront\Domain\Ssl\ValueObjects;

/**
 * Value object for all product ids.
 *
 * @see @see https://doc.openprovider.eu/API_Format_SSL_Product_Ids
 */
class SslProduct
{
    private const array REGULAR_SSL_IDS_OPEN_PROVIDER = [
        5,
        8,
        14,
        31,
        41,
    ];

    private const array WILDCARD_SSL_IDS_OPEN_PROVIDER = [
        6,
        11,
        17,
        23,
        26,
        32,
        42,
    ];

    private const array EXTENDED_SSL_IDS_OPEN_PROVIDER = [
        3,
        4,
        10,
        16,
        24,
        27,
        33,
        44,
    ];

    private const array REGULAR_SSL_IDS_RTR = [
        'ssl_geotrust',
        'ssl_geotrust_ov',
        'ssl_globalsign',
        'ssl_globalsign_alpha',
        'ssl_globalsign_ov',
        'ssl_sectigo',
        'ssl_sectigo_amt_ov',
        'ssl_sectigo_elite_ov',
        'ssl_sectigo_essential',
        'ssl_sectigo_gold_ov',
        'ssl_sectigo_ov',
        'ssl_sectigo_platinum_ov',
        'ssl_sectigo_premium_ov',
        'ssl_sectigo_pro_ov',
        'ssl_symantec_ov',
        'ssl_symantec_pro_ov',
        'ssl_thawte',
        'ssl_thawte_ov',
    ];

    private const array WILDCARD_SSL_IDS_RTR = [
        'ssl_geotrust_wc',
        'ssl_geotrust_wc_ov',
        'ssl_globalsign_alpha_wc',
        'ssl_globalsign_wc',
        'ssl_globalsign_wc_ov',
        'ssl_sectigo_wc',
        'ssl_sectigo_wc_ov',
        'ssl_symantec_wc_ov',
        'ssl_thawte_wc',
        'ssl_thawte_wc_ov',
    ];

    private const array EXTENDED_SSL_IDS_RTR = [
        'ssl_geotrust_ev',
        'ssl_globalsign_ev',
        'ssl_sectigo_ev',
        'ssl_symantec_ev',
        'ssl_symantec_pro_ev',
        'ssl_thawte_ev',
    ];

    private mixed $productId;

    public static function fromNative(mixed $value): self
    {
        $res = new self();
        $res->productId = is_numeric($value) ? (int) $value : $value;
        return $res;
    }

    public function toNative(): mixed
    {
        return $this->productId;
    }

    /**
     * Returns true if the ssl product is not a wildcard or extended SSL.
     */
    public function isRegularSsl(): bool
    {
        if (is_string($this->productId)) {
            return in_array($this->productId, self::REGULAR_SSL_IDS_RTR, true);
        }

        return in_array($this->productId, self::REGULAR_SSL_IDS_OPEN_PROVIDER, true);
    }

    /**
     * Returns true if the product id is a wild card SSL.
     */
    public function isWildcardSsl(): bool
    {
        if (is_string($this->productId)) {
            return in_array($this->productId, self::WILDCARD_SSL_IDS_RTR, true);
        }

        return in_array($this->productId, self::WILDCARD_SSL_IDS_OPEN_PROVIDER, true);
    }

    /**
     * Returns true if the product id is an extended SSL. Extended SSL can only be ordered by
     * companies.
     */
    public function isExtendedSsl(): bool
    {
        if (is_string($this->productId)) {
            return in_array($this->productId, self::EXTENDED_SSL_IDS_RTR, true);
        }

        return in_array($this->productId, self::EXTENDED_SSL_IDS_OPEN_PROVIDER, true);
    }
}
