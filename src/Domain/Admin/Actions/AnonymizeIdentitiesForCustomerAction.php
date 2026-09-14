<?php

declare(strict_types=1);

namespace Waterfront\Domain\Admin\Actions;

use JsonException;
use Ramsey\Uuid\Uuid;
use Waterfront\Apps\API\Compass\Exceptions\AnonymizeCustomerException;
use Waterfront\Domain\Lighthouse\Actions\GetIdentitiesForCustomerNumberAction;
use Waterfront\Domain\Lighthouse\Actions\RemoveCustomerNumberFromIdentityAction;
use Waterfront\Domain\Lighthouse\Exceptions\DetachCustomerNumberFromIdentityFailedException;
use Waterfront\Domain\Lighthouse\Exceptions\FailedToFetchIdentitiesForCustomerNumbers;
use Waterfront\Domain\Lighthouse\Exceptions\ResourceNotFoundException;

class AnonymizeIdentitiesForCustomerAction
{
    public function __construct(
        private readonly GetIdentitiesForCustomerNumberAction $getIdentitiesForCustomerNumberAction,
        private readonly RemoveCustomerNumberFromIdentityAction $removeCustomerNumberFromIdentityAction,
        private readonly AnonymizeEmailHistoryForReceiverUuidAction $anonymizeEmailHistoryForReceiverUuidAction,
    ) {
    }

    /**
     * @throws AnonymizeCustomerException
     * @throws ResourceNotFoundException
     * @throws JsonException
     */
    public function execute(int $customerNumber): void
    {
        try {
            $identities = $this->getIdentitiesForCustomerNumberAction->execute($customerNumber);
        } catch (FailedToFetchIdentitiesForCustomerNumbers $exception) {
            throw new ResourceNotFoundException(
                'Failed to find identities for customer',
                $exception->getCode(),
                $exception,
            );
        }

        foreach ($identities as $identity) {
            $this->anonymizeEmailHistoryForReceiverUuidAction->execute(Uuid::fromString($identity->id));

            try {
                $this->removeCustomerNumberFromIdentityAction->execute(
                    Uuid::fromString($identity->id),
                    $customerNumber,
                );
            } catch (DetachCustomerNumberFromIdentityFailedException $exception) {
                throw AnonymizeCustomerException::failedToDetachCustomerNumberFromIdentity(
                    $customerNumber,
                    $identity->id,
                    $exception,
                );
            }
        }
    }
}
