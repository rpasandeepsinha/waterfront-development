<?php

declare(strict_types=1);

namespace Waterfront\Infra\PuzzelClient\Serializers;

use Carbon\CarbonImmutable;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Webmozart\Assert\Assert;

/**
 * Puzzel's API uses some legacy Microsoft Datetime JSON format
 * which is a default from old C# code. This denormalizer is
 * used for their weird format `/Date(1767016200000-0000)/`.
 *
 * @see https://medium.com/@aryanvania03/how-do-i-format-a-microsoft-json-date-fe8a99e48a1d
 * @see https://github.com/neuecc/Utf8Json/issues/152
 */
class MicrosoftDateDenormalizer implements DenormalizerInterface
{
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        Assert::string($data);

        $dateMatch = preg_match('/^\/Date\((-?\d+)(?:([+-])(\d{4}))?\)\/$/', $data, $matches);
        if ($dateMatch !== false && $matches !== []) {
            $milliseconds = (int) $matches[1];
            $seconds = $milliseconds / 1000;
            $date = DateTime::createFromFormat('U', (string) floor($seconds));

            Assert::notFalse($date);
            $date->setTimezone(new DateTimeZone(CarbonImmutable::now()->timezoneName));

            return $date;
        }

        throw new InvalidArgumentException("Invalid Microsoft date format: $data");
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === DateTime::class
            && is_string($data)
            && str_starts_with($data, '/Date(');
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            DateTime::class => true,
        ];
    }
}
