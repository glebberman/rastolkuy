<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\PromptSystem;
use App\PromptTemplate;
use App\Services\Prompt\SchemaManager;
use Illuminate\Console\Command;

class SetupPromptSystems extends Command
{
    protected $signature = 'prompt:setup {--force : Force recreate existing systems}';

    protected $description = 'Setup initial prompt systems and templates for legal document analysis';

    public function handle(): int
    {
        $this->info('Setting up prompt systems for legal document analysis...');

        if ($this->option('force')) {
            $this->warn('Forcing recreation of existing systems...');
            PromptSystem::truncate();
            PromptTemplate::truncate();
        }

        $this->setupTranslationSystem();
        $this->setupContradictionSystem();
        $this->setupAmbiguitySystem();
        $this->setupGeneralSystem();

        $this->info('✅ Prompt systems setup completed successfully!');

        return Command::SUCCESS;
    }

    private function setupTranslationSystem(): void
    {
        $this->info('Creating translation system...');

        $schemaManager = app(SchemaManager::class);
        $schema = $schemaManager->getSchema('translation_response');

        $system = PromptSystem::create([
            'name' => 'legal_translation',
            'type' => 'translation',
            'description' => 'Система для перевода юридических документов в простой язык',
            'system_prompt' => 'Ты - эксперт по юридическим документам. Твоя задача - переводить сложные юридические тексты в простой, понятный язык, сохраняя при этом точность и важные правовые нюансы. Всегда объясняй юридические термины простыми словами.',
            'schema' => $schema,
            'default_parameters' => [
                'target_audience' => 'general_public',
                'complexity_level' => 'beginner',
                'preserve_legal_terms' => true,
            ],
            'version' => '1.0.0',
            'metadata' => [
                'author' => 'system',
                'created_for' => 'RAS-10',
            ],
        ]);

        PromptTemplate::create([
            'prompt_system_id' => $system->id,
            'name' => 'basic_translation',
            'template' => 'Переведи следующий юридический документ в простой, понятный язык по секциям:

{{ document }}

{% if document_structure %}
Структура документа с якорями для вставки переводов:
{{ document_structure }}

ВАЖНО: Для каждой секции создай перевод с указанием якоря куда должен быть вставлен переведенный текст. Используй массив section_translations в ответе.
{% endif %}

Требования:
- Переведи каждую секцию документа отдельно
- Объясни сложные термины простыми словами в общем списке legal_terms_preserved
- Сохрани важную правовую информацию
- Для каждой секции укажи соответствующий якорь для вставки перевода
- Создай краткое резюме каждой секции

{% if target_language %}
Целевой уровень сложности: {{ target_language }}
{% endif %}

{% if preserve_terms %}
Сохрани оригинальные юридические термины с объяснениями в отдельном списке.
{% endif %}',
            'required_variables' => ['document'],
            'optional_variables' => ['target_language', 'preserve_terms', 'document_structure'],
            'description' => 'Базовый шаблон для перевода юридических документов',
        ]);

        $this->line('  ✓ Translation system created');
    }

    private function setupContradictionSystem(): void
    {
        $this->info('Creating contradiction analysis system...');

        $schemaManager = app(SchemaManager::class);
        $schema = $schemaManager->getSchema('contradiction_response');

        $system = PromptSystem::create([
            'name' => 'contradiction_analyzer',
            'type' => 'contradiction',
            'description' => 'Система для анализа противоречий в юридических документах',
            'system_prompt' => 'Ты - аналитик юридических документов, специализирующийся на поиске противоречий. Анализируй документы на предмет логических, правовых и процедурных противоречий. Будь точным и обоснованным в своих выводах.',
            'schema' => $schema,
            'default_parameters' => [
                'analysis_depth' => 'detailed',
                'include_suggestions' => true,
            ],
            'version' => '1.0.0',
            'metadata' => [
                'author' => 'system',
                'created_for' => 'RAS-10',
            ],
        ]);

        PromptTemplate::create([
            'prompt_system_id' => $system->id,
            'name' => 'full_contradiction_analysis',
            'template' => 'Проанализируй следующий юридический документ на предмет противоречий:

{{ document }}

{% if document_structure %}
Структура документа с якорями:
{{ document_structure }}

ВАЖНО: Для локаций противоречий указывай якоря секций где они обнаружены.
{% endif %}

Найди и опиши:
1. Логические противоречия
2. Правовые несоответствия
3. Процедурные конфликты
4. Терминологические расхождения
5. Временные противоречия

Для каждого найденного противоречия укажи:
- Тип противоречия
- Местоположение в документе с указанием якоря секции
- Уровень критичности
- Предложения по устранению

{% if context %}
Дополнительный контекст: {{ context }}
{% endif %}

{% if focus_areas %}
Особое внимание обрати на: {{ focus_areas }}
{% endif %}',
            'required_variables' => ['document'],
            'optional_variables' => ['context', 'focus_areas', 'document_structure'],
            'description' => 'Полный анализ противоречий в документе',
        ]);

        $this->line('  ✓ Contradiction analysis system created');
    }

    private function setupAmbiguitySystem(): void
    {
        $this->info('Creating ambiguity analysis system...');

        $schemaManager = app(SchemaManager::class);
        $schema = $schemaManager->getSchema('ambiguity_response');

        $system = PromptSystem::create([
            'name' => 'ambiguity_detector',
            'type' => 'ambiguity',
            'description' => 'Система для выявления неоднозначностей в юридических документах',
            'system_prompt' => 'Ты - эксперт по анализу неоднозначностей в юридических текстах. Выявляй двусмысленные формулировки, неточные определения и места, которые могут толковаться по-разному. Предлагай конкретные улучшения.',
            'schema' => $schema,
            'default_parameters' => [
                'sensitivity_level' => 'high',
                'include_risk_assessment' => true,
            ],
            'version' => '1.0.0',
            'metadata' => [
                'author' => 'system',
                'created_for' => 'RAS-10',
            ],
        ]);

        PromptTemplate::create([
            'prompt_system_id' => $system->id,
            'name' => 'comprehensive_ambiguity_check',
            'template' => 'Проанализируй следующий юридический документ на предмет неоднозначностей:

{{ document }}

{% if document_structure %}
Структура документа с якорями:
{{ document_structure }}

ВАЖНО: Для локаций неоднозначностей указывай в каких секциях они найдены.
{% endif %}

Найди и опиши:
1. Семантические неоднозначности (многозначные слова/фразы)
2. Синтаксические неоднозначности (неясная структура предложений)
3. Референциальные неоднозначности (неясные ссылки)
4. Неоднозначности области действия
5. Временные неоднозначности
6. Условные неоднозначности

Для каждой неоднозначности укажи:
- Тип неоднозначности
- Возможные интерпретации
- Уровень риска
- Предложения по улучшению
- Местоположение в секции документа

{% if legal_context %}
Правовой контекст: {{ legal_context }}
{% endif %}

{% if priority_sections %}
Приоритетные разделы для анализа: {{ priority_sections }}
{% endif %}',
            'required_variables' => ['document'],
            'optional_variables' => ['legal_context', 'priority_sections', 'document_structure'],
            'description' => 'Комплексная проверка неоднозначностей',
        ]);

        $this->line('  ✓ Ambiguity analysis system created');
    }

    private function setupGeneralSystem(): void
    {
        $this->info('Creating general analysis system...');

        $schemaManager = app(SchemaManager::class);
        $schema = $schemaManager->getSchema('general_response');

        $system = PromptSystem::create([
            'name' => 'general_analyzer',
            'type' => 'general',
            'description' => 'Универсальная система для общего анализа юридических документов',
            'system_prompt' => 'Ты - универсальный аналитик юридических документов. Можешь выполнять различные виды анализа: извлечение ключевой информации, резюмирование, структурный анализ, оценка качества и другие задачи по работе с правовыми текстами.',
            'schema' => $schema,
            'default_parameters' => [
                'analysis_type' => 'general',
                'detail_level' => 'medium',
            ],
            'version' => '1.0.0',
            'metadata' => [
                'author' => 'system',
                'created_for' => 'RAS-10',
            ],
        ]);

        PromptTemplate::create([
            'prompt_system_id' => $system->id,
            'name' => 'document_summary',
            'template' => 'Проанализируй и создай краткое резюме следующего юридического документа:

{{ document }}

{% if document_structure %}
Структура документа с якорями:
{{ document_structure }}
{% endif %}

Включи в резюме:
- Тип документа
- Основную цель/предмет
- Ключевые стороны и их обязательства
- Важные сроки и условия
- Потенциальные риски или важные моменты

{% if focus_on %}
Особое внимание обрати на: {{ focus_on }}
{% endif %}

{% if target_audience %}
Адаптируй резюме для: {{ target_audience }}
{% endif %}',
            'required_variables' => ['document'],
            'optional_variables' => ['focus_on', 'target_audience', 'document_structure'],
            'description' => 'Создание краткого резюме документа',
        ]);

        PromptTemplate::create([
            'prompt_system_id' => $system->id,
            'name' => 'key_extraction',
            'template' => 'Извлеки ключевую информацию из следующего юридического документа:

{{ document }}

{% if document_structure %}
Структура документа с якорями:
{{ document_structure }}
{% endif %}

Найди и структурированно представь:
- Даты и сроки
- Денежные суммы и платежи
- Имена и названия организаций
- Номера документов и ссылки
- Права и обязанности сторон
- Условия и ограничения

{% if extraction_type %}
Тип извлечения: {{ extraction_type }}
{% endif %}

Представь результат в структурированном виде.',
            'required_variables' => ['document'],
            'optional_variables' => ['extraction_type', 'document_structure'],
            'description' => 'Извлечение ключевой информации из документа',
        ]);

        $this->line('  ✓ General analysis system created');
    }
}
