<?php

declare(strict_types=1);

namespace Karhu\Http;

use Karhu\Attributes\Email;
use Karhu\Attributes\In;
use Karhu\Attributes\NumericRange;
use Karhu\Attributes\Regex;
use Karhu\Attributes\Required;
use Karhu\Attributes\StringLength;
use ReflectionClass;
use ReflectionProperty;

/**
 * Attribute-based request validation.
 *
 * Scope-fenced: exactly 6 validators ship. No nested validation, no
 * custom rules (subclass to extend). This prevents the validation-
 * library-bloat failure mode.
 *
 * Usage: define a DTO class with validation attributes on properties,
 * then call Validation::validate($data, MyDto::class).
 */
final class Validation
{
    /**
     * Validate data against a DTO class's property attributes.
     *
     * @param array<string, mixed> $data  Input data (e.g. from Request::body())
     * @param class-string         $dto   DTO class with validation attributes
     * @return array<string, string> Errors keyed by field name (empty = valid)
     */
    public static function validate(array $data, string $dto): array
    {
        $errors = [];
        $ref = new ReflectionClass($dto);

        foreach ($ref->getProperties() as $prop) {
            $name = $prop->getName();
            $value = $data[$name] ?? null;

            foreach (self::checkProperty($prop, $name, $value) as $field => $error) {
                $errors[$field] = $error;
            }
        }

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    private static function checkProperty(ReflectionProperty $prop, string $name, mixed $value): array
    {
        $errors = [];

        // #[Required]
        foreach ($prop->getAttributes(Required::class) as $attr) {
            if ($value === null || $value === '') {
                $errors[$name] = $attr->newInstance()->message ?? "{$name} is required.";
                return $errors; // No point checking other rules if missing
            }
        }

        // Skip remaining checks if value is null/empty and not required
        if ($value === null || $value === '') {
            return $errors;
        }

        $strValue = is_scalar($value) ? (string) $value : '';

        // #[StringLength]
        foreach ($prop->getAttributes(StringLength::class) as $attr) {
            /** @var StringLength $rule */
            $rule = $attr->newInstance();
            $len = mb_strlen($strValue);
            if ($rule->min !== null && $len < $rule->min) {
                $errors[$name] = $rule->message ?? "{$name} must be at least {$rule->min} characters.";
            } elseif ($rule->max !== null && $len > $rule->max) {
                $errors[$name] = $rule->message ?? "{$name} must be at most {$rule->max} characters.";
            }
        }

        // #[NumericRange]
        foreach ($prop->getAttributes(NumericRange::class) as $attr) {
            /** @var NumericRange $rule */
            $rule = $attr->newInstance();
            $num = is_numeric($value) ? (float) $value : null;
            if ($num === null) {
                $errors[$name] = "{$name} must be numeric.";
            } elseif ($rule->min !== null && $num < $rule->min) {
                $errors[$name] = $rule->message ?? "{$name} must be at least {$rule->min}.";
            } elseif ($rule->max !== null && $num > $rule->max) {
                $errors[$name] = $rule->message ?? "{$name} must be at most {$rule->max}.";
            }
        }

        // #[Email]
        foreach ($prop->getAttributes(Email::class) as $attr) {
            if (filter_var($strValue, FILTER_VALIDATE_EMAIL) === false) {
                $errors[$name] = $attr->newInstance()->message ?? "{$name} must be a valid email.";
            }
        }

        // #[Regex]
        foreach ($prop->getAttributes(Regex::class) as $attr) {
            /** @var Regex $rule */
            $rule = $attr->newInstance();
            if (!preg_match($rule->pattern, $strValue)) {
                $errors[$name] = $rule->message ?? "{$name} format is invalid.";
            }
        }

        // #[In]
        foreach ($prop->getAttributes(In::class) as $attr) {
            $rule = $attr->newInstance();
            if (!in_array($strValue, $rule->values, true)) {
                $allowed = implode(', ', $rule->values);
                $errors[$name] = $rule->message ?? "{$name} must be one of: {$allowed}.";
            }
        }

        return $errors;
    }
}
