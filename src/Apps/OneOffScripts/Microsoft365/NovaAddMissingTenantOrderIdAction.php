<?php

declare(strict_types=1);

namespace Waterfront\Apps\OneOffScripts\Microsoft365;

use Illuminate\Contracts\Bus\Dispatcher;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\NovaOneOffScriptAbstractAction;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Support\Enums\LoggingContextKeys;
use Webmozart\Assert\Assert;

class NovaAddMissingTenantOrderIdAction extends NovaOneOffScriptAbstractAction
{
    public const string SLUG = 'add-missing-tenant-order-id';

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
            Number::make('Batch amount', 'amount')->default(500),
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
                LoggingContextKeys::META => ['dry-run' => $dryRun],
            ]
        );

        $customerInfos = Microsoft365CustomerInfo::query()
            ->whereNull('tenant_order_id')
            ->whereNotNull('kpn_customer_id')
            ->limit($limit)
            ->get();

        if ($customerInfos->isEmpty()) {
            return self::message('No active Microsoft365 customer infos found with missing tenant order id.');
        }

        if ($dryRun) {
            return self::message(sprintf(
                'Dry run found %d customer infos with missing tenant order id.',
                $customerInfos->count()
            ));
        }

        foreach ($customerInfos as $customerInfo) {
            $this->jobDispatcher->dispatch(new AddMissingTenantOrderIdJob(
                microsoft365CustomerInfo: $customerInfo,
            ));
        }

        $this->registerExecution();

        return self::message(sprintf(
            'Found %d customer infos with missing tenant order id. Updating will be done async.',
            $customerInfos->count()
        ));
    }

    protected function getOneOffScriptSlug(): string
    {
        return self::SLUG;
    }

    protected function getOneOffScriptTicketUrl(): string
    {
        return 'https://yh-jira.atlassian.net/browse/SWD-16754';
    }
}
