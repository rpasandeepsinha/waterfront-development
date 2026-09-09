<?php

declare(strict_types=1);

namespace Waterfront\Support\Providers;

use Illuminate\Support\ServiceProvider;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

abstract class BaseProvider extends ServiceProvider
{
    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @return T
     */
    final public function resolve(string $className)
    {
        $service = $this->app->get($className);
        assert($service instanceof $className);

        return $service;
    }
}
