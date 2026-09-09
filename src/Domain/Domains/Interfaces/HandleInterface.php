<?php

declare(strict_types=1);

namespace Waterfront\Domain\Domains\Interfaces;

interface HandleInterface
{
    /**
     * @return string
     */
    public function getOwnerHandle();

    /**
     * @return string|null
     */
    public function getAdminHandle();

    /**
     * @return string|null
     */
    public function getTechHandle();

    /**
     * @return string|null
     */
    public function getBillingHandle();

    /**
     * @return array{owner?: string, admin?: string, tech?: string, billing?: string}
     */
    public function toArray();
}
