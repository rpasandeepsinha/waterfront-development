<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\Products\Actions;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Waterfront\Apps\Nova\Products\Resources\NovaProductResource;
use Waterfront\Domain\Domains\Factories\DomainServiceFactory;
use Waterfront\Domain\Domains\Services\PremiumDomainService;
use Waterfront\Infra\Translation\TranslatorInterface;

class NovaAddPremiumDomainProductAction extends Action
{
    public function __construct(
        private readonly DomainServiceFactory $domainServiceFactory,
        private readonly PremiumDomainService $premiumDomainProducts,
        private readonly TranslatorInterface $translator,
    ) {
        $this->confirmButtonText = $this->translator->translate('nova-action.add_premium_domain_product_confirm');
    }

    public function name(): string
    {
        return $this->translator->translate('nova-action.add_premium_domain_product');
    }

    /**
     * @param Collection<int, Model> $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse|static
    {
        $domainService = $this->domainServiceFactory->defaultDriver();
        assert(is_string($fields->get('domain')));
        $availability = $domainService->check((string) $fields->string('domain'));
        assert(is_numeric($fields->get('margin')));
        $margin = (int) $fields->get('margin');
        $product = $this->premiumDomainProducts->createProductPriceForPremiumDomain($availability, $margin);

        return self::redirect(sprintf('/nova/resources/%s/%s', NovaProductResource::uriKey(), $product->id));
    }

    /**
     * @return array<int, Text|Select>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            Text::make(
                $this->translator->translate('nova-action.add_premium_domain_product_domain'),
                'domain',
            )->required(),
            Select::make($this->translator->translate('nova-action.add_premium_domain_product_margin'), 'margin')
                ->options([
                    25 => '25%',
                ])
                ->default(25)
                ->required(),
        ];
    }

    /**
     * @throws ValidationException
     */
    protected function afterValidation(NovaRequest $request, Validator $validator): void
    {
        $domainService = $this->domainServiceFactory->defaultDriver();
        assert(is_string($request->input('domain')));
        $availability = $domainService->check($request->input('domain'));
        if ($availability->isPremium() === null) {
            throw ValidationException::withMessages([
                'domain' => $this->translator->translate('nova-action.error.domain_premium_unsupported'),
            ]);
        }

        if (! $availability->isPremium()) {
            throw ValidationException::withMessages([
                'domain' => $this->translator->translate('nova-action.error.domain_is_not_premium'),
            ]);
        }

        if ($this->premiumDomainProducts->doesProductExistForPremiumDomain($availability->getDomain())) {
            throw ValidationException::withMessages([
                'domain' => $this->translator->translate('nova-action.error.domain_premium_product_exists'),
            ]);
        }
    }
}
