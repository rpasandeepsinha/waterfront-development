<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Hydrators;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;
use Waterfront\Domain\DNS\Entities\DnsRecords\AliasRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\CnameRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\DefaultRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\MxRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\NsRecord;
use Waterfront\Domain\DNS\Entities\DnsRecords\SrvRecord;
use Waterfront\Domain\DNS\Enums\DnsRecordType;
use Waterfront\Domain\DNS\Interfaces\DnsRecordInterface;
use Waterfront\Domain\DNS\Services\DnsRecordsValidationService;
use Waterfront\Domain\DNS\Validators\DnsRecordValidator;
use Waterfront\Domain\DNS\Validators\DnsValidatorFactory;
use Waterfront\Support\Helpers\IdnHelper;

class DnsRecordHydrator
{
    private readonly DnsValidatorFactory $validatorFactory;

    public function __construct(DnsValidatorFactory $validatorFactory)
    {
        $validatorFactory->resolver(
            fn (
                DnsRecordsValidationService $validationService,
                Translator $translator,
                array $data,
                array $rules,
                array $messages,
                array $customAttributes,
            ): DnsRecordValidator => new DnsRecordValidator(
                $validationService,
                $translator,
                $data,
                $rules,
                $messages,
                $customAttributes,
            ),
        );

        $this->validatorFactory = $validatorFactory;
    }

    /**
     * Hydrates and optionally validates a DNS record from an input array.
     *
     * @param array<mixed> $input
     *
     * @throws ValidationException
     */
    public function hydrate(array $input, bool $validate = true): DnsRecordInterface
    {
        if ($validate) {
            $input = $this->validateData($input);
        }

        $disabled = $input['disabled'] ?? false;
        assert(is_bool($disabled));

        /** @var string $type */
        $type = $input['type'];
        /** @var string $name */
        $name = $input['name'];
        $name = IdnHelper::toAscii($name);
        /** @var string $content */
        $content = $input['content'];
        $content = $this->normalizeContent($type, $content);
        /** @var numeric-string|int|float $ttl */
        $ttl = $input['ttl'];

        $priority = is_numeric($input['priority'] ?? null) ? $input['priority'] : null;
        $weight = is_numeric($input['weight'] ?? null) ? $input['weight'] : null;
        $port = is_numeric($input['port'] ?? null) ? $input['port'] : null;

        return match (DnsRecordType::tryFrom($type)) {
            DnsRecordType::ALIAS => new AliasRecord($name, $content, (int) $ttl, $disabled),
            DnsRecordType::CNAME => new CnameRecord($name, $content, (int) $ttl, $disabled),
            DnsRecordType::MX => new MxRecord($name, $content, (int) $priority, (int) $ttl, $disabled),
            DnsRecordType::NS => new NsRecord($name, $content, (int) $ttl, $disabled),
            DnsRecordType::SRV => new SrvRecord(
                $name,
                $content,
                (int) $priority,
                (int) $weight,
                (int) $port,
                (int) $ttl,
                $disabled,
            ),
            default => new DefaultRecord($type, $name, $content, (int) $ttl, $disabled),
        };
    }

    /**
     * @return mixed[]
     */
    public function dehydrate(DnsRecordInterface $record): array
    {
        return $record->toArray();
    }

    private function normalizeContent(string $type, string $content): string
    {
        return match (DnsRecordType::tryFrom($type)) {
            DnsRecordType::ALIAS,
            DnsRecordType::CNAME,
            DnsRecordType::MX,
            DnsRecordType::NS,
            DnsRecordType::SRV,
                => IdnHelper::toAscii($content),
            default => $content,
        };
    }

    /**
     * @param mixed[] $data
     *
     * @throws ValidationException
     * @throws JsonException
     *
     * @return mixed[]
     */
    private function validateData(array $data): array
    {
        $validator = $this->validatorFactory->make($data, []);

        if ($validator->fails()) {
            Log::notice(
                'Validation of data failed during DnsRecordHydrator::hydrate(). Errors: '
                    . $validator->errors()->toJson()
                    . ', Raw record data: '
                    . json_encode($data, JSON_THROW_ON_ERROR),
            );

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
