<?php

declare(strict_types=1);

namespace Panulat\Validation;

use Panulat\Foundation\Exception\ValidationException;

final class Validator
{
    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string|callable>> $rules
     * @param array<string, string> $messages
     */
    public function __construct(
        private readonly array $data,
        private readonly array $rules,
        private readonly array $messages = [],
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|array<int, string|callable>> $rules
     * @param array<string, string> $messages
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        return new self($data, $rules, $messages);
    }

    /** @return array<string, mixed> */
    public function validate(): array
    {
        $errors = $this->errors();

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $this->validated();
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        $validated = [];

        foreach (array_keys($this->rules) as $field) {
            if (array_key_exists($field, $this->data)) {
                $validated[$field] = $this->data[$field];
            }
        }

        return $validated;
    }

    public function passes(): bool
    {
        return $this->errors() === [];
    }

    public function fails(): bool
    {
        return ! $this->passes();
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        $errors = [];

        foreach ($this->rules as $field => $rules) {
            $normalizedRules = $this->normalizeRules($rules);
            $valueExists = array_key_exists($field, $this->data);
            $value = $this->data[$field] ?? null;

            if (! $valueExists && $this->hasRule($normalizedRules, 'sometimes')) {
                continue;
            }

            foreach ($normalizedRules as $rule) {
                if (is_string($rule) && in_array($this->ruleName($rule), ['sometimes', 'nullable'], true)) {
                    continue;
                }

                if (is_string($rule) && $this->ruleName($rule) !== 'required' && $this->shouldSkipOptional($value, $normalizedRules)) {
                    continue;
                }

                $message = $this->check($field, $rule);

                if ($message !== null) {
                    $errors[$field][] = $message;

                    if (is_string($rule) && $this->ruleName($rule) === 'required') {
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param string|array<int, string|callable> $rules
     * @return list<string|callable>
     */
    private function normalizeRules(string|array $rules): array
    {
        if (is_string($rules)) {
            return $rules === '' ? [] : explode('|', $rules);
        }

        return array_values($rules);
    }

    /** @param list<string|callable> $rules */
    private function hasRule(array $rules, string $name): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && $this->ruleName($rule) === $name) {
                return true;
            }
        }

        return false;
    }

    /** @param list<string|callable> $rules */
    private function shouldSkipOptional(mixed $value, array $rules): bool
    {
        if (! $this->isBlank($value)) {
            return false;
        }

        return ! $this->hasRule($rules, 'required') || $this->hasRule($rules, 'nullable');
    }

    private function check(string $field, string|callable $rule): ?string
    {
        $value = $this->data[$field] ?? null;

        if (is_callable($rule)) {
            $result = $rule($field, $value, $this->data);
            return is_string($result) ? $result : null;
        }

        $parts = explode(':', $rule, 2);
        $name = trim($parts[0]);
        $parameter = $parts[1] ?? null;

        return match ($name) {
            'required' => $this->isBlank($value) ? $this->message($field, $name, 'The :field field is required.') : null,
            'email' => filter_var((string) $value, FILTER_VALIDATE_EMAIL) === false ? $this->message($field, $name, 'The :field field must be a valid email address.') : null,
            'min' => $parameter !== null && $this->length($value) < (int) $parameter ? $this->message($field, $name, 'The :field field must be at least :parameter.', $parameter) : null,
            'max' => $parameter !== null && $this->length($value) > (int) $parameter ? $this->message($field, $name, 'The :field field must not be greater than :parameter.', $parameter) : null,
            'in' => $parameter !== null && ! in_array((string) $value, explode(',', $parameter), true) ? $this->message($field, $name, 'The selected :field is invalid.', $parameter) : null,
            'string' => ! is_string($value) ? $this->message($field, $name, 'The :field field must be a string.') : null,
            'integer' => filter_var($value, FILTER_VALIDATE_INT) === false ? $this->message($field, $name, 'The :field field must be an integer.') : null,
            'numeric' => ! is_numeric($value) ? $this->message($field, $name, 'The :field field must be numeric.') : null,
            'boolean' => ! $this->isBooleanLike($value) ? $this->message($field, $name, 'The :field field must be true or false.') : null,
            'array' => ! is_array($value) ? $this->message($field, $name, 'The :field field must be an array.') : null,
            'accepted' => ! in_array($value, [true, 1, '1', 'yes', 'on', 'true'], true) ? $this->message($field, $name, 'The :field field must be accepted.') : null,
            'confirmed' => ($this->data[$field . '_confirmation'] ?? null) !== $value ? $this->message($field, $name, 'The :field confirmation does not match.') : null,
            default => throw new \InvalidArgumentException(sprintf('Validation rule [%s] is not supported.', $name)),
        };
    }

    private function ruleName(string $rule): string
    {
        return trim(explode(':', $rule, 2)[0]);
    }

    private function message(string $field, string $rule, string $default, ?string $parameter = null): string
    {
        $message = $this->messages[$field . '.' . $rule]
            ?? $this->messages[$rule]
            ?? $default;

        return strtr($message, [
            ':field' => $field,
            ':attribute' => $field,
            ':parameter' => (string) $parameter,
            ':value' => is_scalar($this->data[$field] ?? null) ? (string) ($this->data[$field] ?? '') : '',
        ]);
    }

    private function isBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [];
    }

    private function length(mixed $value): int
    {
        if (is_array($value)) {
            return count($value);
        }

        if (is_numeric($value) && ! is_string($value)) {
            return (int) $value;
        }

        return strlen((string) $value);
    }

    private function isBooleanLike(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false'], true);
    }
}
