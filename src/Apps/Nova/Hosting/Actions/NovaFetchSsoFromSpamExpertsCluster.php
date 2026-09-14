<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Support\Collection;
use JsonException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Throwable;
use Waterfront\Domain\Hosting\Models\SpamExpertsCluster;
use Waterfront\Infra\SpamExpertsClient\SpamExpertsClient;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFetchSsoFromSpamExpertsCluster extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly SpamExpertsClient $spamExpertsClient,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_sso_from_cluster');
    }

    /**
     * @param Collection<int, SpamExpertsCluster> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $cluster = $models->firstOrFail();
        $domain = (string) $fields->string('domain');

        $sso = null;
        $exceptions = [];

        try {
            $sso = sprintf(
                '%s/?authticket=%s',
                $cluster->hostname,
                $this->spamExpertsClient->generateSsoToken($domain, $cluster),
            );
        } catch (Throwable $exception) {
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        $data = [
            'sso' => $sso,
            'exceptions' => $exceptions,
        ];

        $title = sprintf(
            'Fetched SSO for domain {%s} from cluster hostname {%s} with response:',
            $domain,
            $cluster->hostname,
        );

        return self::modal('modal-response', [
            'title' => $title,
            'code' => json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            'size' => '7xl',
        ]);
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('domain', 'domain')->rules('required')->required(),
        ];
    }
}
