<?php

declare(strict_types=1);

namespace Database\Seeders\OneTimeScripts;

use Illuminate\Container\Container;
use Illuminate\Database\Seeder;
use Waterfront\Domain\DNS\Models\DnsNameserver;
use Waterfront\Domain\DNS\Models\DnsRegion;
use Waterfront\Domain\DNS\Repository\DnsDeploymentRepository;
use Waterfront\Domain\Domains\Models\DomainDeployment;
use Waterfront\Domain\Provision\DNS\Enums\NameserverType;
use Waterfront\Domain\Subscriptions\Models\Subscription;

/**
 * This seeder is created for the move of nameservers from DomainDeployment to DnsDeployment.
 * It is used to be able to run the MoveNameserverFromDomainDeploymentToDnsDeploymentAction
 * in the Nova admin panel from a local environment for testing purposes.
 *
 * This seeder is ment for manually use only.
 * php artisan db:seed --class=Database\\Seeders\\OneTimeScripts\\MoveNameserverSeeder
 *
 * It will be removed in https://yh-jira.atlassian.net/browse/SWD-7364.
 */
class MoveNameserverSeeder extends Seeder
{
    public function run(): void
    {
        $region = new DnsRegion();
        $region->name = uniqid() . 'TestRegion';
        $region->save();

        $dnsNameserver1 = new DnsNameserver();
        $dnsNameserver1->nameserver = uniqid() . 'ns1.test.com';
        $dnsNameserver1->dns_region_id = $region->id;
        $dnsNameserver1->save();

        $dnsNameserver2 = new DnsNameserver();
        $dnsNameserver2->nameserver = uniqid() . 'ns2.test.com';
        $dnsNameserver2->dns_region_id = $region->id;
        $dnsNameserver2->save();

        $dnsNameserver3 = new DnsNameserver();
        $dnsNameserver3->nameserver = uniqid() . 'ns3.test.com';
        $dnsNameserver3->dns_region_id = $region->id;
        $dnsNameserver3->save();

        /** @var DomainDeployment $domainDeployment */
        $domainDeployment = DomainDeployment::query()
            ->doesntHave('dnsNameservers') // in the current seeders this should always be the case, but let's be sure.
            ->with('subscription')
            ->firstOrFail();

        /** @var DnsDeploymentRepository $dnsDeploymentRepo */
        $dnsDeploymentRepo = Container::getInstance()->make(DnsDeploymentRepository::class);

        $dnsDeployment = $dnsDeploymentRepo->getDnsDeploymentFromDomainDeployment($domainDeployment);

        if ($dnsDeployment === null) { // in the current seeders this should always be the case, but let's be sure.
            /** @var Subscription $dnsSubscription */
            $dnsSubscription = Subscription::query()
                ->whereHas('product.productGroup', function ($query) {
                    $query->where('slug', 'dns');
                })
                ->whereDoesntHave('dnsDeployment')
                ->firstOrFail();

            $dnsDeploymentRepo->create($dnsSubscription->uuid, NameserverType::INTERNAL);

            $dnsSubscription->parent_subscription_id = $domainDeployment->subscription->id;
            $dnsSubscription->save();
        }

        $domainDeployment->dnsNameservers()->attach($dnsNameserver1);
        $domainDeployment->dnsNameservers()->attach($dnsNameserver2);
        $domainDeployment->dnsNameservers()->attach($dnsNameserver3);
    }
}
