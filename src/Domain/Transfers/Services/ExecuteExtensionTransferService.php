<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Services;

use Illuminate\Support\Facades\Log;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Domains\DomainService;
use Waterfront\Domain\Domains\Exceptions\DomainModificationFailedException;
use Waterfront\Domain\Domains\Services\DomainContactService;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Exceptions\TransferException;
use Waterfront\Domain\Transfers\Interfaces\ExecuteExtensionTransferInterface;
use Waterfront\Support\Exceptions\NotImplementedException;
use Webmozart\Assert\Assert;

class ExecuteExtensionTransferService implements ExecuteExtensionTransferInterface
{
    public function __construct(
        private readonly DomainService $domainService,
        private readonly DomainContactService $domainContactService,
    ) {
    }

    public function execute(Subscription $subscription, Customer $receiver): void
    {
        $subscription->loadMissing('domainDeployment');

        $deployment = $subscription->domainDeployment;

        $domain = $subscription->domain;
        Assert::notNull($domain, 'Provided subscription has no domain');

        if (is_null($deployment)) {
            throw new TransferException(sprintf(
                'Unable to find domain subscription for domain {%s}.',
                $domain,
            ));
        }

        $owner = $this->domainContactService->findOrCreateDefaultOwner($receiver);

        try {
            $this->domainService->linkContactHandle(
                [
                    [
                        'domain' => $domain,
                        'type' => 'owner',
                    ],
                ],
                $owner,
            );
        } catch (DomainModificationFailedException|NotImplementedException $exception) {
            Log::error(
                sprintf(
                    'Error updating domain contact for domain: %s with message: %s',
                    $domain,
                    $exception->getMessage(),
                ),
            );
        }

        $deployment->contactOwner()->dissociate();
        $deployment->contactOwner()->associate($owner);
    }
}
