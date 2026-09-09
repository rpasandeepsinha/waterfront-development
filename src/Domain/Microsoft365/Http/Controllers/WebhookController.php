<?php

declare(strict_types=1);

namespace Waterfront\Domain\Microsoft365\Http\Controllers;

use Exception;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Psr\Log\LoggerInterface;
use SandwaveIo\Office365\Enum\Event;
use SandwaveIo\Office365\Exception\Office365Exception;
use SandwaveIo\Office365\Office\OfficeClient;
use Waterfront\Domain\Microsoft365\EventListener\CallbackErrorListener;
use Waterfront\Domain\Microsoft365\EventListener\CloudLicenseListener;
use Waterfront\Domain\Microsoft365\EventListener\CustomerCreateListener;
use Waterfront\Domain\Microsoft365\EventListener\ModifyOrderQuantityListener;
use Waterfront\Domain\Microsoft365\EventListener\OrderMessageListener;
use Waterfront\Domain\Microsoft365\EventListener\TenantCreateListener;
use Waterfront\Domain\Microsoft365\EventListener\TerminateOrderListener;
use Waterfront\Domain\Microsoft365\Events\Microsoft365Webhook;
use Waterfront\Support\Enums\LoggingContextKeys;

class WebhookController
{
    /**
     * @throws Office365Exception
     */
    public function incomingCall(
        Request $request,
        OfficeClient $microsoftClient,
        CloudLicenseListener $cloudLicenseListener,
        CustomerCreateListener $customerCreateListener,
        TenantCreateListener $tenantCreateListener,
        OrderMessageListener $orderMessageListener,
        Dispatcher $eventDispatcher,
        LoggerInterface $logger,
    ): Response {
        try {
            $eventDispatcher->dispatch(new Microsoft365Webhook($request->getContent()));
        } catch (Exception $e) {
            $logger->error('Something went wrong while logging the Microsoft365 webhook', [
                LoggingContextKeys::EXCEPTION => $e,
            ]);
            return new Response(status: Response::HTTP_NO_CONTENT);
        }

        $microsoftClient->webhook->addEventSubscriber(Event::CLOUD_LICENSE_ORDER_CREATE, $cloudLicenseListener);
        $microsoftClient->webhook->addEventSubscriber(Event::CUSTOMER_CREATE, $customerCreateListener);
        $microsoftClient->webhook->addEventSubscriber(Event::ORDER_MODIFY_QUANTITY, new ModifyOrderQuantityListener());
        $microsoftClient->webhook->addEventSubscriber(Event::TERMINATE_ORDER, new TerminateOrderListener());
        $microsoftClient->webhook->addEventSubscriber(Event::CALLBACK_ERROR, new CallbackErrorListener());
        $microsoftClient->webhook->addEventSubscriber(Event::TENANT_ORDER, $tenantCreateListener);
        $microsoftClient->webhook->addEventSubscriber(Event::ORDER_MESSAGE, $orderMessageListener);

        $microsoftClient->webhook->process($request->getContent());

        return new Response([
            'status' => 'ok',
        ]);
    }
}
