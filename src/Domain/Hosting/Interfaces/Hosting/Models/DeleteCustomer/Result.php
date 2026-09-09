<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Interfaces\Hosting\Models\DeleteCustomer;

use Laminas\Hydrator\ClassMethodsHydrator as Hydrator;
use Waterfront\Domain\Hosting\Interfaces\Hosting\Models\Result as BaseResult;

class Result extends BaseResult
{
    public static function create(array $data): Result
    {
        $data = array_filter($data);

        $hydrator = new Hydrator();

        return $hydrator->hydrate($data, new self());
    }
}
