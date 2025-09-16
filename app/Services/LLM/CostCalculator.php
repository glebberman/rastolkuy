<?php

declare(strict_types=1);

namespace App\Services\LLM;

class CostCalculator
{
    /**
     * Рассчитать стоимость использования LLM.
     */
    public function calculateCost(int $inputTokens, int $outputTokens, ?string $model = null): float
    {
        // Используем модель по умолчанию, если не указана
        if ($model === null) {
            $defaultModel = config('llm.default_model', 'claude-3-5-sonnet-20241022');
            $model = is_string($defaultModel) ? $defaultModel : 'claude-3-5-sonnet-20241022';
        }

        // Получаем цены из конфигурации
        $pricing = config("llm.pricing.{$model}");

        if (!is_array($pricing) || !isset($pricing['input_per_million'], $pricing['output_per_million'])) {
            // Fallback к базовым ценам Claude 3.5 Sonnet, если модель не найдена
            $pricing = [
                'input_per_million' => 3.00,
                'output_per_million' => 15.00,
            ];
        }

        $inputCost = ($inputTokens / 1_000_000) * (float) $pricing['input_per_million'];
        $outputCost = ($outputTokens / 1_000_000) * (float) $pricing['output_per_million'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Получить информацию о ценах для модели.
     */
    public function getPricingInfo(string $model): array
    {
        $pricing = config("llm.pricing.{$model}");

        if (!is_array($pricing) || !isset($pricing['input_per_million'], $pricing['output_per_million'])) {
            return [
                'model' => $model,
                'found' => false,
                'input_per_million' => null,
                'output_per_million' => null,
            ];
        }

        return [
            'model' => $model,
            'found' => true,
            'input_per_million' => (float) $pricing['input_per_million'],
            'output_per_million' => (float) $pricing['output_per_million'],
        ];
    }

    /**
     * Получить все доступные модели с ценами.
     */
    public function getAllPricing(): array
    {
        $allPricing = config('llm.pricing', []);

        if (!is_array($allPricing)) {
            return [];
        }

        return array_map(static function ($pricing, $model) {
            if (!is_array($pricing) || !isset($pricing['input_per_million'], $pricing['output_per_million'])) {
                return [
                    'model' => $model,
                    'input_per_million' => null,
                    'output_per_million' => null,
                ];
            }

            return [
                'model' => $model,
                'input_per_million' => (float) $pricing['input_per_million'],
                'output_per_million' => (float) $pricing['output_per_million'],
            ];
        }, $allPricing, array_keys($allPricing));
    }

    /**
     * Оценить количество токенов на основе текста.
     */
    public function estimateTokens(string $text): int
    {
        // Простая эвристика: ~4 байта на токен для русского и английского текста
        // В реальности это зависит от токенизатора, но для примерной оценки подходит
        return (int) (mb_strlen($text) / 4);
    }

    /**
     * Оценить количество токенов на основе размера файла.
     */
    public function estimateTokensFromFileSize(int $fileSizeBytes): int
    {
        // Используем ту же эвристику
        return (int) ($fileSizeBytes / 4);
    }

    /**
     * Оценить количество выходных токенов по типу задачи.
     */
    public function estimateOutputTokensByTaskType(
        int $inputTokens,
        string $taskType,
        array $structureAnalysis = [],
    ): int {
        $taskConfig = config("credits.output_token_multipliers.{$taskType}");

        if (!is_array($taskConfig)) {
            // Fallback к значениям по умолчанию для translation
            $taskConfig = config('credits.output_token_multipliers.translation', [
                'content_multiplier' => 0.5,
                'json_overhead' => 0.3,
                'summary_base_tokens' => 150,
                'min_multiplier' => 0.4,
                'max_multiplier' => 1.0,
            ]);
        }

        // Базовые параметры с проверкой типов
        $contentMultiplier = (float) (is_array($taskConfig) && isset($taskConfig['content_multiplier']) ? $taskConfig['content_multiplier'] : 0.5);
        $jsonOverhead = (float) (is_array($taskConfig) && isset($taskConfig['json_overhead']) ? $taskConfig['json_overhead'] : 0.3);
        $summaryBaseTokens = (int) (is_array($taskConfig) && isset($taskConfig['summary_base_tokens']) ? $taskConfig['summary_base_tokens'] : 150);
        $minMultiplier = (float) (is_array($taskConfig) && isset($taskConfig['min_multiplier']) ? $taskConfig['min_multiplier'] : 0.4);
        $maxMultiplier = (float) (is_array($taskConfig) && isset($taskConfig['max_multiplier']) ? $taskConfig['max_multiplier'] : 1.0);

        // Расчет контентных токенов (сжатые объяснения)
        $contentTokens = $inputTokens * $contentMultiplier;

        // Коэффициент сложности на основе количества секций
        $sectionsCount = $structureAnalysis['sections_count'] ?? 5;
        $complexityMultiplier = 1.0 + ($sectionsCount * 0.02); // +2% за секцию

        // Расчет JSON токенов (структура, якоря, риски)
        $jsonTokens = $inputTokens * $jsonOverhead * $complexityMultiplier;

        // Итого токенов
        $totalTokens = $contentTokens + $jsonTokens + $summaryBaseTokens;

        // Применение ограничений
        $finalMultiplier = $totalTokens / $inputTokens;
        $finalMultiplier = max($minMultiplier, min($maxMultiplier, $finalMultiplier));

        return (int) round($inputTokens * $finalMultiplier);
    }
}
