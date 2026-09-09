<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Listeners;

use Illuminate\Support\Facades\Log;
use SandwaveIo\Office365\Helper\XmlHelper;
use SimpleXMLElement;
use Waterfront\Domain\Microsoft365\Events\Microsoft365Webhook;
use Waterfront\Domain\Microsoft365\Helpers\Microsoft365Helper;
use Waterfront\Domain\Microsoft365\Models\Microsoft365CustomerInfo;
use Waterfront\Domain\Microsoft365\Models\Microsoft365Deployment;
use Waterfront\Domain\Microsoft365\Models\Microsoft365HttpLog;
use Waterfront\Domain\Subscriptions\Models\Subscription;
use Webmozart\Assert\Assert;

class Microsoft365WebhookLogListener
{
    public function handle(Microsoft365Webhook $event): void
    {
        $xml = XmlHelper::loadXML($event->getLog());

        if (! $xml instanceof SimpleXMLElement) {
            Log::error(sprintf(
                '%s::handle - Webhook for Microsoft 365 was not logged because the xml could not be converted to SimpleXMLElement. The log: %s',
                self::class,
                $event->getLog(),
            ));

            return;
        }

        $subscription_id = null;
        $kpn_customer_id = $this->getPropertyValue('CustomerId', $xml);
        $kpn_order_id = $this->getPropertyValue('OrderId', $xml);
        $partner_reference = $this->getPropertyValue('PartnerReference', $xml);

        if ($kpn_customer_id === '') {
            $kpn_customer_id = null;
        }

        if ($kpn_order_id === '') {
            $kpn_order_id = null;
        }

        if ($kpn_order_id !== null) {
            $kpn_order_id = str_replace('OID', '', $kpn_order_id);
            $subscription_id = Microsoft365Deployment::where('kpn_order_id', $kpn_order_id)->first()?->subscription->id;
        }

        if ($kpn_order_id === null && $kpn_customer_id !== null) {
            $microsoft365Deployment = Microsoft365CustomerInfo::where('kpn_customer_id', $kpn_customer_id)->first()?->microsoft365Deployments->first();

            // When we receive a customer creation response we don't have the kpn_customer_id yet. So we can't look it up.
            if ($microsoft365Deployment instanceof Microsoft365Deployment) {
                $subscription_id = $microsoft365Deployment->subscription->id;
            }
        }

        // Ensures that the subscription id is set for termination logs when the kpn_order_id has just been changed.
        if ($subscription_id === null) {
            $orderIdString = '<OrderId>' . $kpn_order_id . '</OrderId>';
            $microsoft365ModifyLog = Microsoft365HttpLog::where('xml_root_name', 'ModifyOrderQuantityResponse_V1')->where('log', 'LIKE', "%$orderIdString%")->first();

            if ($microsoft365ModifyLog instanceof Microsoft365HttpLog) {
                $subscription_id = $microsoft365ModifyLog->subscription_id;
            }
        }

        $log = new Microsoft365HttpLog();
        $log->log = $event->getLog();
        $log->xml_root_name = $xml->getName();
        $log->kpn_customer_id = $kpn_customer_id;
        $log->kpn_order_id = $kpn_order_id;
        $log->partner_reference = $partner_reference;
        $log->tenant_name = $this->getPropertyValue('TenantName', $xml);
        $log->subscription_id = $subscription_id;

        if ($log->kpn_customer_id !== null) {
            $log->kpn_customer_id = Microsoft365Helper::customerIdToStringWithPrefix($log->kpn_customer_id);
        }

        if ($log->kpn_customer_id === null && $log->subscription_id !== null) {
            $subscription = Subscription::findOrFail($log->subscription_id);
            $microsoft365Deployment = $subscription->microsoft365Deployment;
            Assert::isInstanceOf(
                $microsoft365Deployment,
                Microsoft365Deployment::class,
                'Deployment is not instance of Microsoft365Deployment'
            );
            $log->kpn_customer_id = $microsoft365Deployment->microsoft365CustomerInfo->kpn_customer_id;
        }

        if ($log->partner_reference !== null && $log->xml_root_name === 'NewCustomerDeclined_V1') {
            $partialPartnerReference = explode('-', $log->partner_reference);
            $microsoft365Customer = Microsoft365CustomerInfo::findOrFail(end($partialPartnerReference));
            $log->kpn_customer_id = $microsoft365Customer->kpn_customer_id;
        }

        if ($log->partner_reference !== null && $log->xml_root_name === 'OrderDeclined_V2') {
            $partialPartnerReference = explode('-', $log->partner_reference);
            $microsoft365Deployment = Microsoft365Deployment::find(end($partialPartnerReference));
            $microsoft365Customer = Microsoft365CustomerInfo::find(end($partialPartnerReference));
            $log->kpn_order_id = $kpn_order_id;
            $log->kpn_customer_id = $microsoft365Deployment?->microsoft365CustomerInfo->kpn_customer_id ?? $microsoft365Customer?->kpn_customer_id;
        }

        $log->save();
    }

    private function getPropertyValue(string $propertyName, SimpleXMLElement $xml): ?string
    {
        $nodes = (array) $xml->xpath('//' . $propertyName);

        if (count($nodes) === 0 || ! $nodes[0] instanceof SimpleXMLElement) {
            return null;
        }

        return (string) $nodes[0][0];
    }
}
