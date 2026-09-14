<?php

declare(strict_types=1);

namespace Waterfront\Domain\ManualProvisioning\Services;

use Ramsey\Uuid\Uuid;
use Waterfront\Domain\Email\Dto\Recipient;
use Waterfront\Domain\Mailer\IsMailable;
use Waterfront\Domain\Mailer\MailerInterface;
use Waterfront\Domain\ManualProvisioning\DTO\ProvisionDetails;
use Waterfront\Domain\ManualProvisioning\Mailer\Customer\ActivatedManualSubscriptionCustomer;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledManualSubscriptionEmployee;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\CanceledReminderManualSubscription;
use Waterfront\Domain\ManualProvisioning\Mailer\Employee\OrderedManualSubscriptionEmployee;
use Waterfront\Infra\Configuration\ConfigurationException;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Webmozart\Assert\Assert;

class MailNotificationService
{
    private readonly IsMailable $employeeRecipient;

    /**
     * @throws ConfigurationException
     */
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly ConfigurationInterface $configuration,
    ) {
        $this->validateMailConfig();

        $this->employeeRecipient = new Recipient(
            $this->configuration->getAsString('manual-provisioning.notification-receiver-name'),
            $this->configuration->getAsString('manual-provisioning.notification-email'),
            Uuid::fromString($this->configuration->getAsString('auth.console_identity_uuid')),
        );
    }

    public function sendCreationNotification(ProvisionDetails $data): void
    {
        $this->mailer->send(
            [$this->employeeRecipient],
            new OrderedManualSubscriptionEmployee(
                $data->getCustomerId(),
                $data->getCustomerFirstName(),
                $data->getCustomerLastName(),
                $data->getCustomerEmail(),
                $data->getProductName(),
                $data->getSubscriptionId(),
            ),
        );
    }

    public function sendTerminationNotification(ProvisionDetails $data): void
    {
        $this->mailer->send(
            [$this->employeeRecipient],
            new CanceledManualSubscriptionEmployee(
                $data->getCustomerId(),
                $data->getCustomerFirstName(),
                $data->getCustomerLastName(),
                $data->getCustomerEmail(),
                $data->getProductName(),
                $data->getSubscriptionId(),
            ),
        );
    }

    public function sendTerminationReminderNotification(ProvisionDetails $data): void
    {
        $this->mailer->send(
            [$this->employeeRecipient],
            new CanceledReminderManualSubscription(
                $data->getCustomerId(),
                $data->getCustomerFirstName(),
                $data->getCustomerLastName(),
                $data->getCustomerEmail(),
                $data->getProductName(),
                $data->getSubscriptionId(),
            ),
        );
    }

    public function sendActivationNotification(ProvisionDetails $data): void
    {
        $this->mailer->send(
            [$this->getCustomerRecipient($data)],
            new ActivatedManualSubscriptionCustomer(
                $data->getCustomerId(),
                $data->getCustomerFirstName(),
                $data->getCustomerLastName(),
                $data->getCustomerEmail(),
                $data->getProductName(),
                $data->getSubscriptionId(),
            ),
        );
    }

    /**
     * @throws ConfigurationException
     */
    private function validateMailConfig(): void
    {
        $customerSupportEmail = $this->configuration->getAsString('manual-provisioning.notification-email');
        $customerSupportName = $this->configuration->getAsString('manual-provisioning.notification-receiver-name');

        Assert::email($customerSupportEmail);
        Assert::stringNotEmpty($customerSupportName);
    }

    private function getCustomerRecipient(ProvisionDetails $data): Recipient
    {
        return new Recipient(
            $data->getCustomerFirstName(),
            $data->getCustomerEmail(),
            $data->getCustomerUuid(),
        );
    }
}
