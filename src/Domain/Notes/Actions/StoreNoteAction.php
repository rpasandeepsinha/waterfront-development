<?php

declare(strict_types=1);

namespace Waterfront\Domain\Notes\Actions;

use JsonException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Notes\Models\Notes;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\Authentication\Helpers\PermissionsHelper;

class StoreNoteAction
{
    public function __construct(private readonly AuthenticationManager $authenticationManager)
    {
    }

    /**
     * @throws JsonException
     */
    public function execute(
        string $noteMessage,
        Customer|Subscription $context,
    ): void {
        $notedBy = $this->authenticationManager->getAuthenticatedSubject();

        $notedByMetadata = [];

        $notedByMetadata['email'] = $notedBy->identitySchema->traits?->email;
        $notedByMetadata['schemaId'] = PermissionsHelper::getKratosSchemaId($notedBy);

        $note = new Notes();
        $note->note = $noteMessage;
        $note->noted_by_uuid = $notedBy->identitySchema->id;
        $note->noted_by_metadata = json_encode($notedByMetadata, JSON_THROW_ON_ERROR);

        if ($context instanceof Subscription) {
            $note->subscription_id = $context->id;
            $note->customer_id = $context->customer->id;
        } else {
            $note->customer_id = $context->id;
        }

        $note->save();
    }
}
