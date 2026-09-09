<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Hosting\Actions;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Waterfront\Domain\Hosting\Actions\GetSsoUrlAction;
use Waterfront\Domain\Hosting\Exceptions\SsoResolveException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Hosting\Services\HostingService;
use Waterfront\Domain\MailManagement\Services\MailManagementService;
use Waterfront\Domain\Providers\Enums\ProviderSlug;
use Waterfront\Domain\Providers\Models\Provider;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Infra\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

class NovaGenerateSsoAction extends Action
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly HostingService $hostingService,
        private readonly MailManagementService $mailOnlyService,
        private readonly GetSsoUrlAction $getSsoUrlAction,
    ) {
        $this->sole();
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.generate_sso_action');
    }

    /**
     * @param Collection<int, HostingDeployment> $models
     *
     * @throws SsoResolveException
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $hostingDeployment = $models->firstOrFail();
        $redirectToMail = $hostingDeployment->mailOnlyServer !== null;
        $provider = $hostingDeployment->mailProvider ?? $hostingDeployment->provider;

        if ($provider === null) {
            return self::danger(
                $this->translator->translate('nova.general_actions_relevant_provider_hosting_not_found')
            );
        }

        $ssoUrl = $this->getSsoUrl($provider, $redirectToMail, $hostingDeployment);

        if ($ssoUrl === '') {
            return self::danger($this->translator->translate('nova-action.generate_sso_action_failed'));
        }

        return self::openInNewTab($ssoUrl);
    }

    private function getSsoUrl(
        Provider $provider,
        bool $redirectToMail,
        HostingDeployment|ResellerHostingDeployment $hostingDeployment,
    ): string {
        if ($provider->slug !== ProviderSlug::PLESK && $redirectToMail) {
            return $this->mailOnlyService->spamExpertsSso($hostingDeployment->subscription);
        }

        /** @var Request $request */
        $request = Container::getInstance()->make(Request::class);

        if ($hostingDeployment instanceof ResellerHostingDeployment) {
            $server = $hostingDeployment->server;
            Assert::notNull($server);
            return $this->getSsoUrlAction->execute(
                $server,
                $hostingDeployment->getRelevantUsernameAttribute(),
                $request->ip() ?? ''
            );
        }

        return $this->hostingService->getSsoUrl($hostingDeployment, $request->ip() ?? '', $redirectToMail);
    }
}
