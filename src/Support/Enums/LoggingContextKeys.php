<?php

declare(strict_types=1);

namespace Waterfront\Support\Enums;

/**
 * When adding a value to the LoggingContextKeys please also double-check if changes
 * are required in the InsightsFormatter and if infra team should be notified.
 */
readonly class LoggingContextKeys
{
    public const string IDENTITY_UUID = 'identity.uuid';

    public const string CUSTOMER_ID = 'customer.id';

    public const string CUSTOMER_NUMBER = 'customer.number';

    public const string ORDER_ID = 'order.id';

    public const string ORDER_LINE_ID = 'order_line.id';

    public const string SUBSCRIPTION_ID = 'subscription.id';

    public const string SUBSCRIPTION_UUID = 'subscription.uuid';

    public const string PRODUCT_ID = 'product.id';

    public const string PRODUCT_UUID = 'product.uuid';

    public const string PRODUCT_SLUG = 'product.slug';

    public const string DOMAIN_NAME = 'domain.name';

    public const string INVOICE_LINE_ID = 'invoice_line.wf_id';

    public const string MIGRATION_VALIDATION_REFERENCE = 'migration.validation_reference';

    public const string MIGRATION_REFERENCE_CUSTOMER_ID = 'migration.reference_customer_id';

    public const string MIGRATION_REFERENCE_NAME = 'migration.reference_name';

    public const string MIGRATION_SOURCE = 'migration.source';

    public const string MIGRATION_STEP = 'migration.step';

    public const string SERVER_ID = 'server.id';

    public const string SERVER_TYPE = 'server.type';

    public const string SERVER_HOSTNAME = 'server.hostname';

    /**
     * The related provisioning id of the log message.
     * Value should be an integer, id of the technical
     * provisioning model in the database.
     * For example: the `domain_deployments.id`
     * or `hosting_deployments.id`
     * or `ssl_deployments.id`.
     */
    public const string PROVISIONING_ID = 'provisioning.id';

    /**
     * Request id that relates to the Provisioning Request in our system.
     * Value should be of the type Ramsey\Uuid\UuidInterface.
     */
    public const string PROVISIONING_REQUEST_ID = 'provisioning.request_id';

    /**
     * The related provisioning context uuid of the log message.
     * Value should be of the type Ramsey\Uuid\UuidInterface.
     */
    public const string PROVISIONING_CONTEXT = 'provisioning.context';

    /**
     * When logging something related to provisioning,
     * this value should be the provisioning type slug.
     * Value should be a string.
     * For example: hosting, domain, ssl, vps, M365.
     */
    public const string PROVISIONING_TYPE = 'provisioning.type';

    /**
     * The related provider slug of the provisioning log message.
     * Values should be a string.
     * For example: realtimeregister, plesk, directadmin.
     */
    public const string PROVISIONING_PROVIDER = 'provisioning.provider';

    public const string ONE_OFF_SCRIPT = 'one_off_script.slug';

    public const string QUEUE_NAME = 'queue.name';

    public const string QUEUE_MESSAGE_NAME = 'queue.message';

    public const string QUEUE_ATTEMPT = 'queue.attempt';

    // $this->job?->getUuid() in a queueable job
    public const string QUEUE_JOB_ID = 'queue.job_uuid';

    /**
     * When logging API requests (outgoing or incoming),
     * this is the full uri that is called.
     */
    public const string REQUEST_URI = 'request.uri';

    /**
     * Value should be string (GET, POST, DELETE, etc.).
     */
    public const string REQUEST_METHOD = 'request.method';

    /**
     * If you want to log the headers in the request.
     * Value should be a string (serialized).
     */
    public const string REQUEST_HEADERS = 'request.headers';

    /**
     * If you want to log the data in the request.
     * Value should be a string (serialized).
     */
    public const string REQUEST_DATA = 'request.data';

    /**
     * Value should be an integer.
     */
    public const string RESPONSE_CODE = 'response.code';

    /**
     * If you want to log the headers in the response.
     * Value should be a string (serialized).
     */
    public const string RESPONSE_HEADERS = 'response.headers';

    /**
     * If you want to log the data from the response.
     * Value should be a string (serialized).
     */
    public const string RESPONSE_DATA = 'response.data';

    /**
     * When logging an exception.
     * Value should be a Throwable instance.
     */
    public const string EXCEPTION = 'exception';

    /**
     * When logging stats, counts, daily numbers that can be used for
     * reporting purposes in Insights visualizations.
     * Value should be an array of value-key's.
     * For example: [
     *   'total' => 213,
     *   'unprocessed' => 41,
     *   'errors' => 6
     * ].
     */
    public const REPORTING_DATA = 'reporting';

    /**
     * When logging context that does not belong to any of the other keys
     * and is not 'important' enough to deserve a dedicated key.
     * Value should be an array of value-key's.
     * For example:
     * - Logging DTO data for traceability.
     * - Specific technical provisioning data (external id's, keys).
     */
    public const META = 'meta';
}
