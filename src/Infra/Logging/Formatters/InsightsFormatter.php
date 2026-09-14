<?php

declare(strict_types=1);

namespace Waterfront\Infra\Logging\Formatters;

use Monolog\Formatter\JsonFormatter;
use Monolog\Formatter\NormalizerFormatter;
use Monolog\LogRecord;
use Override;
use stdClass;
use Waterfront\Support\Enums\LoggingContextKeys;

class InsightsFormatter extends JsonFormatter
{
    private const int LOG_DEPTH = 20;

    public function __construct(
        int $batchMode = self::BATCH_MODE_JSON,
        bool $appendNewline = true,
        bool $ignoreEmptyContextAndExtra = false,
        bool $includeStacktraces = false,
    ) {
        parent::__construct($batchMode, $appendNewline, $ignoreEmptyContextAndExtra, $includeStacktraces);
        $this->setMaxNormalizeDepth(self::LOG_DEPTH);
    }

    #[Override]
    public function format(LogRecord $record): string
    {
        if ($record->context === []) {
            return parent::format($record);
        }

        $normalized = NormalizerFormatter::format($record);
        assert(is_array($normalized));
        $context = $normalized['context'] ?? [];

        if ($context === []) {
            return parent::format($record);
        }

        // Extract attributes from context
        $attributesFromContext = array_intersect_key($context, array_flip(
            $this->getContextKeysForAttributes(),
        ));

        // Remove extracted attributes from context
        $normalized['context'] = array_diff_key($context, $attributesFromContext);

        // Add extracted attributes to attributes key
        $normalized['attributes'] = $attributesFromContext;

        // Copied from parent::format, to (un)set empty context or extra keys
        if ($normalized['context'] === []) {
            if ($this->ignoreEmptyContextAndExtra) {
                unset($normalized['context']);
            } else {
                $normalized['context'] = new stdClass();
            }
        }

        if (array_key_exists('extra', $normalized) && $normalized['extra'] === []) {
            if ($this->ignoreEmptyContextAndExtra) {
                unset($normalized['extra']);
            } else {
                $normalized['extra'] = new stdClass();
            }
        }

        return $this->toJson($normalized, true) . ($this->appendNewline ? "\n" : '');
    }

    /**
     * @return array<string>
     */
    private function getContextKeysForAttributes(): array
    {
        // Note: Make sure to inform team infra about any changes here
        return [
            // Main business models identifiers
            LoggingContextKeys::IDENTITY_UUID,
            LoggingContextKeys::CUSTOMER_ID,
            LoggingContextKeys::CUSTOMER_NUMBER,
            LoggingContextKeys::SUBSCRIPTION_ID,
            LoggingContextKeys::SUBSCRIPTION_UUID,
            LoggingContextKeys::PRODUCT_ID,
            LoggingContextKeys::PRODUCT_UUID,
            LoggingContextKeys::PRODUCT_SLUG,
            LoggingContextKeys::ORDER_ID,
            LoggingContextKeys::ORDER_LINE_ID,
            LoggingContextKeys::DOMAIN_NAME,
            LoggingContextKeys::PROVISIONING_ID,
            LoggingContextKeys::PROVISIONING_TYPE,
            LoggingContextKeys::PROVISIONING_PROVIDER,
            LoggingContextKeys::SERVER_ID,
            LoggingContextKeys::SERVER_HOSTNAME,
            LoggingContextKeys::SERVER_TYPE,
            LoggingContextKeys::INVOICE_LINE_ID,

            // Migration
            LoggingContextKeys::MIGRATION_STEP,
            LoggingContextKeys::MIGRATION_VALIDATION_REFERENCE,

            // Queuing
            LoggingContextKeys::QUEUE_NAME,
            LoggingContextKeys::QUEUE_MESSAGE_NAME,
            LoggingContextKeys::QUEUE_JOB_ID,
        ];
    }
}
