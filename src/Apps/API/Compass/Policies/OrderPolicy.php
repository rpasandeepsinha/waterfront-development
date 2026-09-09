<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Compass\Policies;

use SandwaveIo\LighthouseAuthBase\Enum\SchemaId;
use Waterfront\Domain\Orders\Enums\OrderStatus;
use Waterfront\Domain\Orders\Models\OrderLineItem;
use Waterfront\Infra\Authentication\AuthenticationManager;

class OrderPolicy
{
    public function __construct(
        private readonly AuthenticationManager $authManager,
    ) {
    }

    /**
     * @return string[]
     */
    public function getAvailableCompassActionsForLineItem(OrderLineItem $orderLineItem): array
    {
        $actions = [];
        if ($this->authManager->getAuthenticatedSubject()->identitySchema->schemaId !== SchemaId::EMPLOYEE) {
            return $actions;
        }

        if ($this->canProcessLineItem($orderLineItem)) {
            $actions[] = 'processLineItem';
        }

        return $actions;
    }

    private function canProcessLineItem(OrderLineItem $orderLineItem): bool
    {
        if ($orderLineItem->processed_at !== null) {
            return false;
        }

        return ! in_array($orderLineItem->order->status, [OrderStatus::ON_HOLD, OrderStatus::ABUSE], true);
    }
}
