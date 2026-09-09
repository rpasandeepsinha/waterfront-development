<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\ActionEvents;

use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Actions\ActionResource;

/**
 * @template TActionModel of ActionEvent
 *
 * @extends ActionResource<TActionModel>
 */
class IdentityAwareActionResource extends ActionResource
{
    /**
     * The model the resource corresponds to.
     *
     * @var class-string<TActionModel>
     */
    public static $model = IdentityAwareActionEvent::class;
}
