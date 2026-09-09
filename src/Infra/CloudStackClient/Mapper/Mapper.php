<?php

declare(strict_types=1);

namespace Waterfront\Infra\CloudStackClient\Mapper;

/**
 * @template T
 */
interface Mapper
{
    /**
     * @param array<mixed> $data
     *
     * @return T
     */
    public function __invoke(array $data);
}
