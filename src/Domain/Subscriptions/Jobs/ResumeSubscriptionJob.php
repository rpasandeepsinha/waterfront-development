<?php

declare(strict_types=1);

namespace Waterfront\Domain\Subscriptions\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Bus\Dispatcher;
use LogicException;
use Override;
use Psr\Log\LoggerInterface;
use UnexpectedValueException;
use Waterfront\Domain\Customers\Exceptions\InvalidCountryCodeException;
use Waterfront\Domain\Domains\Jobs\RestoreDomainJob;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Hosting\Jobs\UnsuspendHostingJob;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Invoices\DTO\OneTimeServiceContext;
use Waterfront\Domain\OneTimeServices\Enums\OneTimeServiceStatus;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceCreator;
use Waterfront\Domain\OneTimeServices\Services\OneTimeServiceInvoiceService;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Products\Models\Product;
use Waterfront\Domain\Products\Repositories\ProductRepository;
use Waterfront\Domain\Subscriptions\Actions\ResumeSubscriptionInGracePeriodAction;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Support\Enums\LoggingContextKeys;
use Waterfront\Support\Enums\QueueName;
use Waterfront\Support\Exceptions\NotImplementedException;
use Waterfront\Support\Jobs\AbstractQueueableJob;

class ResumeSubscriptionJob extends AbstractQueueableJob
{
    public function __construct(
        private readonly Subscription $subscription,
        private readonly bool $invoiceQuarantaineCosts,
    ) {
        parent::__construct();
    }

    public function handle(
        ResumeSubscriptionInGracePeriodAction $resumeSubscriptionInGracePeriodAction,
        ProductRepository $productRepository,
        OneTimeServiceCreator $oneTimeServiceCreator,
        OneTimeServiceInvoiceService $invoiceOneTimeServiceAction,
        Dispatcher $jobDispatcher,
        LoggerInterface $logger,
    ): void {
        try {
            $resumeSubscriptionInGracePeriodAction->execute($this->subscription);
        } catch (UnexpectedValueException $exception) {
            $this->fail($exception);

            return;
        }

        try {
            if (! is_null($this->subscription->domainDeployment) && $this->invoiceQuarantaineCosts) {
                $product = $this->findQuarantaineProduct($productRepository);

                $context = new OneTimeServiceContext(
                    $this->subscription,
                    $product,
                    1,
                    0,
                    OneTimeServiceStatus::DONE,
                    CarbonImmutable::now(),
                    'Resuming expired subscription, removing domain from quarantaine.',
                    null,
                );

                $oneTimeService = $oneTimeServiceCreator->createFromContextWithNote($context);

                $invoiceOneTimeServiceAction->createOneTimeServiceInvoices($oneTimeService);
            }
        } catch (LogicException|InvalidCountryCodeException $exception) {
            //@ignore-exception
            $logger->error('Failed to create billing or ots while resuming subscription ({subscription.id})', [
                LoggingContextKeys::SUBSCRIPTION_ID => $this->subscription->id,
                LoggingContextKeys::EXCEPTION => $exception,
            ]);
        }

        $this->unsuspendDeployment($jobDispatcher, $this->subscription);
    }

    #[Override]
    protected function getQueueName(): QueueName
    {
        return QueueName::SUBSCRIPTIONS;
    }

    private function findQuarantaineProduct(ProductRepository $productRepository): Product
    {
        $quarantaineProduct = $productRepository->getQuarantaineProduct();
        if (! $quarantaineProduct instanceof Product) {
            throw new LogicException("No quarantaine product found, can't create invoice.");
        }

        return $quarantaineProduct;
    }

    private function unsuspendDeployment(Dispatcher $jobDispatcher, Subscription $subscription): void
    {
        match ($subscription->product->productGroup->slug) {
            ProductGroupType::EXTENSION => $jobDispatcher->dispatch($this->getDomainRestoreJob($subscription)),
            ProductGroupType::HOSTING => $jobDispatcher->dispatch($this->getUnsuspendHostingJob($subscription)),
            default => throw new NotImplementedException(sprintf(
                'Products with product group "%s" can not be unsuspended.',
                $subscription->product->productGroup->slug->value,
            )),
        };
    }

    private function getDomainRestoreJob(Subscription $subscription): RestoreDomainJob
    {
        $domainDeployment = $subscription->domainDeployment;
        assert($domainDeployment instanceof DomainDeployment);

        return new RestoreDomainJob($domainDeployment);
    }

    private function getUnsuspendHostingJob(Subscription $subscription): UnsuspendHostingJob
    {
        $hostingDeployment = $subscription->hostingDeployment;
        assert($hostingDeployment instanceof HostingDeployment);

        return new UnsuspendHostingJob($hostingDeployment, sendEmailOnSuccess: false);
    }
}
