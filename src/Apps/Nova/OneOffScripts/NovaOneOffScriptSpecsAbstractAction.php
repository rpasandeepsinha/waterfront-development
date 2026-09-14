<?php

declare(strict_types=1);

namespace Waterfront\Apps\Nova\OneOffScripts;

use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Nova\OneOffScripts\DTO\OneTimeActionSpecs;
use Waterfront\Apps\Nova\OneOffScripts\Enums\Environments;
use Waterfront\Apps\Nova\OneOffScripts\Enums\SpecActions;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Models\ProductSpec;
use Waterfront\Domain\Products\Repositories\ProductSpecRepository;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

abstract class NovaOneOffScriptSpecsAbstractAction extends NovaOneOffScriptAbstractAction
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ConfigurationInterface $configuration,
        private readonly ProductSpecRepository $productSpecRepository,
    ) {
        parent::__construct();
    }

    /**
     * @param array<int, OneTimeActionSpecs> $specs
     */
    public function handleSpec(array $specs, bool $dryRun): string
    {
        $modalMessage = '<table class="w-full divide-y divide-gray-100 dark:divide-gray-700"><tr class="text-left px-6 whitespace-nowrap uppercase text-gray-500 text-xxs tracking-wide py-5"><th>Environment</th><th>Spec Name</th><th>Spec Value</th><th>Action</th><th>Product Slug</th><th>Result</th></tr>';
        $currentEnvironment = $this->configuration->getAsString('app.tenant');

        foreach ($specs as $spec) {
            foreach ($spec->environments as $environment) {
                if ($environment->value !== $currentEnvironment) {
                    $modalMessage .= $this->addResultRow(
                        $spec,
                        $environment,
                        '-',
                        sprintf('Environment %s does not match %s', $currentEnvironment, $environment->value),
                    );
                    continue;
                }

                foreach ($spec->productSlugs as $productSlug) {
                    $product = Product::where('slug', $productSlug)->first();

                    if (! $product instanceof Product) {
                        $modalMessage .= $this->addResultRow(
                            $spec,
                            $environment,
                            $productSlug,
                            'Product does not exist',
                        );
                        continue;
                    }

                    $productSpec = $this->productSpecRepository->findBySpecification($product, $spec->specName);

                    if ($productSpec instanceof ProductSpec && $spec->action === SpecActions::CREATE) {
                        $modalMessage .= $this->addResultRow(
                            $spec,
                            $environment,
                            $productSlug,
                            'Spec already exists on the product',
                        );
                        continue;
                    }

                    if (! $productSpec instanceof ProductSpec && $spec->action === SpecActions::DELETE) {
                        $modalMessage .= $this->addResultRow($spec, $environment, $productSlug, 'Spec is not present');
                        continue;
                    }

                    switch ($spec->action) {
                        case SpecActions::CREATE:
                            if (! $dryRun) {
                                $productSpec = new ProductSpec();
                                $productSpec->product_id = $product->id;
                                $productSpec->name = $spec->specName;
                                $productSpec->value = $spec->specValue;
                                $productSpec->save();
                            }

                            $modalMessage .= $this->addResultRow($spec, $environment, $productSlug, 'Created');
                            break;
                        case SpecActions::DELETE:
                            if (! $dryRun) {
                                $productSpec->delete();
                            }

                            $modalMessage .= $this->addResultRow($spec, $environment, $productSlug, 'Deleted');
                            break;
                        default:
                            $modalMessage .= $this->addResultRow($spec, $environment, $productSlug, 'Action not found');
                    }
                }
            }
        }

        return $modalMessage . '</table>';
    }

    public function handle(ActionFields $fields): ActionResponse|static
    {
        $dryRun = (bool) $fields['dry-run'];

        if ($this->isExecuted() && ! $dryRun) {
            return self::danger('This one-time action is not allowed to run multiple times');
        }

        $mode = $dryRun ? 'dry-run' : 'execution';
        $this->logger->debug(
            sprintf(
                'Executing one-time script %s in %s mode',
                $this->getOneOffScriptSlug(),
                $mode,
            ),
            [
                LoggingContextKeys::ONE_OFF_SCRIPT => $this->getOneOffScriptSlug(),
            ],
        );

        $specs = $this->getSpecs();

        $result = $this->handleSpec($specs, $dryRun);

        if (! $dryRun) {
            $this->registerExecution();
        }

        $this->oneOffScript->output_last_run = $result;
        $this->oneOffScript->save();

        return self::modal('modal-response', [
            'title' => 'OneTimeAction Result',
            'html' => $result,
            'size' => '7xl',
        ]);
    }

    /**
     * @return array<Field>
     */
    public function fields(NovaRequest $request): array
    {
        return [
            ...$this->getOneOffScriptInfoFields(),
            Boolean::make('Dry run', 'dry-run')->withMeta(['value' => true]),
        ];
    }

    public function isExecuted(): bool
    {
        return $this->oneOffScript->isExecuted();
    }

    /**
     * @return array<OneTimeActionSpecs>
     */
    abstract protected function getSpecs(): array;

    private function addResultRow(
        OneTimeActionSpecs $oneTimeActionSpec,
        Environments $environment,
        ?string $productSlug,
        string $message,
    ): string {
        return sprintf(
            '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
            $environment->value,
            $oneTimeActionSpec->specName,
            $oneTimeActionSpec->specValue,
            $oneTimeActionSpec->action->value,
            $productSlug,
            $message,
        );
    }
}
