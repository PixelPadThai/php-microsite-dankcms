<?php
/**
 * Validates a content.json payload against its embedded _schemas + _meta.
 * Pure function — no I/O. The save-content endpoint wraps this.
 */
final class ContentValidator
{
    public static function validate(array $payload, array $schemas, array $meta): array
    {
        $errors = [];

        $canonical = fn(array $a): string => json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (!isset($payload['_meta']) || $canonical($payload['_meta']) !== $canonical($meta)) {
            $errors[] = '_meta is immutable in this endpoint';
        }
        if (!isset($payload['_schemas']) || $canonical($payload['_schemas']) !== $canonical($schemas)) {
            $errors[] = '_schemas is immutable in this endpoint';
        }

        $settingsSchema = $schemas['settings'] ?? [];
        $settings = $payload['settings'] ?? [];
        if (!is_array($settings)) {
            $errors[] = 'settings must be an object';
        } else {
            self::validateObject($settings, $settingsSchema, 'settings', $errors);
        }

        $collections = $payload['collections'] ?? [];
        if (!is_array($collections)) {
            $errors[] = 'collections must be an object';
        } else {
            foreach ($collections as $name => $rows) {
                if (!isset($schemas[$name]) || $name === 'settings') {
                    $errors[] = "Unknown collection: $name";
                    continue;
                }
                if (!is_array($rows)) {
                    $errors[] = "collections.$name must be an array";
                    continue;
                }
                $recSchema = $schemas[$name];
                $primary = self::primaryField($recSchema);
                foreach ($rows as $i => $row) {
                    if (!is_array($row)) {
                        $errors[] = "collections.$name[$i] must be an object";
                        continue;
                    }
                    if ($primary !== null && (!array_key_exists($primary, $row) || $row[$primary] === '' || $row[$primary] === null)) {
                        $errors[] = "collections.$name[$i].$primary (primary key) is required";
                    }
                    self::validateObject($row, $recSchema, "collections.$name[$i]", $errors);
                }
            }
        }

        return ['ok' => count($errors) === 0, 'errors' => $errors];
    }

    private static function primaryField(array $schema): ?string
    {
        foreach ($schema as $field => $def) {
            if (is_array($def) && !empty($def['primary'])) return $field;
        }
        return null;
    }

    private static function validateObject(array $obj, array $schema, string $path, array &$errors): void
    {
        foreach ($schema as $field => $def) {
            if (!is_array($def)) continue;
            $present = array_key_exists($field, $obj);
            $required = !empty($def['required']);
            if (!$present) {
                if ($required) $errors[] = "$path.$field is required";
                continue;
            }
            self::validateField($obj[$field], $def, "$path.$field", $errors);
        }
    }

    private static function validateField($value, array $def, string $path, array &$errors): void
    {
        $type = $def['type'] ?? 'string';

        if ($value === '' || $value === null) {
            if (!empty($def['required'])) $errors[] = "$path is required";
            return;
        }

        switch ($type) {
            case 'string':
            case 'text':
            case 'string_ref':
            case 'image':
                if (!is_string($value)) $errors[] = "$path must be a string";
                break;
            case 'number':
                if (!is_int($value) && !is_float($value)) $errors[] = "$path must be a number";
                break;
            case 'boolean':
                if (!is_bool($value)) $errors[] = "$path must be a boolean";
                break;
            case 'color':
                if (!is_string($value) || !preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                    $errors[] = "$path must be a color hex like #aabbcc";
                }
                break;
            case 'email':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                    $errors[] = "$path must be a valid email";
                }
                break;
            case 'url':
                if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $errors[] = "$path must be a valid URL";
                }
                break;
            case 'tel':
                if (!is_string($value)) $errors[] = "$path must be a string";
                break;
            case 'select':
                $options = $def['options'] ?? [];
                if (!in_array($value, $options, true)) $errors[] = "$path must be one of the allowed options";
                break;
            case 'object':
                if (!is_array($value)) { $errors[] = "$path must be an object"; break; }
                self::validateObject($value, $def['fields'] ?? [], $path, $errors);
                break;
            case 'array':
                if (!is_array($value)) { $errors[] = "$path must be an array"; break; }
                $itemDef = $def['item'] ?? ['type' => 'string'];
                foreach ($value as $i => $item) {
                    self::validateField($item, $itemDef, "$path[$i]", $errors);
                }
                break;
            case 'reference':
                if (!is_string($value) && !is_int($value)) $errors[] = "$path must be a reference key";
                break;
            default:
                if (!is_scalar($value) && !is_array($value)) $errors[] = "$path has unknown type: $type";
        }
    }
}
