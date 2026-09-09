<?php

declare(strict_types=1);

namespace Waterfront\Apps\Webhooks\Providers;

use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Waterfront\Apps\Webhooks\Controllers\PaytController;
use Waterfront\Apps\Webhooks\Services\Payt\PaytWebhookSignatureValidator;
use Waterfront\Domain\Payt\Services\PaytWebhookEventHandler;
use Waterfront\Infra\Configuration\ConfigurationInterface;
use Waterfront\Infra\PaytClient\Serializers\PaytSerializerFactory;
use Waterfront\Infra\Translation\TranslatorInterface;

class WebhooksServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes.php');

        $this->app->bind(PaytController::class, fn () => new PaytController(
            $this->app->make(TranslatorInterface::class),
            $this->app->make(LoggerInterface::class),
            $this->app->make(ConfigurationInterface::class),
            $this->app->make(PaytWebhookSignatureValidator::class),
            PaytSerializerFactory::getSerializer(),
            $this->app->make(PaytWebhookEventHandler::class),
        ));
    }
}
