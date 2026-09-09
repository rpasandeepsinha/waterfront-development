<?php

declare(strict_types=1);

namespace Waterfront\Domain\Provision\Interfaces;

/**
 * The implementation of this interface depends on the type of provision service.
 * For hosting there will be other implementations then for domain registration.
 * This interface is a collection of those services and is used as return type
 * on the ProvisionServiceFactoryInterface implementations.
 */
interface TypeProvisionServiceInterface
{
}
