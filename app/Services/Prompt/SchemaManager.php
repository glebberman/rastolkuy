<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use App\Services\Prompt\Exceptions\PromptException;
use Illuminate\Support\Facades\File;
use JsonException;

final class SchemaManager
{
    private const SCHEMAS_PATH = 'resources/schemas';

    private array $loadedSchemas = [];

    public function __construct()
    {
    }

    public function getSchema(string $schemaName): array
    {
        if (isset($this->loadedSchemas[$schemaName])) {
            return $this->loadedSchemas[$schemaName];
        }

        $schemaPath = base_path(self::SCHEMAS_PATH . '/' . $schemaName . '.json');

        if (!File::exists($schemaPath)) {
            throw new PromptException("Schema not found: {$schemaName}");
        }

        try {
            $schemaContent = File::get($schemaPath);
            $schema = json_decode($schemaContent, true, 512, JSON_THROW_ON_ERROR);

            $this->loadedSchemas[$schemaName] = $schema;

            return is_array($schema) ? $schema : [];
        } catch (JsonException $e) {
            throw new PromptException("Invalid JSON schema: {$schemaName}. Error: {$e->getMessage()}");
        }
    }

    public function validateResponse(string $response, array $schema): array
    {
        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [
                'valid' => false,
                'errors' => ["Invalid JSON: {$e->getMessage()}"],
                'warnings' => [],
            ];
        }

        return $this->validateDataAgainstSchema(is_array($data) ? $data : [], $schema);
    }

    public function getAvailableSchemas(): array
    {
        $schemasPath = base_path(self::SCHEMAS_PATH);
        $schemaFiles = File::files($schemasPath);

        $schemas = [];

        foreach ($schemaFiles as $file) {
            $schemaName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $schemas[] = $schemaName;
        }

        return $schemas;
    }

    public function getSchemaInfo(string $schemaName): array
    {
        $schema = $this->getSchema($schemaName);

        return [
            'name' => $schemaName,
            'title' => $schema['title'] ?? $schemaName,
            'description' => $schema['description'] ?? '',
            'required_fields' => $schema['required'] ?? [],
            'properties' => array_keys($schema['properties'] ?? []),
        ];
    }

    public function generateSampleResponse(string $schemaName): array
    {
        $schema = $this->getSchema($schemaName);

        return $this->generateSampleFromSchema($schema);
    }

    public function getSchemaForPromptType(string $promptType): ?array
    {
        $schemaMapping = [
            'translation' => 'translation_response',
            'contradiction' => 'contradiction_response',
            'ambiguity' => 'ambiguity_response',
            'general' => 'general_response',
        ];

        if (!isset($schemaMapping[$promptType])) {
            return null;
        }

        try {
            return $this->getSchema($schemaMapping[$promptType]);
        } catch (PromptException) {
            return null;
        }
    }

    private function validateDataAgainstSchema(array $data, array $schema): array
    {
        $errors = [];
        $warnings = [];

        $this->validateRequired($data, $schema, $errors);
        $this->validateProperties($data, $schema, $errors, $warnings);
        $this->validateTypes($data, $schema, $errors);

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function validateRequired(array $data, array $schema, array &$errors): void
    {
        $required = $schema['required'] ?? [];

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                $errors[] = "Missing required field: {$field}";
            }
        }
    }

    private function validateProperties(array $data, array $schema, array &$errors, array &$warnings): void
    {
        $properties = $schema['properties'] ?? [];

        foreach ($data as $key => $value) {
            if (!isset($properties[$key])) {
                $warnings[] = "Unexpected field: {$key}";
                continue;
            }

            $propertySchema = $properties[$key];
            $this->validateProperty($value, $propertySchema, $key, $errors);
        }
    }

    private function validateProperty(mixed $value, array $propertySchema, string $fieldName, array &$errors): void
    {
        $expectedType = $propertySchema['type'] ?? null;

        if ($expectedType === null) {
            return;
        }

        $actualType = $this->getValueType($value);

        if (!$this->isValidType($actualType, $expectedType)) {
            $errors[] = "Field '{$fieldName}' expected type '{$expectedType}', got '{$actualType}'";

            return;
        }

        switch ($expectedType) {
            case 'string':
                $this->validateString($value, $propertySchema, $fieldName, $errors);
                break;
            case 'number':
            case 'integer':
                $this->validateNumber($value, $propertySchema, $fieldName, $errors);
                break;
            case 'array':
                $this->validateArray($value, $propertySchema, $fieldName, $errors);
                break;
            case 'object':
                if (isset($propertySchema['properties']) && is_array($value)) {
                    $nestedErrors = [];
                    $nestedWarnings = [];
                    $this->validateProperties($value, $propertySchema, $nestedErrors, $nestedWarnings);
                    $this->validateRequired($value, $propertySchema, $nestedErrors);

                    foreach ($nestedErrors as $error) {
                        $errors[] = "In field '{$fieldName}': {$error}";
                    }
                }
                break;
        }
    }

    private function validateTypes(array $data, array $schema, array &$errors): void
    {
        $schemaType = $schema['type'] ?? null;

        if ($schemaType === 'object') {
            return;
        }

        $actualType = $this->getValueType($data);

        if (!$this->isValidType($actualType, $schemaType)) {
            $errors[] = "Root element expected type '{$schemaType}', got '{$actualType}'";
        }
    }

    private function validateString(mixed $value, array $schema, string $fieldName, array &$errors): void
    {
        if (isset($schema['minLength']) && is_string($value) && mb_strlen($value) < $schema['minLength']) {
            $errors[] = "Field '{$fieldName}' must be at least {$schema['minLength']} characters long";
        }

        if (isset($schema['maxLength']) && is_string($value) && mb_strlen($value) > $schema['maxLength']) {
            $errors[] = "Field '{$fieldName}' must be no more than {$schema['maxLength']} characters long";
        }

        if (isset($schema['enum']) && !in_array($value, $schema['enum'])) {
            $allowed = implode(', ', $schema['enum']);
            $errors[] = "Field '{$fieldName}' must be one of: {$allowed}";
        }
    }

    private function validateNumber(mixed $value, array $schema, string $fieldName, array &$errors): void
    {
        if (isset($schema['minimum']) && $value < $schema['minimum']) {
            $errors[] = "Field '{$fieldName}' must be at least {$schema['minimum']}";
        }

        if (isset($schema['maximum']) && $value > $schema['maximum']) {
            $errors[] = "Field '{$fieldName}' must be no more than {$schema['maximum']}";
        }
    }

    private function validateArray(mixed $value, array $schema, string $fieldName, array &$errors): void
    {
        if (isset($schema['minItems']) && is_countable($value) && count($value) < $schema['minItems']) {
            $errors[] = "Field '{$fieldName}' must have at least {$schema['minItems']} items";
        }

        if (isset($schema['maxItems']) && is_countable($value) && count($value) > $schema['maxItems']) {
            $errors[] = "Field '{$fieldName}' must have no more than {$schema['maxItems']} items";
        }

        if (isset($schema['items']) && is_iterable($value)) {
            foreach ($value as $index => $item) {
                $indexString = is_string($index) || is_int($index) ? (string) $index : 'unknown';
                $this->validateProperty($item, $schema['items'], "{$fieldName}[{$indexString}]", $errors);
            }
        }
    }

    private function getValueType(mixed $value): string
    {
        $type = gettype($value);

        return match ($type) {
            'integer', 'double' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
            'object', 'NULL' => 'object',
            default => 'string',
        };
    }

    private function isValidType(string $actualType, string $expectedType): bool
    {
        if ($actualType === $expectedType) {
            return true;
        }

        return match ($expectedType) {
            'number' => in_array($actualType, ['number']),
            'integer' => $actualType === 'number', // JSON decode всегда дает number для чисел
            default => false,
        };
    }

    private function generateSampleFromSchema(array $schema): array
    {
        $properties = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        $sample = [];

        foreach ($properties as $propertyName => $propertySchema) {
            if (in_array($propertyName, $required) || rand(0, 1)) {
                $sample[$propertyName] = $this->generateSampleValue($propertySchema);
            }
        }

        return $sample;
    }

    private function generateSampleValue(array $schema): mixed
    {
        $type = $schema['type'] ?? 'string';

        return match ($type) {
            'string' => $this->generateSampleString($schema),
            'number' => $this->generateSampleNumber($schema),
            'integer' => $this->generateSampleInteger($schema),
            'boolean' => rand(0, 1) === 1,
            'array' => $this->generateSampleArray($schema),
            'object' => $this->generateSampleFromSchema($schema),
            default => 'sample_value',
        };
    }

    private function generateSampleString(array $schema): string
    {
        if (isset($schema['enum'])) {
            return $schema['enum'][array_rand($schema['enum'])];
        }

        $examples = [
            'Пример текста',
            'Образец строки',
            'Тестовое значение',
            'Демонстрационный контент',
        ];

        return $examples[array_rand($examples)];
    }

    private function generateSampleNumber(array $schema): float
    {
        $min = $schema['minimum'] ?? 0;
        $max = $schema['maximum'] ?? 100;

        return round($min + ($max - $min) * (rand(0, 1000) / 1000), 2);
    }

    private function generateSampleInteger(array $schema): int
    {
        $min = $schema['minimum'] ?? 0;
        $max = $schema['maximum'] ?? 100;

        return rand((int) $min, (int) $max);
    }

    private function generateSampleArray(array $schema): array
    {
        $minItems = $schema['minItems'] ?? 1;
        $maxItems = $schema['maxItems'] ?? 3;
        $itemCount = rand($minItems, $maxItems);

        $items = [];
        $itemSchema = $schema['items'] ?? ['type' => 'string'];

        for ($i = 0; $i < $itemCount; ++$i) {
            $items[] = $this->generateSampleValue($itemSchema);
        }

        return $items;
    }
}
