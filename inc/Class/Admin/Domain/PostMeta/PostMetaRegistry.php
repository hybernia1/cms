<?php
declare(strict_types=1);

namespace Cms\Admin\Domain\PostMeta;

use InvalidArgumentException;

/**
 * @phpstan-type PostMetaDefinition array{
 *   key: string,
 *   type: string,
 *   label: string,
 *   description: string,
 *   default: mixed,
 *   show_in_admin: bool,
 *   group: ?string,
 *   options: array<string,string>,
 *   required: bool,
 *   allow_null: bool,
 *   empty_is_null: bool,
 *   sanitize_callback: (callable(mixed,string,string,array):mixed)|null,
 *   hydrate_callback: (callable(?string,?string,array):mixed)|null
 * }
 */
final class PostMetaRegistry
{
    /**
     * @var array<string,PostMetaDefinition>
     */
    private static array $globalDefinitions = [];

    /**
     * @var array<string,array<string,PostMetaDefinition>>
     */
    private static array $definitionsByType = [];

    /**
     * @param array<string,mixed> $args
     */
    public static function register(string $postType, string $key, array $args = []): void
    {
        $normalizedType = trim($postType);
        if ($normalizedType === '') {
            throw new InvalidArgumentException('Post type for metadata registration must not be empty.');
        }

        $definition = self::buildDefinition($key, $args);
        if ($normalizedType === '*') {
            self::$globalDefinitions[$definition['key']] = $definition;
            return;
        }

        if (!array_key_exists($normalizedType, self::$definitionsByType)) {
            self::$definitionsByType[$normalizedType] = [];
        }
        self::$definitionsByType[$normalizedType][$definition['key']] = $definition;
    }

    /**
     * @param array<string,mixed> $args
     */
    public static function registerShared(string $key, array $args = []): void
    {
        self::register('*', $key, $args);
    }

    /**
     * @return array<string,PostMetaDefinition>
     */
    public static function forType(string $postType): array
    {
        $normalizedType = trim($postType);
        $result = self::$globalDefinitions;
        if ($normalizedType !== '' && isset(self::$definitionsByType[$normalizedType])) {
            foreach (self::$definitionsByType[$normalizedType] as $key => $definition) {
                $result[$key] = $definition;
            }
        }

        return $result;
    }

    public static function definition(string $postType, string $key): ?array
    {
        $normalizedKey = self::normalizeKey($key);
        $normalizedType = trim($postType);
        if ($normalizedType !== '' && isset(self::$definitionsByType[$normalizedType][$normalizedKey])) {
            return self::$definitionsByType[$normalizedType][$normalizedKey];
        }

        return self::$globalDefinitions[$normalizedKey] ?? null;
    }

    /**
     * @param mixed $value
     * @return array{key:string,type:string,value:mixed,storage:?string}
     */
    public static function prepareForStorage(string $postType, string $key, mixed $value): array
    {
        $normalizedKey = self::normalizeKey($key);
        $definition = self::definition($postType, $normalizedKey);
        $type = $definition['type'] ?? 'string';

        if ($definition && $definition['sanitize_callback']) {
            $sanitized = ($definition['sanitize_callback'])($value, $postType, $normalizedKey, $definition);
        } else {
            $sanitized = self::sanitizeByType($definition, $value, $normalizedKey);
        }

        if ($definition && $definition['options'] !== [] && $sanitized !== null) {
            $stringValue = self::stringifyOptionValue($sanitized);
            if (!array_key_exists($stringValue, $definition['options'])) {
                $label = $definition['label'] ?? $normalizedKey;
                throw new InvalidArgumentException(sprintf('Hodnota pole "%s" není povolena.', $label));
            }
            $sanitized = $stringValue;
        }

        return [
            'key'     => $normalizedKey,
            'type'    => $type,
            'value'   => $sanitized,
            'storage' => self::encodeForStorage($type, $sanitized),
        ];
    }

    /**
     * @param array<string,mixed> $input
     * @return array{values:array<string,array{key:string,type:string,value:mixed,storage:?string}>,errors:array<string,array<int,string>>}
     */
    public static function collectInput(string $postType, array $input): array
    {
        $values = [];
        $errors = [];
        foreach ($input as $key => $raw) {
            $normalizedKey = trim((string)$key);
            if ($normalizedKey === '') {
                continue;
            }
            try {
                $values[$normalizedKey] = self::prepareForStorage($postType, $normalizedKey, $raw);
            } catch (InvalidArgumentException $exception) {
                $errors['meta[' . $normalizedKey . ']'][] = $exception->getMessage();
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /**
     * @param array<string,array{meta_type:string,meta_value:?string}> $rows
     * @return array<string,mixed>
     */
    public static function hydrateAll(string $postType, array $rows): array
    {
        $definitions = self::forType($postType);
        $result = [];
        foreach ($definitions as $definition) {
            $result[$definition['key']] = self::resolveDefault($definition, $postType);
        }

        foreach ($rows as $key => $row) {
            $normalizedKey = trim((string)$key);
            if ($normalizedKey === '') {
                continue;
            }
            $storedType = isset($row['meta_type']) ? (string)$row['meta_type'] : null;
            $storedValue = $row['meta_value'] ?? null;
            $result[$normalizedKey] = self::hydrateValue($postType, $normalizedKey, $storedValue, $storedType);
        }

        return $result;
    }

    public static function hydrateValue(string $postType, string $key, ?string $storedValue, ?string $storedType): mixed
    {
        $normalizedKey = self::normalizeKey($key);
        $definition = self::definition($postType, $normalizedKey);
        $type = $storedType !== null ? self::normalizeMetaType($storedType) : ($definition['type'] ?? 'string');

        if ($storedValue === null) {
            return $definition ? self::resolveDefault($definition, $postType) : null;
        }

        if ($definition && $definition['hydrate_callback']) {
            return ($definition['hydrate_callback'])($storedValue, $storedType, $definition);
        }

        return self::decodeFromStorage($type, $storedValue);
    }

    /**
     * @return array<string,mixed>
     */
    public static function defaults(string $postType): array
    {
        $definitions = self::forType($postType);
        $result = [];
        foreach ($definitions as $definition) {
            $result[$definition['key']] = self::resolveDefault($definition, $postType);
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $args
     * @return PostMetaDefinition
     */
    private static function buildDefinition(string $key, array $args): array
    {
        $normalizedKey = self::normalizeKey($key);
        $type = self::normalizeMetaType((string)($args['type'] ?? 'string'));
        $label = isset($args['label']) ? trim((string)$args['label']) : '';
        if ($label === '') {
            $label = self::humanizeKey($normalizedKey);
        }
        $description = isset($args['description']) ? trim((string)$args['description']) : '';
        $group = isset($args['group']) ? trim((string)$args['group']) : null;
        if ($group === '') {
            $group = null;
        }
        $required = (bool)($args['required'] ?? false);
        $allowNull = array_key_exists('allow_null', $args) ? (bool)$args['allow_null'] : !$required;
        $emptyIsNull = array_key_exists('empty_is_null', $args) ? (bool)$args['empty_is_null'] : true;
        $showInAdmin = array_key_exists('show_in_admin', $args) ? (bool)$args['show_in_admin'] : true;
        $options = self::normalizeOptions($args['options'] ?? []);

        $sanitize = null;
        if (isset($args['sanitize_callback'])) {
            if (!is_callable($args['sanitize_callback'])) {
                throw new InvalidArgumentException('sanitize_callback for meta "' . $normalizedKey . '" musí být funkce.');
            }
            /** @var callable $cb */
            $cb = $args['sanitize_callback'];
            $sanitize = $cb;
        }

        $hydrate = null;
        if (isset($args['hydrate_callback'])) {
            if (!is_callable($args['hydrate_callback'])) {
                throw new InvalidArgumentException('hydrate_callback pro meta "' . $normalizedKey . '" musí být funkce.');
            }
            /** @var callable $hb */
            $hb = $args['hydrate_callback'];
            $hydrate = $hb;
        }

        return [
            'key'               => $normalizedKey,
            'type'              => $type,
            'label'             => $label,
            'description'       => $description,
            'default'           => $args['default'] ?? null,
            'show_in_admin'     => $showInAdmin,
            'group'             => $group,
            'options'           => $options,
            'required'          => $required,
            'allow_null'        => $allowNull,
            'empty_is_null'     => $emptyIsNull,
            'sanitize_callback' => $sanitize,
            'hydrate_callback'  => $hydrate,
        ];
    }

    private static function normalizeKey(string $key): string
    {
        $normalized = trim($key);
        if ($normalized === '') {
            throw new InvalidArgumentException('Meta klíč nesmí být prázdný.');
        }
        if (!preg_match('/^[A-Za-z0-9:_\-.]+$/', $normalized)) {
            throw new InvalidArgumentException(sprintf('Meta klíč "%s" obsahuje nepovolené znaky.', $key));
        }

        return $normalized;
    }

    /**
     * @param PostMetaDefinition|null $definition
     */
    private static function sanitizeByType(?array $definition, mixed $value, string $key): mixed
    {
        $type = $definition['type'] ?? 'string';
        $allowNull = $definition['allow_null'] ?? true;
        $emptyIsNull = $definition['empty_is_null'] ?? true;

        if ($value === null || ($value === '' && $allowNull)) {
            return $allowNull ? null : self::throwRequired($definition, $key);
        }

        return match ($type) {
            'int'   => self::sanitizeInt($value, $definition, $key, $allowNull),
            'float' => self::sanitizeFloat($value, $definition, $key, $allowNull),
            'bool'  => self::sanitizeBool($value),
            'json'  => self::sanitizeJson($value, $definition, $key, $allowNull),
            'text'  => self::sanitizeText($value, $definition, $key, $allowNull, $emptyIsNull),
            default => self::sanitizeString($value, $definition, $key, $allowNull, $emptyIsNull),
        };
    }

    private static function sanitizeString(mixed $value, ?array $definition, string $key, bool $allowNull, bool $emptyIsNull): ?string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException(self::errorMessage($definition, $key, 'musí být text.'));
        }
        $text = trim((string)$value);
        if ($text === '' && $emptyIsNull) {
            return $allowNull ? null : self::throwRequired($definition, $key);
        }

        return $text;
    }

    private static function sanitizeText(mixed $value, ?array $definition, string $key, bool $allowNull, bool $emptyIsNull): ?string
    {
        if (!is_scalar($value)) {
            throw new InvalidArgumentException(self::errorMessage($definition, $key, 'musí být text.'));
        }
        $text = (string)$value;
        if ($emptyIsNull && trim($text) === '') {
            return $allowNull ? null : self::throwRequired($definition, $key);
        }

        return $text;
    }

    private static function sanitizeInt(mixed $value, ?array $definition, string $key, bool $allowNull): ?int
    {
        if ($value === '' || $value === null) {
            return $allowNull ? null : self::throwRequired($definition, $key);
        }
        if (is_int($value)) {
            return $value;
        }
        if (is_numeric($value) && (int)$value == $value) {
            return (int)$value;
        }

        throw new InvalidArgumentException(self::errorMessage($definition, $key, 'musí být celé číslo.'));
    }

    private static function sanitizeFloat(mixed $value, ?array $definition, string $key, bool $allowNull): ?float
    {
        if ($value === '' || $value === null) {
            return $allowNull ? null : self::throwRequired($definition, $key);
        }
        if (is_float($value) || is_int($value)) {
            return (float)$value;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }

        throw new InvalidArgumentException(self::errorMessage($definition, $key, 'musí být číslo.'));
    }

    private static function sanitizeBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        $string = is_scalar($value) ? strtolower(trim((string)$value)) : '';
        if ($string === '') {
            return false;
        }
        if (in_array($string, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($string, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return false;
    }

    private static function sanitizeJson(mixed $value, ?array $definition, string $key, bool $allowNull): mixed
    {
        if ($value === null || $value === '') {
            return $allowNull ? null : self::throwRequired($definition, $key);
        }
        if (is_array($value) || is_object($value)) {
            return $value;
        }
        if (!is_scalar($value)) {
            throw new InvalidArgumentException(self::errorMessage($definition, $key, 'musí být validní JSON.'));
        }
        $decoded = json_decode((string)$value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        throw new InvalidArgumentException(self::errorMessage($definition, $key, 'musí být validní JSON.'));
    }

    private static function encodeForStorage(string $type, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int'   => (string)(int)$value,
            'float' => (string)(float)$value,
            'bool'  => $value ? '1' : '0',
            'json'  => self::encodeJson($value),
            default => (string)$value,
        };
    }

    private static function decodeFromStorage(string $type, string $value): mixed
    {
        return match ($type) {
            'int'   => (int)$value,
            'float' => (float)$value,
            'bool'  => in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true),
            'json'  => self::decodeJson($value),
            default => $value,
        };
    }

    private static function encodeJson(mixed $value): string
    {
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new InvalidArgumentException('Nepodařilo se serializovat JSON.');
        }

        return $encoded;
    }

    private static function decodeJson(string $value): mixed
    {
        if ($value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        return $value;
    }

    private static function resolveDefault(array $definition, string $postType): mixed
    {
        $default = $definition['default'] ?? null;
        if (is_callable($default)) {
            return $default($postType, $definition['key'], $definition);
        }

        return $default;
    }

    private static function normalizeOptions(mixed $options): array
    {
        if ($options === null) {
            return [];
        }
        if (!is_array($options)) {
            throw new InvalidArgumentException('Options must be array.');
        }
        $normalized = [];
        foreach ($options as $key => $value) {
            if (is_array($value)) {
                $optionValue = isset($value['value']) ? (string)$value['value'] : '';
                $label = isset($value['label']) ? (string)$value['label'] : $optionValue;
            } else {
                $optionValue = is_int($key) ? (string)$value : (string)$key;
                $label = (string)$value;
            }
            $optionValue = trim($optionValue);
            if ($optionValue === '') {
                continue;
            }
            $normalized[$optionValue] = trim($label);
        }

        return $normalized;
    }

    private static function normalizeMetaType(string $type): string
    {
        $normalized = strtolower(trim($type));
        return match ($normalized) {
            'integer', 'int', 'number' => 'int',
            'float', 'double', 'decimal' => 'float',
            'bool', 'boolean' => 'bool',
            'json', 'array', 'object' => 'json',
            'text', 'textarea', 'html' => 'text',
            default => 'string',
        };
    }

    private static function humanizeKey(string $key): string
    {
        $parts = preg_split('/[_:\\-.]+/', $key) ?: [];
        $parts = array_filter($parts, static fn(string $part): bool => $part !== '');
        if ($parts === []) {
            return ucfirst($key);
        }
        $normalized = array_map(static fn(string $part): string => ucfirst(strtolower($part)), $parts);
        return implode(' ', $normalized);
    }

    private static function stringifyOptionValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_scalar($value)) {
            return (string)$value;
        }

        return '';
    }

    private static function errorMessage(?array $definition, string $key, string $suffix): string
    {
        $label = $definition['label'] ?? $key;
        return sprintf('Pole "%s" %s', $label, $suffix);
    }

    private static function throwRequired(?array $definition, string $key): never
    {
        throw new InvalidArgumentException(self::errorMessage($definition, $key, 'je povinné.'));
    }
}
