<?php

declare(strict_types=1);

namespace Waterfront\Infra\PasswordGenerator;

use Waterfront\Infra\PasswordGenerator\DTO\Rule;
use Waterfront\Infra\PasswordGenerator\Exceptions\PasswordGeneratorException;

abstract class AbstractGenerator
{
    public const int MIN_LENGTH = 8;

    private int $length = 0;

    /** @var array<string, Rule> */
    private array $rules = [];

    final public function generatePassword(?int $length = null): string
    {
        $length ??= static::MIN_LENGTH;

        $this->setLength($length);
        $this->prepareGeneration();
        $this->checkPreConditions();

        return $this->generate();
    }

    protected function addRule(Rule $rule): void
    {
        if (array_key_exists($rule->characterSet->name, $this->rules)) {
            throw PasswordGeneratorException::duplicateCharsetRule($rule->characterSet->name);
        }

        $this->rules[$rule->characterSet->name] = $rule;
    }

    private function setLength(int $length): void
    {
        if ($length < static::MIN_LENGTH) {
            throw PasswordGeneratorException::lengthTooShort(static::MIN_LENGTH, $length);
        }

        $this->length = $length;
    }

    private function prepareGeneration(): void
    {
        if ($this->length === 0) {
            $this->length = static::MIN_LENGTH;
        }
    }

    private function checkPreConditions(): void
    {
        if (count($this->rules) === 0) {
            throw PasswordGeneratorException::noRulesFound();
        }

        $minOccurrenceTotal = 0;
        foreach ($this->rules as $rule) {
            $minOccurrenceTotal += $rule->minOccurrence;
        }

        if ($minOccurrenceTotal > $this->length) {
            throw PasswordGeneratorException::maxLengthExceeded($this->length, $minOccurrenceTotal);
        }
    }

    /**
     * @return array<int, Rule>
     */
    private function buildRuleQueue(): array
    {
        $passwordQueue = ['queue' => [], 'queue_index' => []];
        $ruleQueue = [];

        foreach ($this->rules as $rule) {
            /**
             * Every rule is applied at least once with minimum off Occurrences.
             */
            if ($rule->minOccurrence >= 1) {
                for ($i = 1; $i <= $rule->minOccurrence; $i++) {
                    array_push($passwordQueue['queue'], $rule);
                }
            }

            $passwordQueue['queue_index'][$rule->characterSet->name] = $rule->minOccurrence;
            if ($rule->minOccurrence !== $rule->maxOccurrence) {
                $ruleQueue[$rule->characterSet->name] = $rule;
            }
        }

        while ($this->length - count($passwordQueue['queue']) > 0) {
            /**
             * Fallback for when an unforeseen case arises.
             */
            if (count($ruleQueue) === 0) {
                throw PasswordGeneratorException::ruleQueueExhausted();
            }

            $ruleName = array_rand($ruleQueue);
            $currentRule = $ruleQueue[$ruleName];
            array_push($passwordQueue['queue'], $currentRule);
            $passwordQueue['queue_index'][$currentRule->characterSet->name] =
                ($passwordQueue['queue_index'][$currentRule->characterSet->name] ?? 0) + 1;

            if ($passwordQueue['queue_index'][$currentRule->characterSet->name] === $currentRule->maxOccurrence) {
                unset($ruleQueue[$currentRule->characterSet->name]);
            }
        }
        return $passwordQueue['queue'];
    }

    private function generate(): string
    {
        $ruleQueue = $this->buildRuleQueue();
        $password = '';

        if (count($ruleQueue) !== $this->length) {
            throw PasswordGeneratorException::invalidQueueLength($this->length, count($ruleQueue));
        }

        shuffle($ruleQueue);
        foreach ($ruleQueue as $rule) {
            $charsAvailable = $rule->characterSet->chars();

            if ($rule->exclusions !== null) {
                $exclusions = str_split($rule->exclusions);

                foreach ($exclusions as $value) {
                    unset($charsAvailable[array_search($value, $charsAvailable, true)]);
                }
            }

            $size = count($charsAvailable) - 1;
            if ($size === 0) {
                throw PasswordGeneratorException::invalidSize($size);
            }

            // We need the predefined $randomKey due a stan error, if we don't.
            $randomKey = 0;
            foreach ($charsAvailable as $char) {
                $randomKey = random_int(0, $size);
                if (array_key_exists($randomKey, $charsAvailable)) {
                    break;
                }
            }

            $password .= $charsAvailable[$randomKey];
        }

        return $password;
    }
}
