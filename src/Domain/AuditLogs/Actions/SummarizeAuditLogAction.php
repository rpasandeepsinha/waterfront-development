<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Actions;

use Illuminate\Support\Facades\Log;
use Waterfront\Domain\AuditLogs\DTO\AuditLogCloudstackChildSubscriptionsParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLogCustomerParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLogDeploymentParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLoggableIdentity;
use Waterfront\Domain\AuditLogs\DTO\AuditLogInvoiceParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLogMicrosoft365DeploymentParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLogSubscriptionMutationParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLogSubscriptionParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLogTranslationParameters;
use Waterfront\Domain\AuditLogs\DTO\AuditLogUnknownParameters;
use Waterfront\Domain\Customers\Models\Customer;
use Waterfront\Domain\Customers\Models\CustomerAddress;
use Waterfront\Domain\Customers\Models\CustomerContact;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\History\Models\Audit;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Invoices\Models\Invoice;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\ResellerHosting\Models\ResellerHostingDeployment;
use Waterfront\Domain\Ssl\Models\SslDeployment;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Waterfront\Domain\Subscriptions\Models\SubscriptionMutation;
use Waterfront\Domain\VPS\Models\ManagerDomainDeployment;
use Waterfront\Domain\VPS\Models\VirtualMachineDeployment;
use Waterfront\Domain\VPS\Models\VolumeDeployment;
use Waterfront\Infra\Translation\TranslatorInterface;
use Waterfront\Support\Enums\LoggingContextKeys;

class SummarizeAuditLogAction
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function execute(Audit $audit, ?AuditLoggableIdentity $identity): string
    {
        $auditableTranslationType = $this->getAuditableTranslationType($audit);

        $translationParameters = $this->findAuditTranslationParams($audit);

        $params = $translationParameters->getParameters($this->translator);

        $params = array_merge($params, [
            'actor' => $this->translator->translate(
                sprintf('audit-log-summary.actor.%s', $identity?->getIdentityType() ?? 'system'),
            ),
        ]);

        return ucfirst($this->translator->translate("audit-log-summary.$auditableTranslationType", $params));
    }

    /*
     * We're supporting old namespaces because models have moved, we don't have morphmapping yet.
     * In the audit log refactor we will start to support this so this can be less complex. (Ticket: WATER-4467)
     */
    private function findAuditTranslationParams(Audit $audit): AuditLogTranslationParameters
    {
        switch ($audit->auditable_type) {
            case Invoice::class:
                return new AuditLogInvoiceParameters($audit);
            case Subscription::class:
                return new AuditLogSubscriptionParameters($audit);
            case SubscriptionMutation::class:
                return new AuditLogSubscriptionMutationParameters($audit);
            case DomainDeployment::class:
            case "Modules\DomainService\Models\Subscription":
            case SslDeployment::class:
            case ResellerHostingDeployment::class:
            case "Waterfront\Domain\ResellerHosting\Models\ResellerHostingSubscription":
            case "Waterfront\Domain\Ssl\Models\SslSubscription":
            case "Waterfront\Domain\Domains\Models\DomainSubscription":
            case "Waterfront\Domain\Hosting\Models\HostingSubscription":
            case "App\Models\ResellerHostingSubscription":
            case "Modules\HostingService\Models\Subscription":
            case "Modules\SslService\Models\Subscription":
            case HostingDeployment::class:
                return new AuditLogDeploymentParameters($audit);
            case VirtualMachineDeployment::class:
            case VolumeDeployment::class:
            case "Waterfront\Domain\VPS\Models\VolumeSubscription":
            case "Waterfront\Domain\VPS\Models\VirtualMachineSubscription":
                return new AuditLogCloudstackChildSubscriptionsParameters($audit);
            case Microsoft365Deployment::class:
            case "Waterfront\Domain\Microsoft365\Models\Microsoft365Subscription":
                return new AuditLogMicrosoft365DeploymentParameters($audit);
            case "App\Models\Customer":
            case "Modules\Customer\Models\Customer":
            case Customer::class:
                return new AuditLogCustomerParameters('customer', $audit);
            case "App\Models\CustomerAddress":
            case "Modules\Customer\Models\CustomerAddress":
            case CustomerAddress::class:
                return new AuditLogCustomerParameters('address', $audit);
            case "Modules\Customer\Models\CustomerContact":
            case "App\Models\CustomerContact":
            case CustomerContact::class:
                return new AuditLogCustomerParameters('contact', $audit);
            case "App\Models\User":
                return new AuditLogCustomerParameters('user', $audit);

            default:
                // Don't throw an exception since we don't want a 500 if one audit log line cannot be translated.
                Log::warning('Summarize audit translation params not found', [
                    LoggingContextKeys::META => [
                        'audit_id' => $audit->id,
                        'auditable_type' => $audit->auditable_type,
                    ],
                ]);

                return new AuditLogUnknownParameters();
        }
    }

    /*
     * We're supporting old namespaces because models have moved, we don't have morphmapping yet.
     * In the audit log refactor we will start to support this so this can be less complex. (Ticket: WATER-4467)
     */
    private function getAuditableTranslationType(Audit $audit): string
    {
        switch ($audit->auditable_type) {
            case Subscription::class:
            case "Modules\DomainService\Models\Subscription":
            case "Modules\HostingService\Models\Subscription":
            case DomainDeployment::class:
            case HostingDeployment::class:
            case ResellerHostingDeployment::class:
            case "App\Models\ResellerHostingSubscription":
            case "Waterfront\Domain\ResellerHosting\Models\ResellerHostingSubscription":
            case SslDeployment::class:
            case Microsoft365Deployment::class:
            case "Waterfront\Domain\Microsoft365\Models\Microsoft365Subscription":
            case ManagerDomainDeployment::class:
            case VirtualMachineDeployment::class:
            case VolumeDeployment::class:
            case "Waterfront\Domain\VPS\Models\VolumeSubscription":
            case "Waterfront\Domain\VPS\Models\VirtualMachineSubscription":
            case "Waterfront\Domain\VPS\Models\ManagerDomainSubscription":
                return 'subscription';
            case SubscriptionMutation::class:
                return 'subscription-mutation';
            case "App\Models\Customer":
            case "Modules\Customer\Models\Customer":
            case Customer::class:
            case "App\Models\CustomerAddress":
            case "Modules\Customer\Models\CustomerAddress":
            case CustomerAddress::class:
            case "App\Models\CustomerContact":
            case "Modules\Customer\Models\CustomerContact":
            case CustomerContact::class:
            case "App\Model\User":
                return 'customer';

            default:
                // Just return the lowercase classname by default
                $exploded = explode('\\', $audit->auditable_type);
                $last = end($exploded);

                return strtolower($last);
        }
    }
}
