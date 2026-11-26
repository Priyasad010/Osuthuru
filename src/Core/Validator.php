<?php

namespace App\Core;

class Validator
{
    private array $data;
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public static function make(array $data, array $rules): self
    {
        $validator = new self($data);
        $validator->validate($rules);
        return $validator;
    }

    public function validate(array $rules): void
    {
        foreach ($rules as $field => $fieldRules) {
            $value = $this->data[$field] ?? null;

            if (is_string($fieldRules)) {
                $fieldRules = explode('|', $fieldRules);
            }

            foreach ($fieldRules as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $this->applyRule($field, $value, $rule, $params);
            }
        }
    }

    private function applyRule(string $field, mixed $value, string $rule, array $params): void
    {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->addError($field, t('val_required', ['field' => self::label($field)]));
                }
                break;

            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, t('val_email'));
                }
                break;

            case 'min':
                $min = (int)($params[0] ?? 0);
                if (!empty($value) && mb_strlen((string)$value) < $min) {
                    $this->addError($field, t('val_min', ['field' => self::label($field), 'count' => $min]));
                }
                break;

            case 'max':
                $max = (int)($params[0] ?? 255);
                if (!empty($value) && mb_strlen((string)$value) > $max) {
                    $this->addError($field, t('val_max', ['field' => self::label($field), 'count' => $max]));
                }
                break;

            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, t('val_numeric', ['field' => self::label($field)]));
                }
                break;

            case 'matches':
                $otherField = $params[0] ?? '';
                $otherValue = $this->data[$otherField] ?? null;
                if ($value !== $otherValue) {
                    $this->addError($field, t('val_matches', ['field' => self::label($field)]));
                }
                break;

            case 'in':
                if (!empty($value) && !in_array($value, $params, true)) {
                    $this->addError($field, t('val_in', ['field' => self::label($field)]));
                }
                break;
        }
    }

    private static function label(string $field): string
    {
        $key = 'field_' . $field;
        $label = t($key);
        return $label !== $key ? $label : ucfirst(str_replace('_', ' ', $field));
    }

    public function addError(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function fails(): bool
    {
        return !$this->passes();
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }
}
