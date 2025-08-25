<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PromptSystem;
use App\Models\PromptTemplate;
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
        $schema = $schemaManager->getSchema('anchor_translation_response');

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
            'template' => 'Переведи следующий юридический документ в простой, понятный язык по секциям.

ДОКУМЕНТ:
{{ document }}

{% if anchor_list %}
ВАЖНО: В документе есть якоря секций. Ответь в формате JSON:
{
  "sections": [
    {"anchor": "anchor_id", "content": "переведенный текст", "type": "translation"},
    ...
  ]
}

Доступные якоря: {{ anchor_list }}

Переведи каждую секцию отдельно, указав правильный якорь.
{% else %}
Ответь в формате JSON:
{
  "sections": [
    {"anchor": "section_1", "content": "переведенный текст", "type": "translation"}
  ]
}
{% endif %}

Требования:
- Переведи каждую секцию документа отдельно
- Объясни сложные юридические термины простыми словами
- Сохрани важную правовую информацию
- Используй понятный язык для обычного человека

{% if target_audience %}
Целевая аудитория: {{ target_audience }}
{% endif %}',
            'required_variables' => ['document'],
            'optional_variables' => ['anchor_list', 'target_audience'],
            'description' => 'Базовый шаблон для перевода юридических документов в JSON формате',
        ]);

        $this->line('  ✓ Translation system created');
    }

    private function setupContradictionSystem(): void
    {
        $this->info('Creating contradiction analysis system...');

        $schemaManager = app(SchemaManager::class);
        $schema = $schemaManager->getSchema('anchor_analysis_response');

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
            'template' => 'Проанализируй следующий юридический документ на предмет противоречий.

ДОКУМЕНТ:
{{ document }}

{% if anchor_list %}
ВАЖНО: В документе есть якоря секций. Ответь в формате JSON:
{
  "sections": [
    {"anchor": "anchor_id", "content": "найденное противоречие", "analysis_type": "contradiction", "severity": "high"},
    ...
  ]
}

Доступные якоря: {{ anchor_list }}

Проанализируй каждую секцию отдельно, указав правильный якорь.
{% else %}
Ответь в формате JSON:
{
  "sections": [
    {"anchor": "section_1", "content": "найденное противоречие", "analysis_type": "contradiction", "severity": "medium"}
  ]
}
{% endif %}

Найди и опиши:
1. Логические противоречия
2. Правовые несоответствия
3. Процедурные конфликты
4. Терминологические расхождения
5. Временные противоречия

Для каждого найденного противоречия укажи:
- Тип противоречия в analysis_type
- Уровень критичности в severity (critical, high, medium, low)
- Описание противоречия в content
- Предложения по устранению в suggestions (массив строк)

{% if context %}
Дополнительный контекст: {{ context }}
{% endif %}',
            'required_variables' => ['document'],
            'optional_variables' => ['anchor_list', 'context'],
            'description' => 'Полный анализ противоречий в JSON формате',
        ]);

        $this->line('  ✓ Contradiction analysis system created');
    }

    private function setupAmbiguitySystem(): void
    {
        $this->info('Creating ambiguity analysis system...');

        $schemaManager = app(SchemaManager::class);
        $schema = $schemaManager->getSchema('anchor_analysis_response');

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
            'template' => 'Проанализируй следующий юридический документ на предмет неоднозначностей.

ДОКУМЕНТ:  
{{ document }}

{% if anchor_list %}
ВАЖНО: В документе есть якоря секций. Ответь в формате JSON:
{
  "sections": [
    {"anchor": "anchor_id", "content": "найденная неоднозначность", "analysis_type": "ambiguity", "severity": "medium"},
    ...
  ]
}

Доступные якоря: {{ anchor_list }}

Проанализируй каждую секцию отдельно, указав правильный якорь.
{% else %}
Ответь в формате JSON:
{
  "sections": [
    {"anchor": "section_1", "content": "найденная неоднозначность", "analysis_type": "ambiguity", "severity": "medium"}
  ]
}
{% endif %}

Найди и опиши:
1. Семантические неоднозначности (многозначные слова/фразы)
2. Синтаксические неоднозначности (неясная структура предложений)
3. Референциальные неоднозначности (неясные ссылки)
4. Неоднозначности области действия
5. Временные неоднозначности
6. Условные неоднозначности

Для каждой неоднозначности укажи:
- Тип неоднозначности в analysis_type
- Возможные интерпретации в content
- Уровень риска в severity (critical, high, medium, low)
- Предложения по улучшению в suggestions (массив строк)

{% if legal_context %}
Правовой контекст: {{ legal_context }}
{% endif %}',
            'required_variables' => ['document'],
            'optional_variables' => ['anchor_list', 'legal_context'],
            'description' => 'Комплексная проверка неоднозначностей в JSON формате',
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
