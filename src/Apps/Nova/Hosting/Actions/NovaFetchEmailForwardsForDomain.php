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
use UnexpectedValueException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Repositories\HostingDeploymentRepository;
use Waterfront\Domain\Hosting\Services\HostingDeploymentService;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaFetchEmailForwardsForDomain extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly MailManagementService $mailOnlyService,
        private readonly HostingDeploymentRepository $hostingDeploymentRepository,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.fetch_forwards_for_domain_from_user');
    }

    /**
     * @param Collection<int, HostingDeployment> $models
     *
     * @throws JsonException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        /** @var string $domain */
        $domain = $fields->get('domain');

        $hostingDeployment = $models->firstOrFail();

        /** @var HostingDeploymentService $hostingDeploymentService */
        $hostingDeploymentService = resolve(HostingDeploymentService::class);

        if ($hostingDeployment->mailProvider()->exists()) {
            $identifier = $hostingDeploymentService->getMailUsername($hostingDeployment);
        } else {
            $identifier = $hostingDeploymentService->getUsername($hostingDeployment);
        }

        $server = $this->hostingDeploymentRepository->getServer($hostingDeployment);

        if ($identifier === null || $server === null) {
            return self::danger($this->translator->translate('nova-action.error.no_identifier_provided'));
        }

        $fetchedEmailForwards = [];
        $exceptions  = [];

        try {
            $relevantProviderSlug = $this->getRelevantProviderSlug($hostingDeployment);

            $emailForwards = $this->mailOnlyService->getEmailForwards(
                domain: $domain,
                server: $server,
                username: $identifier,
                providerSlug: ProviderSlug::from($relevantProviderSlug),
            );

            foreach ($emailForwards as $emailForward) {
                $fetchedEmailForwards[] = $emailForward->toArray($domain);
            }
        } catch (Throwable $exception) { // @phpstan-ignore-line
            $exceptions[] = [
                'message' => $exception->getMessage(),
                'previous' => $exception->getPrevious()?->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'code' => $exception->getCode(),
            ];
        }

        $data = [
            'mailForwards' => $fetchedEmailForwards,
            'exceptions' => $exceptions,
        ];

        $title = sprintf(
            'Fetched forward for domain {%s} for user {%s} from server with hostname {%s} with response:',
            $domain,
            $identifier,
            $server->hostname
        );

        return self::modal('modal-response', [
            'title' => $title,
            'code' => json_encode($data, JSON_PRETTY_PRINT),
            'size' => '7xl',
        ]);
    }

    /**
     * @return array<int, Text>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('domain', 'domain')
                ->rules('required')
                ->required(),
        ];
    }

    private function getRelevantProviderSlug(HostingDeployment $hostingDeployment): string
    {
        $mailProvider = $hostingDeployment->mailProvider()->first();
        $standardProvider = $hostingDeployment->provider;

        return $mailProvider instanceof Provider ?
            $mailProvider->slug->value :
            (
                $standardProvider instanceof Provider ?
                $standardProvider->slug->value :
                throw new UnexpectedValueException("Hosting deployment with ID {$hostingDeployment->id} does not have a known provider coupled. Check database.}")
            );
    }
}
