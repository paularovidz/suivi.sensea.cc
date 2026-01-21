<?php

declare(strict_types=1);

namespace App\Utils;

class Validator
{
    private array $errors = [];
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function required(string $field, string $message = null): self
    {
        if (!isset($this->data[$field]) || trim((string)$this->data[$field]) === '') {
            $this->errors[$field] = $message ?? "Le champ {$field} est requis";
        }
        return $this;
    }

    public function email(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[$field] = $message ?? "Adresse email invalide";
        }
        return $this;
    }

    public function minLength(string $field, int $min, string $message = null): self
    {
        if (isset($this->data[$field]) && strlen((string)$this->data[$field]) < $min) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit contenir au moins {$min} caractères";
        }
        return $this;
    }

    public function maxLength(string $field, int $max, string $message = null): self
    {
        if (isset($this->data[$field]) && strlen((string)$this->data[$field]) > $max) {
            $this->errors[$field] = $message ?? "Le champ {$field} ne doit pas dépasser {$max} caractères";
        }
        return $this;
    }

    public function numeric(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !is_numeric($this->data[$field])) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit être numérique";
        }
        return $this;
    }

    public function integer(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_INT)) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit être un entier";
        }
        return $this;
    }

    public function inArray(string $field, array $allowed, string $message = null): self
    {
        if (isset($this->data[$field]) && !in_array($this->data[$field], $allowed, true)) {
            $this->errors[$field] = $message ?? "Le champ {$field} doit être l'une des valeurs: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function date(string $field, string $format = 'Y-m-d', string $message = null): self
    {
        if (isset($this->data[$field])) {
            $date = \DateTime::createFromFormat($format, $this->data[$field]);
            if (!$date || $date->format($format) !== $this->data[$field]) {
                $this->errors[$field] = $message ?? "Le champ {$field} doit être une date valide au format {$format}";
            }
        }
        return $this;
    }

    public function datetime(string $field, string $message = null): self
    {
        return $this->date($field, 'Y-m-d H:i:s', $message);
    }

    public function uuid(string $field, string $message = null): self
    {
        if (isset($this->data[$field])) {
            $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
            if (!preg_match($pattern, $this->data[$field])) {
                $this->errors[$field] = $message ?? "Le champ {$field} doit être un UUID valide";
            }
        }
        return $this;
    }

    public function phone(string $field, string $message = null): self
    {
        if (isset($this->data[$field]) && !empty($this->data[$field])) {
            $phone = preg_replace('/[\s\-\.]/', '', $this->data[$field]);
            if (!preg_match('/^\+?[0-9]{10,15}$/', $phone)) {
                $this->errors[$field] = $message ?? "Numéro de téléphone invalide. Format attendu : 06 12 34 56 78";
            }
        }
        return $this;
    }

    public function between(string $field, int|float $min, int|float $max, string $message = null): self
    {
        if (isset($this->data[$field])) {
            $value = (float)$this->data[$field];
            if ($value < $min || $value > $max) {
                $this->errors[$field] = $message ?? "Le champ {$field} doit être entre {$min} et {$max}";
            }
        }
        return $this;
    }

    public function json(string $field, string $message = null): self
    {
        if (isset($this->data[$field])) {
            if (is_string($this->data[$field])) {
                json_decode($this->data[$field]);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->errors[$field] = $message ?? "Le champ {$field} doit être un JSON valide";
                }
            } elseif (!is_array($this->data[$field])) {
                $this->errors[$field] = $message ?? "Le champ {$field} doit être un JSON valide";
            }
        }
        return $this;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function validate(): void
    {
        if (!$this->isValid()) {
            Response::validationError($this->errors);
        }
    }

    public static function sanitizeString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    public static function sanitizeInt(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int)$value;
    }
}
