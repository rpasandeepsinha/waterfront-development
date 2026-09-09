<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Ssl;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Domain\Ssl\Jobs\UpdateSslExpireDate;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Ssl\Repositories\DeploymentRepository;
use Waterfront\Support\Enums\LoggingContextKeys;

class NovaFillSslDeploymentExpireDateAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'fill-ssl-deployments-expire-date';

    public function __construct(
        private readonly Dispatcher $dispatcher,
        private readonly LoggerInterface $logger,
        private readonly DeploymentRepository $sslDeploymentRepository,
    ) {
        parent::__construct();
    }

    /** @return array<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Debug', 'debug')
                ->default(true)
                ->help('When enabled, only returns how many SSL deployments still have a missing expire_date.'),

            Number::make('Limit', 'limit')
                ->default(10)
                ->min(0)
                ->help('For gradual rollout: try 10 first, then 0 for remaining.'),

            Number::make('Missing expire_date (estimate)')
                ->default(
                    fn () => $this->sslDeploymentRepository
                        ->getExpireDateBackfillCandidates()
                        ->count()
                )
                ->readonly(),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse
    {
        $isDebug = $fields->boolean('debug');
        $limit = $fields->integer('limit');

        $this->logger->debug(
            sprintf('Executing one-time script %s', $this->getOneOffScriptSlug()),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
                LoggingContextKeys::META => [
                    'debug' => $isDebug,
                    'limit' => $limit,
                ],
            ]
        );

        $this->registerExecution();

        if ($isDebug) {
            $sslDeployments = $this->sslDeploymentRepository->getExpireDateBackfillCandidates()->count();

            $this->logger->info('SSL expire_date backfill debug summary', [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
                LoggingContextKeys::META => [
                    'debug' => true,
                    'limit' => $limit,
                    'missing_expire_date' => $sslDeployments,
                ],
            ]);

            return self::message(sprintf('Debug: %d SSL deployment(s) missing expire_date.', $sslDeployments));
        }

        $queued = $this->dispatchUpdateJobs($limit);

        $this->logger->info('SSL expire_date backfill queued jobs', [
            LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
            LoggingContextKeys::META => [
                'debug' => false,
                'limit' => $limit,
                'queued' => $queued,
            ],
        ]);

        return self::message(sprintf('Queued %d UpdateSslExpireDate job(s).', $queued));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16210';
    }

    private function dispatchUpdateJobs(int $limit): int
    {
        $queued = 0;

        $this->sslDeploymentRepository
            ->getExpireDateBackfillCandidates()
            ->orderBy('id')
            ->chunkById(
                500,
                function ($sslDeployments) use (&$queued, $limit): bool {
                    /** @var SslDeployment $sslDeployment */
                    foreach ($sslDeployments as $sslDeployment) {
                        if ($limit > 0 && $queued >= $limit) {
                            return false;
                        }

                        $this->dispatcher->dispatch(new UpdateSslExpireDate($sslDeployment));

                        $queued++;
                    }

                    return true;
                }
            );

        return $queued;
    }
}
