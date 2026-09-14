<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\General\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Http\Requests\ResourceIndexRequest;
use Laravel\Nova\Resource as NovaResource;
use Waterfront\Apps\Nova\General\Actions\NovaNoActionsPlaceholderAction;
use Waterfront\Infra\Translation\TranslatorInterface;

/**
 * @property-read Model $resource
 *
 * @extends NovaResource<Model>
 */
abstract class Resource extends NovaResource
{
    abstract public static function getTranslationKey(): string;

    // Prevent table row clicking from going to resource.
    public static function clickAction(): string
    {
        return 'ignore';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        if (! $request->has('orderBy') || $request->input('orderBy') === '') {
            $query->getQuery()->orders = [];

            return $query->orderBy('id', 'desc');
        }

        return $query;
    }

    public static function label(): string
    {
        return self::translate(static::getTranslationKey() . '.plural');
    }

    public static function singularLabel(): string
    {
        return self::translate(static::getTranslationKey() . '.singular');
    }

    final public function isResourceIndexRequest(Request $request): bool
    {
        return $request instanceof ResourceIndexRequest;
    }

    public static function translate(string $translationKey): string
    {
        return resolve(TranslatorInterface::class)->translate($translationKey);
    }

    public function authorizedToReplicate(Request $request): bool
    {
        return false;
    }

    /**
     * By always giving back at least one action (our "NoActionsPlaceholder"),
     * even if there are no actions (that can be seen or defined). We
     * standardize our index pages by always showing the checkboxes on
     * every index. And also prevent the bug that the checkboxes can disappear
     * after a selection was made (WATER-5564).
     *
     */
    public function availableActionsOnIndex(NovaRequest $request): ActionCollection
    {
        /** @var ActionCollection<int, Action> $actions */
        $actions = parent::availableActionsOnIndex($request);

        if ($actions->count() === 0) {
            /** @var ActionCollection<int, Action> $actions */
            $actions = ActionCollection::make(
                [resolve(NovaNoActionsPlaceholderAction::class)],
            );

            return $actions->values();
        }

        return $actions->values();
    }
}
