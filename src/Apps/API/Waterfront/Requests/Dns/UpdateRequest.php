<?php

declare(strict_types=1);

namespace Waterfront\Apps\API\Waterfront\Requests\Dns;

use Illuminate\Contracts\Translation\Translator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Rules\NsSubdomainName;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\DnsValidatorFactory;
use Waterfront\Domain\DNS\Validators\UpdateDnsRecordValidator;

class UpdateRequest extends BaseRequest
{
    /**
     * @{inheritDoc}
     */
    protected function getValidatorFactory(): DnsValidatorFactory
    {
        $factory = $this->container->make(DnsValidatorFactory::class);
        $factory->resolver(
            function (DnsRecordsValidationService $service, Translator $translator, array $data, array $rules, array $messages, array $customAttributes): UpdateDnsRecordValidator {
                if (! array_key_exists('domain', $customAttributes)) {
                    $domain = $this->route('domain');

                    if ($domain === null) {
                        throw new NotFoundHttpException('Domain expected in route');
                    }

                    $customAttributes['domain'] = $this->route('domain');
                }

                $validationService = $this->container->make(DnsRecordsValidationService::class);

                /**
                 * When a user adds DNS records from Coast they are allowed to add NS records, these
                 * NS records should only be for a subdomain. For the root domain the existing
                 * NameserverController API should be used. We add the subdomain rule here.
                 */
                $nsSubdomainRule = $this->container->make(NsSubdomainName::class);

                if (is_string($data['new']['type']) && $data['new']['type'] === DnsRecordType::NS->value) {
                    $rules = [
                        'name'  => ['bail', 'required', 'string', $nsSubdomainRule],
                    ] + $validationService->getDefaultRules();
                }

                return new UpdateDnsRecordValidator(
                    $validationService,
                    $translator,
                    $data,
                    $rules,
                    $messages,
                    $customAttributes
                );
            }
        );

        return $factory;
    }
}
