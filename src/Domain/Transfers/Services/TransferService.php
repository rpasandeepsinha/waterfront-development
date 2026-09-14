<?php

declare(strict_types=1);

namespace Waterfront\Domain\Transfers\Services;

use Illuminate\Bus\Dispatcher;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use JsonException;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\Mailer\MailTemplateInterface;
use Waterfront\Domain\Products\Enums\ProductGroupType;
use Waterfront\Domain\Subscriptions\Jobs\TransferSubscriptions;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Transfers\Enums\TransferStatus;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferAcceptedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCancelledReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCancelledSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCompletedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferCreatedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferFailedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferFailedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferRejectedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferRejectedSender;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedReceiver;
use Waterfront\Domain\Transfers\Mailer\MailTransferStartedSender;
use Waterfront\Domain\Transfers\Models\Transfer;

class TransferService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Dispatcher $jobDispatcher,
    ) {
    }

    public function hasOpenTransfer(Subscription $subscription): bool
    {
        /**
         * A subscription is not allowed to be in multiple transfers with a status of completed, rejected, canceled, failed.
         * We can thus take the latest "open" transfer since there are no others.
         */
        $transfer = $subscription->transfers->sortByDesc('id')->first();

        return $transfer !== null && $transfer->isOpen();
    }

    /**
     * Determines the status of the transfer and dispatches the emails jobs.
     */
    public function sendNotificationMail(Transfer $transfer): void
    {
        switch ($transfer->getStatus()) {
            case TransferStatus::CANCELED:
                $this->sendEmailJobs(
                    new MailTransferCancelledSender($this->getDomains($transfer)),
                    new MailTransferCancelledReceiver($this->getDomains($transfer)),
                    $transfer->fromCustomer,
                    $transfer->toCustomer,
                );
                break;

            case TransferStatus::FAILED:
                $this->sendEmailJobs(
                    new MailTransferFailedSender($this->getDomains($transfer)),
                    new MailTransferFailedReceiver($this->getDomains($transfer)),
                    $transfer->toCustomer,
                    $transfer->fromCustomer,
                );
                break;

            case TransferStatus::COMPLETED:
                $this->sendEmailJobs(
                    new MailTransferCompletedSender($this->getDomains($transfer)),
                    new MailTransferCompletedReceiver($this->getDomains($transfer)),
                    $transfer->fromCustomer,
                    $transfer->toCustomer,
                );
                break;

            case TransferStatus::REJECTED:
                $this->sendEmailJobs(
                    new MailTransferRejectedSender($this->getDomains($transfer)),
                    new MailTransferRejectedReceiver($this->getDomains($transfer)),
                    $transfer->fromCustomer,
                    $transfer->toCustomer,
                );
                break;

            case TransferStatus::STARTED:
                $this->sendEmailJobs(
                    new MailTransferStartedSender($this->getDomains($transfer)),
                    new MailTransferStartedReceiver($this->getDomains($transfer)),
                    $transfer->toCustomer,
                    $transfer->fromCustomer,
                );
                break;

            case TransferStatus::ACCEPTED:
                $this->sendEmailJobs(
                    new MailTransferAcceptedSender($this->getDomains($transfer)),
                    new MailTransferAcceptedReceiver($this->getDomains($transfer)),
                    $transfer->toCustomer,
                    $transfer->fromCustomer,
                );
                break;

            case TransferStatus::REQUESTED:
                $this->sendEmailJobs(
                    new MailTransferCreatedSender($this->getDomains($transfer)),
                    new MailTransferCreatedReceiver($this->getDomains($transfer)),
                    $transfer->fromCustomer,
                    $transfer->toCustomer,
                );
        }
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function createTransfer(
        Collection $subscriptions,
        Customer $from,
        Customer $receiver,
    ): Transfer {
        if (! $this->validateSubscriptions($subscriptions, $from)) {
            $subscriptionIds = json_encode(
                $subscriptions->map(fn (Subscription $subscription): int => $subscription->id),
                JSON_THROW_ON_ERROR,
            );
            throw new InvalidArgumentException(
                "Subscription set: {$subscriptionIds} or initiating customer {$from->contact_name} is not allowed to transfer.",
            );
        }

        /**
         * @var Transfer $transfer
         */
        $transfer = Transfer::create([
            'from_customer_id' => $from->id,
            'to_customer_id' => $receiver->id,
        ]);
        $transfer->subscriptions()->saveMany($subscriptions);

        $transfer->load('subscriptions.product');

        $this->sendNotificationMail($transfer);

        return $transfer;
    }

    public function resolveReceiver(string $email, int $customerNumber): ?Customer
    {
        /**
         * @var Customer|null $customer
         */
        $customer = Customer::where('customer_number', $customerNumber)->first();

        if (! is_null($customer) && $this->isEmailRelatedToCustomer($email, $customer)) {
            return $customer;
        }

        return null;
    }

    /**
     * @param array<mixed> $subscriptionUuids
     *
     * @return Collection<int, Subscription>
     */
    public function resolveSubscriptions(array $subscriptionUuids, Customer $from): Collection
    {
        /** @var Collection<int, Subscription> $collection */
        $collection = new Collection();

        foreach ($subscriptionUuids as $subscriptionUuid) {
            $subscription = Subscription::where('uuid', $subscriptionUuid)->first();
            assert($subscription instanceof Subscription);
            $collection->add($subscription);
        }

        return $this->filterAvailableSubscriptions($collection, $from);
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     */
    public function validateSubscriptions(
        Collection $subscriptions,
        Customer $from,
    ): bool {
        /** @var bool $validated */
        $validated = $this->filterAvailableSubscriptions($subscriptions, $from)->pipe(
            fn (Collection $filtered): bool => $filtered->count() === $subscriptions->count(),
        );

        return $validated;
    }

    public function validateSubscriptionFromUuid(string $uuid, Customer $from): bool
    {
        /**
         * @var Subscription $subscription
         */
        $subscription = Subscription::query()->where('uuid', $uuid)->first();

        return $this->validateSubscription($subscription, $from);
    }

    public function retry(Transfer $transfer): void
    {
        if (! $transfer->isAccepted()) {
            throw new InvalidArgumentException(sprintf(
                'Cannot retry transferring a non-accepted transfer. Transfer ID: %s',
                $transfer->id,
            ));
        }

        $this->jobDispatcher->dispatch(new TransferSubscriptions($transfer));
    }

    /**
     * @return string[]
     */
    private function getDomains(Transfer $transfer): array
    {
        /** @var string[] $domains */
        $domains = $transfer
            ->subscriptions
            ->map(
                fn (Subscription $subscription): string => sprintf(
                    '%s (%s)',
                    $subscription->domain,
                    $subscription->product->name,
                ),
            )
            ->toArray();

        return $domains;
    }

    /**
     * Dispatches the email job for both sender and receiver.
     */
    private function sendEmailJobs(
        MailTemplateInterface $titleToSender,
        MailTemplateInterface $titleFromSender,
        Customer $sender,
        Customer $receiver,
    ): void {
        $this->mailer->send([$sender], $titleToSender);
        $this->mailer->send([$receiver], $titleFromSender);
    }

    private function isEmailRelatedToCustomer(string $email, Customer $customer): bool
    {
        return $email === $customer->email;
    }

    private function validateSubscription(Subscription $subscription, Customer $from): bool
    {
        return (
            $from->id === $subscription->customer_id
            && ! $this->hasOpenTransfer($subscription)
            && ! $this->subscriptionHasDnsTemplate($subscription)
            && $subscription->product->productGroup->slug !== ProductGroupType::VOLUME_DISCOUNT
            && $subscription->product->productGroup->slug !== ProductGroupType::MICROSOFT_365
        );
    }

    private function subscriptionHasDnsTemplate(Subscription $subscription): bool
    {
        return $subscription->domainDeployment?->template_id !== null;
    }

    /**
     * @param Collection<int, Subscription> $subscriptions
     *
     * @return Collection<int, Subscription>
     */
    private function filterAvailableSubscriptions(
        Collection $subscriptions,
        Customer $from,
    ): Collection {
        return $subscriptions->filter(
            fn (Subscription $subscription): bool => $this->validateSubscription($subscription, $from),
        );
    }
}
