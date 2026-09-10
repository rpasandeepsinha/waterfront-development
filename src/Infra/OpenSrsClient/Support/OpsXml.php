<?php

declare(strict_types=1);

namespace Waterfront\Infra\OpenSrsClient\Support;

use DOMDocument;
use DOMElement;
use DOMImplementation;
use Exception;
use SimpleXMLElement;

/**
 * Encoder/decoder for the OpenSRS OPS (XCP) protocol envelope.
 *
 * An OPS message wraps its payload in
 * `OPS_envelope > body > data_block > dt_assoc`, where associative arrays are
 * `<dt_assoc>` of `<item key="...">` and lists are `<dt_array>` of `<item key="0">`.
 *
 * @see https://domains.opensrs.guide/docs/protocol-message-structure
 */
final class OpsXml
{
    private const VERSION = '0.9';

    /**
     * @param array<string, mixed> $data top level associative payload (protocol, object, action, attributes, ...)
     */
    public static function encode(array $data): string
    {
        $implementation = new DOMImplementation();
        $docType = $implementation->createDocumentType('OPS_envelope', '', 'ops.dtd');
        $document = $implementation->createDocument('', '', $docType);
        $document->encoding = 'UTF-8';
        $document->formatOutput = true;

        $envelope = $document->createElement('OPS_envelope');
        $document->appendChild($envelope);

        $header = $document->createElement('header');
        $header->appendChild($document->createElement('version', self::VERSION));
        $envelope->appendChild($header);

        $body = $document->createElement('body');
        $dataBlock = $document->createElement('data_block');
        $dataBlock->appendChild(self::encodeValue($document, $data));
        $body->appendChild($dataBlock);
        $envelope->appendChild($body);

        return (string) $document->saveXML();
    }

    /**
     * @throws Exception on malformed XML, a blocked external entity, or a duplicate key
     *
     * @return mixed[]
     */
    public static function decode(string $xml): array
    {
        // LIBXML_NONET keeps the parser from fetching ops.dtd (or any SYSTEM entity) over
        // the network; no LIBXML_DTDLOAD/LIBXML_NOENT so declared entities are never expanded.
        $useInternalErrors = libxml_use_internal_errors(true);

        try {
            $envelope = new SimpleXMLElement($xml, LIBXML_NONET);
        } catch (Exception $exception) {
            throw new Exception('Malformed OpenSRS OPS response envelope.', 0, $exception);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($useInternalErrors);
        }

        $dataBlock = $envelope->body->data_block ?? null;
        if (! $dataBlock instanceof SimpleXMLElement) {
            return [];
        }

        $container = self::firstChild($dataBlock);

        return $container instanceof SimpleXMLElement ? self::decodeNode($container) : [];
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private static function encodeValue(DOMDocument $document, array $value): DOMElement
    {
        $isList = array_is_list($value) && $value !== [];
        $container = $document->createElement($isList ? 'dt_array' : 'dt_assoc');

        foreach ($value as $key => $item) {
            $itemElement = $document->createElement('item');
            $itemElement->setAttribute('key', (string) $key);

            if (is_array($item)) {
                $itemElement->appendChild(self::encodeValue($document, $item));
            } else {
                $itemElement->appendChild($document->createTextNode(self::stringifyScalar($item)));
            }

            $container->appendChild($itemElement);
        }

        return $container;
    }

    private static function stringifyScalar(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * @throws Exception on a duplicate key within the same container
     *
     * @return mixed[]
     */
    private static function decodeNode(SimpleXMLElement $node): array
    {
        $result = [];

        foreach ($node->children() as $item) {
            if ($item->getName() !== 'item') {
                continue;
            }

            $key = (string) $item['key'];

            if (array_key_exists($key, $result)) {
                throw new Exception(sprintf('Duplicate key "%s" in OpenSRS OPS response.', $key));
            }

            $childContainer = self::firstChild($item);

            $result[$key] = $childContainer instanceof SimpleXMLElement
                ? self::decodeNode($childContainer)
                : trim((string) $item);
        }

        return $result;
    }

    private static function firstChild(SimpleXMLElement $node): ?SimpleXMLElement
    {
        foreach ($node->children() as $child) {
            return $child;
        }

        return null;
    }
}
