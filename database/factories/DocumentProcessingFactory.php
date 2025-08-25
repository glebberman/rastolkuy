<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\DocumentProcessing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentProcessing>
 */
class DocumentProcessingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $taskTypes = [
            DocumentProcessing::TASK_TRANSLATION,
            DocumentProcessing::TASK_CONTRADICTION,
            DocumentProcessing::TASK_AMBIGUITY,
        ];

        $statuses = [
            DocumentProcessing::STATUS_PENDING,
            DocumentProcessing::STATUS_PROCESSING,
            DocumentProcessing::STATUS_COMPLETED,
            DocumentProcessing::STATUS_FAILED,
        ];

        $fileTypes = [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/msword',
            'text/plain',
        ];

        $fileExtensions = ['pdf', 'docx', 'doc', 'txt'];
        $extension = $this->faker->randomElement($fileExtensions);

        return [
            'uuid' => Str::uuid()->toString(),
            'original_filename' => $this->faker->word() . '_contract.' . $extension,
            'file_path' => 'documents/' . Str::uuid()->toString() . '.' . $extension,
            'file_type' => $this->faker->randomElement($fileTypes),
            'file_size' => $this->faker->numberBetween(1024, 52428800), // 1KB - 50MB
            'task_type' => $this->faker->randomElement($taskTypes),
            'options' => [
                'model' => $this->faker->randomElement([
                    'claude-3-5-sonnet-20241022',
                    'claude-3-5-haiku-20241022',
                    'claude-sonnet-4',
                ]),
                'max_tokens' => $this->faker->randomElement([1000, 2000, 4000]),
                'temperature' => $this->faker->randomFloat(1, 0.1, 1.0),
            ],
            'anchor_at_start' => $this->faker->boolean(),
            'status' => $this->faker->randomElement($statuses),
            'result' => null, // Будет заполнено в состояниях
            'error_details' => null,
            'processing_metadata' => null,
            'processing_time_seconds' => null,
            'cost_usd' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    /**
     * Состояние для завершенной обработки.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $this->faker->dateTimeBetween('-1 week', '-1 hour');
            $completedAt = $this->faker->dateTimeBetween($startedAt, 'now');

            $taskType = is_string($attributes['task_type']) ? $attributes['task_type'] : DocumentProcessing::TASK_TRANSLATION;
            $options = is_array($attributes['options']) ? $attributes['options'] : [];
            $model = is_string($options['model'] ?? null) ? $options['model'] : 'claude-3-5-sonnet-20241022';

            return [
                'status' => DocumentProcessing::STATUS_COMPLETED,
                'result' => $this->generateSampleResult($taskType),
                'processing_metadata' => [
                    'sections_processed' => $this->faker->numberBetween(5, 50),
                    'tokens_used' => $this->faker->numberBetween(500, 5000),
                    'model_used' => $model,
                ],
                'processing_time_seconds' => $this->faker->randomFloat(2, 5.0, 120.0),
                'cost_usd' => $this->faker->randomFloat(4, 0.01, 2.5),
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
            ];
        });
    }

    /**
     * Состояние для неудачной обработки.
     */
    public function failed(): static
    {
        return $this->state(function (array $attributes) {
            $startedAt = $this->faker->dateTimeBetween('-1 week', '-1 hour');

            return [
                'status' => DocumentProcessing::STATUS_FAILED,
                'error_details' => [
                    'error_type' => $this->faker->randomElement([
                        'file_parsing_error',
                        'llm_api_error',
                        'timeout_error',
                        'validation_error',
                    ]),
                    'message' => 'Не удалось обработать документ: ' . $this->faker->sentence(),
                    'code' => $this->faker->numberBetween(4001, 5999),
                ],
                'started_at' => $startedAt,
                'completed_at' => $this->faker->dateTimeBetween($startedAt, 'now'),
            ];
        });
    }

    /**
     * Состояние для обрабатываемого документа.
     */
    public function processing(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => DocumentProcessing::STATUS_PROCESSING,
                'processing_metadata' => [
                    'progress_percentage' => $this->faker->numberBetween(10, 90),
                    'current_section' => $this->faker->numberBetween(1, 10),
                    'total_sections' => $this->faker->numberBetween(15, 50),
                ],
                'started_at' => $this->faker->dateTimeBetween('-2 hours', '-5 minutes'),
            ];
        });
    }

    /**
     * Генерация примера результата обработки.
     */
    private function generateSampleResult(string $taskType): string
    {
        $result = match ($taskType) {
            DocumentProcessing::TASK_TRANSLATION => json_encode([
                'translated_sections' => [
                    [
                        'anchor' => '<!-- SECTION_ANCHOR_general_provisions_abc123 -->',
                        'original' => 'Общие положения настоящего договора...',
                        'translation' => 'Этот раздел устанавливает основные правила...',
                        'risks' => ['Слишком общие формулировки могут быть истолкованы в пользу другой стороны'],
                    ],
                ],
                'summary' => 'Документ переведен с выделением 3 потенциальных рисков.',
            ], JSON_UNESCAPED_UNICODE),
            DocumentProcessing::TASK_CONTRADICTION => json_encode([
                'contradictions_found' => [
                    [
                        'section1' => 'Пункт 2.1',
                        'section2' => 'Пункт 5.3',
                        'description' => 'Противоречие в сроках выполнения работ',
                        'severity' => 'high',
                    ],
                ],
                'summary' => 'Найдено 1 критическое противоречие в документе.',
            ], JSON_UNESCAPED_UNICODE),
            DocumentProcessing::TASK_AMBIGUITY => json_encode([
                'ambiguous_clauses' => [
                    [
                        'section' => 'Пункт 3.2',
                        'text' => 'В разумные сроки',
                        'issue' => 'Неопределенность временных рамок',
                        'suggestion' => 'Указать конкретные сроки в днях',
                    ],
                ],
                'summary' => 'Обнаружено 2 двусмысленных формулировки.',
            ], JSON_UNESCAPED_UNICODE),
            default => json_encode(['result' => 'Обработка завершена'], JSON_UNESCAPED_UNICODE),
        };

        return $result !== false ? $result : '{"error": "Failed to encode result"}';
    }
}
