<?php

declare(strict_types=1);

namespace Waterfront\Domain\DNS\Services\Serializers;

use InvalidArgumentException;

/**
 * Serializer for PowerDNS record content.
 */
class PowerDnsRecordContentSerializer
{
    /**
     * Serializes a data array to a content string.
     *
     * @param mixed[] $data
     */
    public function serialize(string $type, array $data): string
    {
        $this->validateRecordType($type);

        if ('MX' === $type) {
            return $this->serializeMx($data);
        }

        if ('SPF' === $type) {
            return $this->serializeSpf($data);
        }

        if ('SRV' === $type) {
            return $this->serializeSrv($data);
        }

        if ('TXT' === $type) {
            return $this->serializeTxt($data);
        }

        assert(is_string($data['content']));

        return $data['content'];
    }

    /**
     * Unserializes a content string to an array.
     *
     * @return mixed[]
     */
    public function unserialize(string $type, string $content): array
    {
        $this->validateRecordType($type);

        if ('MX' === $type) {
            return $this->unserializeMx($content);
        }

        if ('SPF' === $type) {
            return $this->unserializeSpf($content);
        }

        if ('SRV' === $type) {
            return $this->unserializeSrv($content);
        }

        if ('TXT' === $type) {
            return $this->unserializeTxt($content);
        }

        return [
            'content' => $content,
        ];
    }

    /**
     * Serializes the content of a MX record.
     *
     * @param mixed[] $data
     */
    private function serializeMx(array $data): string
    {
        $requiredFields = [
            'priority',
            'content',
        ];

        $fields = $this->validateSerializationFields($data, $requiredFields);

        return $fields['priority'] . ' ' . $fields['content'];
    }

    /**
     * Unserializes the content of a MX record.
     *
     * @throws InvalidArgumentException
     *
     * @return mixed[]
     */
    private function unserializeMx(string $content): array
    {
        if (! (bool) preg_match('/^(\d+)\s+([^\s]+)$/', $content, $matches)) {
            throw new InvalidArgumentException('The content of the MX record does not match the expected format.');
        }

        return [
            'priority' => $matches[1],
            'content' => $matches[2],
        ];
    }

    /**
     * Serializes the content of a SRV record.
     *
     * @param mixed[] $data
     */
    private function serializeSrv(array $data): string
    {
        $requiredFields = [
            'priority',
            'weight',
            'port',
            'content',
        ];

        $fields = $this->validateSerializationFields($data, $requiredFields);

        return $fields['priority'] . ' ' . $fields['weight'] . ' ' . $fields['port'] . ' ' . $fields['content'];
    }

    /**
     * Unserializes the content of a SRV record.
     *
     * @throws InvalidArgumentException
     *
     * @return mixed[]
     */
    private function unserializeSrv(string $content): array
    {
        if (! (bool) preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+([^\s]+)$/', $content, $matches)) {
            throw new InvalidArgumentException('The content of the SRV record does not match the expected format.');
        }

        return [
            'priority' => $matches[1],
            'weight' => $matches[2],
            'port' => $matches[3],
            'content' => $matches[4],
        ];
    }

    /**
     * Serializes the content of a TXT record.
     *
     * @param mixed[] $data
     */
    private function serializeTxt(array $data): string
    {
        assert(is_string($data['content']));

        return '"' . trim($data['content'], '"') . '"';
    }

    /**
     * Unserializes the content of a TXT record.
     *
     * @return mixed[]
     */
    private function unserializeTxt(string $content): array
    {
        return ['content' => trim($content, '"')];
    }

    /**
     * Serializes the content of a SPF record.
     *
     * @param mixed[] $data
     */
    private function serializeSpf(array $data): string
    {
        return $this->serializeTxt($data);
    }

    /**
     * Unserializes the content of a SPF record.
     *
     * @return mixed[]
     */
    private function unserializeSpf(string $content): array
    {
        return $this->unserializeTxt($content);
    }

    /**
     * Validates the record type.
     *
     * @throws InvalidArgumentException
     */
    private function validateRecordType(string $type): void
    {
        if (! (bool) preg_match('/^[A-Z]+$/', $type)) {
            throw new InvalidArgumentException('The record type is not valid.');
        }
    }

    /**
     * Validates fields for serialization.
     *
     * @param mixed[]  $data
     * @param string[] $requiredFields
     *
     * @throws InvalidArgumentException
     *
     * @return array<string, string>
     */
    private function validateSerializationFields(array $data, array $requiredFields): array
    {
        $fields = [];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data)) {
                throw new InvalidArgumentException('The ' . $field . ' is missing.');
            }

            if (! is_scalar($data[$field])) {
                throw new InvalidArgumentException('The ' . $field . ' is not a scalar value.');
            }

            $fields[$field] = strval($data[$field]);
        }

        return $fields;
    }
}
