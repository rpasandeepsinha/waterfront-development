<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Enums;

enum CancellationStepType: string
{
    case START = 'start';
    case REASONS = 'reasons';
    case SUPPORT = 'support';
    case MUTATION_SUGGESTION = 'mutation-suggestion';
    case VALUE_LOSS_PREVENTION = 'value-loss-prevention';
    case CONFIRM_CANCELLATION = 'confirm-cancellation';
    case CONFIRM_MUTATION = 'confirm-mutation';
    case CONFIRM_PHONE = 'confirm-phone';
    case FEEDBACK = 'feedback';
}
