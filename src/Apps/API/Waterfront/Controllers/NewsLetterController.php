<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Controllers;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Waterfront\Domain\Marketing\Crm;
use Waterfront\Infra\Authentication\AuthenticationManager;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotAuthenticationException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotConflictException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotJsonException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotThrottledException;
use Waterfront\Infra\HubspotClient\Exceptions\HubspotUnexpectedResponseException;
use Waterfront\Infra\Translation\TranslatorInterface;

class NewsLetterController
{
    public function __construct(
        private readonly Crm $crm,
        private readonly AuthenticationManager $authenticationManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws AuthenticationException
     * @throws HubspotJsonException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     */
    public function isSubscribed(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $isSubscribed = $this->crm->hasEnabledMarketingEmails($customer);

        return new JsonResponse([
            'subscribed' => $isSubscribed,
        ]);
    }

    /**
     * @throws HubspotJsonException
     * @throws HubspotThrottledException
     * @throws HubspotUnexpectedResponseException
     * @throws AuthenticationException
     * @throws HubspotAuthenticationException
     * @throws HubspotConflictException
     */
    public function isOptedInMarketingEmails(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;
        $array = $this->crm->hasOptedInMarketingEmails($customer);

        return new JsonResponse($array);
    }

    /**
     * @throws AuthenticationException
     */
    public function subscribe(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $this->crm->enableMarketingEmails($customer);

        return new JsonResponse([
            'message' => $this->translator->translate('newsletter-subscription.subscribed'),
        ]);
    }

    /**
     * @throws AuthenticationException
     */
    public function unsubscribe(): JsonResponse
    {
        $customer = $this->authenticationManager->getAuthenticatedCustomer()->customer;

        $this->crm->disableMarketingEmails($customer);

        return new JsonResponse([
            'message' => $this->translator->translate('newsletter-subscription.unsubscribed'),
        ]);
    }
}
