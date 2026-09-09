<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\RemoveDuplicatedNameservers;

use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Support\Facades\DB;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Apps\OneOffScripts\Dns\RemoveDuplicatedNameserversJob;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaRemoveDuplicatedNamserversAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'remove-duplicated-nameservers';

    public function __construct(
        private readonly Dispatcher $jobDispatcher,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    /**
     * @return array<Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')
                ->withMeta(['value' => true]),
            Number::make('Batch amount', 'amount')->default(100),
        ];
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $dryRun = (bool) $fields['dry-run'];
        Assert::integerish($fields['amount']);
        $limit = (int) $fields['amount'];

        $mode = $dryRun ? 'dry-run' : 'execution';
        $this->logger->debug(
            sprintf(
                'Executing one-time script %s in %s mode',
                $this->getOneOffScriptSlug(),
                $mode
            ),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
            ]
        );

        $dnsDeploymentIdsWithDuplicatedNameservers = $this->getDnsDeploymentIdsWithDuplicatedNameservers($limit);

        if (! $dryRun) {
            foreach ($dnsDeploymentIdsWithDuplicatedNameservers as $dnsDeploymentId) {
                $this->jobDispatcher->dispatch(new RemoveDuplicatedNameserversJob($dnsDeploymentId));
            }

            return self::message(sprintf('Found %d dns deployments with duplicated nameservers. Removing duplicates will be done async', count($dnsDeploymentIdsWithDuplicatedNameservers)));
        }

        return self::message(sprintf('The dry run found %d dns deployments which still have to be unduplicated.', count($dnsDeploymentIdsWithDuplicatedNameservers)));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-9506';
    }

    /**
     * @return int[]
     */
    private function getDnsDeploymentIdsWithDuplicatedNameservers(int $limit): array
    {
        /** @var int[] $dnsDeploymentIds */
        $dnsDeploymentIds = DB::table('dns_deployment_dns_nameserver')
            ->select('dns_deployment_id')
            ->distinct()
            ->groupBy('dns_nameserver_id', 'dns_deployment_id')
            ->havingRaw('COUNT(*) > ?', [1])
            ->limit($limit)
            ->pluck('dns_deployment_id')
            ->toArray();

        return $dnsDeploymentIds;
    }
}
