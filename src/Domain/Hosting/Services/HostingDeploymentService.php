<?php

declare(strict_types=1);

namespace Waterfront\Domain\Hosting\Services;

use RuntimeException;
use Waterfront\Domain\Hosting\Models\HostingDeployment;
use Waterfront\Domain\Providers\Enums\ProviderSlug;

class HostingDeploymentService
{
    public function getUsername(HostingDeployment $hostingDeployment): ?string
    {
        $slug = $hostingDeployment->provider?->slug;

        switch ($slug) {
            case ProviderSlug::PLESK:
                return $hostingDeployment->plesk_customer_username;
            case ProviderSlug::DIRECTADMIN:
                return $hostingDeployment->directadmin_customer_username;
            case ProviderSlug::PLACEHOLDER:
                return null;
            default:
                break;
        }

        /**
         * Override the relation as it is not available when creating an instance.
         */
        if ($hostingDeployment->subscription !== null) {
            if ($hostingDeployment->subscription->product->isMailOnlyServer()) {
                $username = $this->getMailUsername($hostingDeployment);
                if ($username !== null) {
                    return $username;
                }
            }

            if ($hostingDeployment->subscription->product->isSitebuilderProduct()) {
                $username = $hostingDeployment->basekitServer->username ?? null;
                if ($username !== null) {
                    return $username;
                }
            }
        }

        throw new RuntimeException(sprintf(
            'HostingDeployment: cannot perform action on unsupported provider %s: fetching matching username',
            $slug?->value,
        ));
    }

    public function getMailUsername(HostingDeployment $hostingDeployment): ?string
    {
        $slug = $this->getMailProviderSlug($hostingDeployment);

        return match ($slug) {
            ProviderSlug::DIRECTADMIN => $hostingDeployment->directadmin_customer_username,
            ProviderSlug::PLESK => $hostingDeployment->plesk_customer_username,
            ProviderSlug::PLACEHOLDER => null,
            default => throw new RuntimeException(sprintf(
                'HostingDeployment: cannot perform action on unsupported provider %s: fetching matching mail username',
                $slug?->value,
            )),
        };
    }

    public function setMailUsername(HostingDeployment $hostingDeployment, string $username): void
    {
        $slug = $this->getMailProviderSlug($hostingDeployment);

        switch ($slug) {
            case ProviderSlug::DIRECTADMIN:
                $hostingDeployment->directadmin_customer_username = $username;
                break;
            case ProviderSlug::PLESK:
                $hostingDeployment->plesk_customer_username = $username;
                break;
            default:
                throw new RuntimeException(sprintf(
                    'HostingDeployment: cannot perform action on unsupported provider %s: setting mail username: %s',
                    $slug?->value,
                    $username,
                ));
        }

        $hostingDeployment->save();
    }

    private function getMailProviderSlug(HostingDeployment $hostingDeployment): ?ProviderSlug
    {
        return $hostingDeployment->subscription->product->isMailOnlyServer()
            ? $hostingDeployment->mailProvider?->slug
            : $hostingDeployment->provider?->slug;
    }
}
