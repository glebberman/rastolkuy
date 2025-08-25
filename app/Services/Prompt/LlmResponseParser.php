<?php

declare(strict_types=1);

namespace App\Services\Prompt;

use App\Services\Prompt\DTOs\LlmParsingRequest;
use App\Services\Prompt\DTOs\ParsedLlmResponse;
use Exception;
use Illuminate\Support\Facades\Log;
use JsonException;

final readonly class LlmResponseParser
{
    public function __construct(
        private MetadataExtractorManager $metadataExtractorManager,
    ) {
    }

    public function parse(LlmParsingRequest $request): ParsedLlmResponse
    {
        $warnings = [];
        $errors = [];

        try {
            // Извлекаем JSON из ответа
            $jsonData = $this->extractJsonFromResponse($request->rawResponse);

            if ($jsonData === null) {
                return $this->createFailureResponse(
                    request: $request,
                    errors: ['Failed to extract valid JSON from LLM response'],
                    warnings: $warnings,
                );
            }

            // Валидируем схему если она предоставлена
            if ($request->expectedSchema !== null) {
                $schemaValidation = $this->validateAgainstSchema($jsonData, $request->expectedSchema);

                if (!$schemaValidation['valid']) {
                    $errors = array_merge($errors, $schemaValidation['errors']);
                    $warnings = array_merge($warnings, $schemaValidation['warnings']);
                }
            }

            // Валидируем якоря если они предоставлены
            $anchorValidation = $this->validateAnchors($jsonData, $request->originalAnchors, $request->schemaType);

            // Применяем дополнительные правила валидации
            $rulesValidation = $this->applyValidationRules($jsonData, $request->validationRules);
            $errors = array_merge($errors, $rulesValidation['errors']);
            $warnings = array_merge($warnings, $rulesValidation['warnings']);

            // Нормализуем данные
            $normalizedData = $this->normalizeResponseData($jsonData, $request->schemaType);

            $isValid = empty($errors) || (!$request->strictValidation && !empty($normalizedData));

            return new ParsedLlmResponse(
                isValid: $isValid,
                parsedData: $normalizedData,
                anchorValidation: $anchorValidation,
                warnings: $warnings,
                errors: $errors,
                schemaType: $request->schemaType,
                rawResponse: $request->rawResponse,
                metadata: $this->extractMetadata($jsonData, $request),
            );
        } catch (JsonException $e) {
            Log::error('JSON parsing failed in LlmResponseParser', [
                'error' => $e->getMessage(),
                'response_length' => mb_strlen($request->rawResponse),
                'schema_type' => $request->schemaType,
            ]);

            return $this->createFailureResponse(
                request: $request,
                errors: ["JSON parsing failed: {$e->getMessage()}"],
                warnings: $warnings,
            );
        } catch (Exception $e) {
            Log::error('Unexpected error in LlmResponseParser', [
                'error' => $e->getMessage(),
                'schema_type' => $request->schemaType,
            ]);

            return $this->createFailureResponse(
                request: $request,
                errors: ["Unexpected parsing error: {$e->getMessage()}"],
                warnings: $warnings,
            );
        }
    }

    public function parseWithFallback(LlmParsingRequest $request): ParsedLlmResponse
    {
        $primaryResult = $this->parse($request);

        if ($primaryResult->isSuccessful()) {
            return $primaryResult;
        }

        Log::info('Primary parsing failed, attempting fallback parsing', [
            'schema_type' => $request->schemaType,
            'errors_count' => count($primaryResult->errors),
        ]);

        // Создаем более мягкий запрос для fallback
        $fallbackRequest = new LlmParsingRequest(
            rawResponse: $request->rawResponse,
            expectedSchema: null, // Убираем строгую схему
            schemaType: $request->schemaType,
            originalAnchors: $request->originalAnchors,
            validationRules: [],
            strictValidation: false,
        );

        $fallbackResult = $this->parse($fallbackRequest);

        // Объединяем ошибки из первичного парсинга с результатами fallback
        return new ParsedLlmResponse(
            isValid: $fallbackResult->isValid,
            parsedData: $fallbackResult->parsedData,
            anchorValidation: $fallbackResult->anchorValidation,
            warnings: array_merge($primaryResult->warnings, $fallbackResult->warnings, ['Used fallback parsing due to primary parsing failure']),
            errors: $fallbackResult->errors, // Используем только ошибки fallback если он успешен
            schemaType: $request->schemaType,
            rawResponse: $request->rawResponse,
            metadata: array_merge($fallbackResult->metadata, ['fallback_used' => true, 'primary_errors' => $primaryResult->errors]),
        );
    }

    private function extractJsonFromResponse(string $response): ?array
    {
        // Удаляем markdown код блоки если они есть
        $cleanResponse = preg_replace('/```json\s*/', '', $response);
        $cleanResponse = preg_replace('/```\s*$/', '', $cleanResponse ?? '');
        $cleanResponse = trim($cleanResponse ?? '');

        // Пытаемся найти JSON в ответе
        if (preg_match('/\{.*\}/s', $cleanResponse, $matches)) {
            $jsonString = $matches[0];
        } else {
            $jsonString = $cleanResponse;
        }

        try {
            $decoded = json_decode($jsonString, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : null;
        } catch (JsonException) {
            // Пытаемся исправить частые проблемы JSON
            $fixedJson = $this->attemptJsonRepair($jsonString);

            if ($fixedJson !== null) {
                try {
                    $decoded = json_decode($fixedJson, true, 512, JSON_THROW_ON_ERROR);

                    return is_array($decoded) ? $decoded : null;
                } catch (JsonException) {
                    return null;
                }
            }

            return null;
        }
    }

    private function attemptJsonRepair(string $jsonString): ?string
    {
        // Исправляем распространенные проблемы JSON
        $fixed = $jsonString;

        // Добавляем закрывающие скобки если их не хватает
        $openBraces = substr_count($fixed, '{');
        $closeBraces = substr_count($fixed, '}');

        if ($openBraces > $closeBraces) {
            $fixed .= str_repeat('}', $openBraces - $closeBraces);
        }

        // Аналогично для квадратных скобок
        $openBrackets = substr_count($fixed, '[');
        $closeBrackets = substr_count($fixed, ']');

        if ($openBrackets > $closeBrackets) {
            $fixed .= str_repeat(']', $openBrackets - $closeBrackets);
        }

        // Удаляем trailing запятые
        $fixed = preg_replace('/,(\s*[}\]])/m', '$1', $fixed) ?? $fixed;

        return $fixed !== $jsonString ? $fixed : null;
    }

    private function validateAgainstSchema(array $data, array $schema): array
    {
        $errors = [];
        $warnings = [];

        // Базовая валидация обязательных полей
        if (isset($schema['required']) && is_array($schema['required'])) {
            foreach ($schema['required'] as $requiredField) {
                if (!array_key_exists($requiredField, $data)) {
                    $errors[] = "Missing required field: {$requiredField}";
                }
            }
        }

        // Валидация типов полей
        if (isset($schema['properties']) && is_array($schema['properties'])) {
            foreach ($schema['properties'] as $field => $fieldSchema) {
                if (array_key_exists($field, $data)) {
                    $validation = $this->validateFieldType($data[$field], $fieldSchema, $field);
                    $errors = array_merge($errors, $validation['errors']);
                    $warnings = array_merge($warnings, $validation['warnings']);
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    private function validateFieldType(mixed $value, array $fieldSchema, string $fieldName): array
    {
        $errors = [];
        $warnings = [];

        if (!isset($fieldSchema['type'])) {
            return ['errors' => $errors, 'warnings' => $warnings];
        }

        $expectedType = $fieldSchema['type'];

        switch ($expectedType) {
            case 'string':
                if (!is_string($value)) {
                    $errors[] = "Field '{$fieldName}' must be a string, got " . gettype($value);
                }
                break;
            case 'number':
                if (!is_numeric($value)) {
                    $errors[] = "Field '{$fieldName}' must be a number, got " . gettype($value);
                }
                break;
            case 'integer':
                if (is_string($value)) {
                    $stringValue = $value;
                } elseif (is_scalar($value) || is_null($value)) {
                    $stringValue = (string) $value;
                } else {
                    $stringValue = '';
                }

                if (!is_int($value) && !ctype_digit($stringValue)) {
                    $errors[] = "Field '{$fieldName}' must be an integer, got " . gettype($value);
                }
                break;
            case 'array':
                if (!is_array($value)) {
                    $errors[] = "Field '{$fieldName}' must be an array, got " . gettype($value);
                }
                break;
            case 'object':
                if (!is_array($value) && !is_object($value)) {
                    $errors[] = "Field '{$fieldName}' must be an object, got " . gettype($value);
                }
                break;
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function validateAnchors(array $data, array $originalAnchors, ?string $schemaType): array
    {
        $validation = [];

        if (empty($originalAnchors)) {
            return $validation;
        }

        // Извлекаем якоря из данных в зависимости от типа схемы
        $responseAnchors = $this->extractAnchorsFromData($data, $schemaType);

        // Проверяем каждый исходный якорь
        foreach ($originalAnchors as $anchor) {
            $isValid = in_array($anchor, $responseAnchors, true);
            $validation[] = [
                'anchor' => $anchor,
                'is_valid' => $isValid,
                'found_in_response' => $isValid,
                'error' => $isValid ? null : 'Anchor not found in response',
            ];
        }

        // Проверяем на лишние якоря в ответе
        foreach ($responseAnchors as $responseAnchor) {
            if (!in_array($responseAnchor, $originalAnchors, true)) {
                $validation[] = [
                    'anchor' => $responseAnchor,
                    'is_valid' => false,
                    'found_in_response' => true,
                    'error' => 'Unexpected anchor in response',
                ];
            }
        }

        return $validation;
    }

    private function extractAnchorsFromData(array $data, ?string $schemaType): array
    {
        $anchors = [];

        // Новый упрощенный формат - все схемы используют массив sections
        if (isset($data['sections']) && is_array($data['sections'])) {
            foreach ($data['sections'] as $section) {
                if (isset($section['anchor']) && is_string($section['anchor'])) {
                    $anchors[] = $section['anchor'];
                }
            }

            return array_unique($anchors);
        }

        // Fallback для старого формата
        switch ($schemaType) {
            case 'translation':
                if (isset($data['section_translations']) && is_array($data['section_translations'])) {
                    foreach ($data['section_translations'] as $section) {
                        if (isset($section['anchor']) && is_string($section['anchor'])) {
                            $anchors[] = $section['anchor'];
                        }
                    }
                }
                break;

            case 'contradiction':
            case 'ambiguity':
                if (isset($data['contradictions_found']) && is_array($data['contradictions_found'])) {
                    foreach ($data['contradictions_found'] as $contradiction) {
                        if (isset($contradiction['locations']) && is_array($contradiction['locations'])) {
                            foreach ($contradiction['locations'] as $location) {
                                if (isset($location['anchor']) && is_string($location['anchor'])) {
                                    $anchors[] = $location['anchor'];
                                }
                            }
                        }
                    }
                }
                break;

            default:
                // Общий поиск якорей в любых полях
                $this->recursivelyExtractAnchors($data, $anchors);
                break;
        }

        return array_unique($anchors);
    }

    private function recursivelyExtractAnchors(array $data, array &$anchors): void
    {
        foreach ($data as $key => $value) {
            if ($key === 'anchor' && is_string($value)) {
                $anchors[] = $value;
            } elseif (is_array($value)) {
                $this->recursivelyExtractAnchors($value, $anchors);
            }
        }
    }

    private function applyValidationRules(array $data, array $rules): array
    {
        $errors = [];
        $warnings = [];

        foreach ($rules as $rule) {
            switch ($rule) {
                case 'anchors_required':
                    if (!$this->hasAnchors($data)) {
                        $errors[] = 'Response must contain anchor references';
                    }
                    break;

                case 'confidence_required':
                    if (!isset($data['confidence']) && !isset($data['analysis_summary']['confidence'])) {
                        $warnings[] = 'Confidence score not found in response';
                    }
                    break;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    private function hasAnchors(array $data): bool
    {
        $anchors = [];
        $this->recursivelyExtractAnchors($data, $anchors);

        return !empty($anchors);
    }

    private function normalizeResponseData(array $data, ?string $schemaType): array
    {
        // Нормализация данных в зависимости от типа схемы
        $normalized = $data;

        // Общая нормализация
        $this->normalizeStrings($normalized);
        $this->normalizeNumbers($normalized);

        return $normalized;
    }

    private function normalizeStrings(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (is_string($value)) {
                $value = trim($value);
                // Нормализуем пробелы
                $value = preg_replace('/\s+/', ' ', $value) ?? $value;
            } elseif (is_array($value)) {
                $this->normalizeStrings($value);
            }
        }
    }

    private function normalizeNumbers(array &$data): void
    {
        foreach ($data as $key => &$value) {
            if (is_string($value) && is_numeric($value)) {
                // Конвертируем строковые числа в числа
                $value = str_contains($value, '.') ? (float) $value : (int) $value;
            } elseif (is_array($value)) {
                $this->normalizeNumbers($value);
            }
        }
    }

    private function extractMetadata(array $data, LlmParsingRequest $request): array
    {
        $baseMetadata = [
            'response_length' => mb_strlen($request->rawResponse),
            'parsed_fields_count' => count($data),
            'schema_type' => $request->schemaType,
            'has_anchors' => $this->hasAnchors($data),
            'anchor_count' => count($this->extractAnchorsFromData($data, $request->schemaType)),
            'parsing_timestamp' => now()->toISOString(),
        ];

        // Используем специализированные экстракторы для детальных метаданных
        $detailedMetadata = $this->metadataExtractorManager->extractMetadata($data, $request->schemaType);

        return array_merge($baseMetadata, $detailedMetadata);
    }

    private function createFailureResponse(LlmParsingRequest $request, array $errors, array $warnings): ParsedLlmResponse
    {
        return new ParsedLlmResponse(
            isValid: false,
            parsedData: [],
            anchorValidation: [],
            warnings: $warnings,
            errors: $errors,
            schemaType: $request->schemaType,
            rawResponse: $request->rawResponse,
            metadata: [
                'response_length' => mb_strlen($request->rawResponse),
                'parsing_failed' => true,
                'parsing_timestamp' => now()->toISOString(),
            ],
        );
    }
}
