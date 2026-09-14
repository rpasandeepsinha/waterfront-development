<?php

declare(strict_types=1);

namespace Waterfront\Domain\AuditLogs\Actions;

use JsonException;
use stdClass;
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

class GetAuditableNameAction
{
    /*
     * We're supporting old namespaces because models have moved, we don't have morphmapping yet.
     * In the audit log refactor we will start to support this so this can be less complex. (Ticket: WATER-4467)
     */
    public function execute(Audit $audit): ?string
    {
        switch ($audit->auditable_type) {
            case Invoice::class:
                /** @var Invoice $invoice */
                $invoice = $audit->auditable;

                return $invoice->description;
            case Subscription::class:
                /** @var Subscription $subscription */
                $subscription = $audit->auditable;

                return $subscription->domain;
            case "Waterfront\Domain\Microsoft365\Models\Microsoft365Subscription":
            case Microsoft365Deployment::class:
                /** @var Microsoft365Deployment $deployment */
                $deployment = $audit->auditable;

                return $deployment->microsoft365CustomerInfo->tenant_name;
            case DomainDeployment::class:
            case "Modules\DomainService\Models\Subscription":
            case "Modules\SslService\Models\Subscription":
            case "Waterfront\Domain\Ssl\Models\SslSubscription":
            case "Waterfront\Domain\Domains\Models\DomainSubscription":
            case "Waterfront\Domain\Hosting\Models\HostingSubscription":
            case SslDeployment::class:
            case ResellerHostingDeployment::class:
            case "Waterfront\Domain\ResellerHosting\Models\ResellerHostingSubscription":
            case "App\Models\ResellerHostingSubscription":
            case "Modules\HostingService\Models\Subscription":
            case SubscriptionMutation::class:
            case HostingDeployment::class:
                /** @var DomainDeployment|SslDeployment|ResellerHostingDeployment|HostingDeployment $deployment */
                $deployment = $audit->auditable;

                return $deployment->subscription->domain;
            case VirtualMachineDeployment::class:
            case VolumeDeployment::class:
            case "Waterfront\Domain\VPS\Models\VolumeSubscription":
            case "Waterfront\Domain\VPS\Models\VirtualMachineSubscription":
                /** @var VirtualMachineDeployment|VolumeDeployment $deployment */
                $deployment = $audit->auditable;

                return $deployment->managerDomainDeployment->domain_name;
            case ManagerDomainDeployment::class:
            case "Waterfront\Domain\VPS\Models\ManagerDomainSubscription":
                /** @var ManagerDomainDeployment $deployment */
                $deployment = $audit->auditable;

                return $deployment->domain_name;
            case "App\Models\Customer":
            case "Modules\Customer\Models\Customer":
            case Customer::class:
                /** @var Customer $customer */
                $customer = $audit->auditable;

                return $customer->first_name . ' ' . $customer->last_name;
            case "App\Models\User":
                try {
                    $metadata = json_decode($audit->identity_metadata ?? '', false, flags: JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    $metadata = null;
                }

                assert($metadata instanceof stdClass);

                return $metadata->email ?? 'unknown';
            case "Modules\Customer\Models\CustomerContact":
            case "App\Models\CustomerContact":
            case CustomerContact::class:
                /** @var CustomerContact $customerContact */
                $customerContact = $audit->auditable;

                return $customerContact->first_name . ' ' . $customerContact->last_name;
            case "App\Models\CustomerAddress":
            case "Modules\Customer\Models\CustomerAddress":
            case CustomerAddress::class:
                /** @var CustomerAddress $customerAddress */
                $customerAddress = $audit->auditable;

                return $customerAddress->customer->first_name . ' ' . $customerAddress->customer->first_name;
        }

        return null;
    }
}
