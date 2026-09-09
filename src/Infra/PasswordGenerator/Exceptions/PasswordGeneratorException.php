<?php

declare(strict_types=1);

namespace Waterfront\Infra\PasswordGenerator\Exceptions;

use Exception;

class PasswordGeneratorException extends Exception
{
    public static function lengthTooShort(int $minLength, int $givenLength): PasswordGeneratorException
    {
        return new PasswordGeneratorException(
            sprintf(
                'The given PasswordLength (%d) is too short, the minimum is %d',
                $givenLength,
                $minLength
            ),
        );
    }

    public static function duplicateCharsetRule(string $charSet): PasswordGeneratorException
    {
        return new PasswordGeneratorException(
            sprintf(
                'There is already a rule for the charset : %s applied',
                $charSet
            ),
        );
    }

    public static function noRulesFound(): PasswordGeneratorException
    {
        return new PasswordGeneratorException(
            'There are no rules attached, so we can not generate a password'
        );
    }

    public static function maxLengthExceeded(int $length, int $minOccurrences): PasswordGeneratorException
    {
        return new PasswordGeneratorException(
            sprintf(
                'The maximum length (%d) will be exceeded by te total of minimum occurrences (%d) of the rules',
                $length,
                $minOccurrences
            ),
        );
    }

    public static function ruleQueueExhausted(): PasswordGeneratorException
    {
        return new PasswordGeneratorException(
            'The Rule Queue was exhausted before generation was finished'
        );
    }

    public static function invalidQueueLength(int $Passlength, int $queueLength): PasswordGeneratorException
    {
        return new PasswordGeneratorException(
            sprintf(
                'The queue for constructing the password has invalid length | Passlength : %d - QueueLength : %d',
                $Passlength,
                $queueLength
            ),
        );
    }

    public static function invalidSize(int $size): PasswordGeneratorException
    {
        return new PasswordGeneratorException(
            sprintf(
                'The size of the charsAvailable array is invalid. This must be greater than 0. | current size is : %d',
                $size,
            ),
        );
    }
}
