<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Actions;

use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * This is a no action placeholder,
 * which can be used to prevent the checkboxes and the select (dropdown with actions) to disappear.
 *
 * An example why this is / was an issue:
 * The SubscriptionResource actions returns an empty array once there was a free DNS subscription selected.
 * The result was that the checkboxes (and dropdown) disappeared, leaving no option for the user
 * to deselect that subscription or select any other.
 * Only a hard refresh of the page (forcing nothing to be selected again) made the checkboxes appear again.
 *
 */
class NovaNoActionsPlaceholderAction extends Action
{
    public $name = 'no action available';

    public $withoutActionEvents = true;

    public $withoutConfirmation = true;

    public function __construct()
    {
        $this->exceptOnDetail();
    }

    /**
     * @param Collection<int, Subscription> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        return self::message('This is a fake action and will not execute anything');
    }
}
