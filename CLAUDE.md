# Legal Translator - Переводчик юридических документов

## 📋 О проекте

**Legal Translator** - это SaaS-сервис для автоматического перевода юридических документов на простой человеческий язык. Система анализирует договоры, выделяет риски и подводные камни, предоставляя пользователям понятные объяснения каждого пункта.

### Основные возможности

- 📄 **Поддержка форматов**: PDF, DOCX, TXT
- 🤖 **ИИ-перевод**: Использует Claude API (Sonnet/Haiku) для высокоточного анализа
- ⚠️ **Выявление рисков**: Автоматическое выделение опасных пунктов
- 🎯 **Система якорей**: Точное сопоставление оригинала и перевода
- 📊 **Детальная аналитика**: Метрики качества и производительности
- 💳 **Монетизация**: Freemium модель с подписками

### Целевая аудитория

- **B2C**: Физлица, подписывающие договоры (трудовые, ипотечные, аренды)
- **Фрилансеры**: Проверка договоров с заказчиками
- **Малый бизнес**: Анализ договоров без штатного юриста
- **B2B**: Маркетплейсы, банки, страховые компании (API/White-label)

## 🏗️ Архитектура системы

### Технологический стек

```
Backend: Laravel 11 + PHP 8.3
Database: PostgreSQL + Redis
AI: Claude API (Anthropic) - гибкий выбор модели
Storage: MinIO (S3-compatible)
Queue: Redis
Testing: PHPUnit + Pest
Static Analysis: PHPStan Level 9 + Larastan
```

### Основные компоненты

#### 1. Система парсинга документов
```
app/Services/Parser/
├── ExtractorManager.php          # Фабрика экстракторов
├── Extractors/
│   ├── PdfExtractor.php         # Парсинг PDF
│   ├── DocxExtractor.php        # Парсинг DOCX  
│   ├── TxtExtractor.php         # Парсинг TXT
│   └── Support/
│       ├── ElementClassifier.php # Классификация элементов
│       ├── EncodingDetector.php  # Определение кодировки
│       └── MetricsCollector.php  # Сбор метрик
└── DTOs/
    └── ExtractedDocument.php    # DTO извлеченного документа
```

#### 2. Система анализа структуры
```
app/Services/Structure/
├── StructureAnalyzer.php        # Главный анализатор
├── AnchorGenerator.php          # Генерация уникальных якорей
├── DTOs/
│   └── DocumentSection.php     # DTO секции документа
└── Validation/
    └── InputValidator.php       # Валидация входных данных
```

#### 3. Система промптов и ИИ
```
app/Services/Prompt/
├── ClaudeApiClient.php          # Клиент Claude API
├── TemplateEngine.php           # Движок шаблонов промптов
├── QualityAnalyzer.php          # Анализ качества ответов
├── SchemaManager.php            # Управление JSON-схемами
├── MetricsCollector.php         # Метрики промптов
└── Exceptions/
    └── PromptException.php      # Исключения ИИ-системы
```

#### 4. Система валидации документов
```
app/Services/Validation/
├── DocumentValidator.php        # Главный валидатор
├── Contracts/
│   └── ValidatorInterface.php   # Интерфейс валидаторов
├── Validators/
│   ├── ContentValidator.php     # Валидация содержимого
│   ├── FileFormatValidator.php  # Валидация формата
│   ├── FileSizeValidator.php    # Валидация размера
│   └── SecurityValidator.php    # Проверка безопасности
└── DTOs/
    └── ValidationResult.php     # Результат валидации
```

## 📊 Модели данных

### Основные модели

#### PromptSystem
Система промптов - основная конфигурация для группы шаблонов.

```php
/**
 * @property int $id
 * @property string $name Название системы промптов
 * @property string $type Тип системы (translation, analysis, generation)
 * @property string|null $system_prompt Базовый системный промпт
 * @property array<string, mixed>|null $schema JSON-схема валидации
 * @property bool $is_active Активна ли система
 */
```

#### PromptTemplate
Шаблон промпта с параметризованными переменными.

```php
/**
 * @property int $id
 * @property string $template Текст шаблона с переменными {{variable}}
 * @property array<int, string>|null $required_variables Обязательные переменные
 * @property array<int, string>|null $optional_variables Опциональные переменные
 * @property bool $is_active Активен ли шаблон
 */
```

#### PromptExecution
Выполнение промпта - запись о конкретном запросе к LLM.

```php
/**
 * @property string $rendered_prompt Сгенерированный итоговый промпт
 * @property string|null $llm_response Ответ от языковой модели
 * @property 'pending'|'completed'|'failed' $status Статус выполнения
 * @property array<string, mixed> $input_variables Входные переменные
 * @property float|null $execution_time_ms Время выполнения
 * @property int|null $tokens_used Количество токенов
 * @property float|null $cost_usd Стоимость в USD
 */
```

#### PromptFeedback
Обратная связь по качеству выполнения промптов.

```php
/**
 * @property string $feedback_type Тип обратной связи (quality, accuracy)
 * @property float|null $rating Числовая оценка (1-5 или 0-1)
 * @property array<string, mixed>|null $details Детализированные метрики
 * @property string|null $user_type Тип пользователя (human, system, automated)
 */
```

## 🔧 Ключевые алгоритмы

### 1. Парсинг документов с якорями

Система использует уникальные якоря для точного сопоставления оригинальных секций и их переводов:

```php
// Генерация якоря для секции
private function generateAnchor(string $sectionId, string $title): string
{
    $cleanTitle = $this->transliterate($this->slugify($title));
    $hash = substr(md5($sectionId . $title), 0, 6);
    
    return "<!-- SECTION_ANCHOR_{$cleanTitle}_{$hash} -->";
}
```

### 2. Интеллектуальная классификация элементов

```php
// Определение типа элемента документа
private function classifyElement(string $text): string
{
    $patterns = [
        'header' => ['/^\\d+\\.\\s+[А-ЯA-Z]/', '/^(Статья|Раздел|Глава)/ui'],
        'list_item' => ['/^[\\-•]\\s+/', '/^\\d+\\)\\s+/'],
        'paragraph' => ['/^[А-ЯA-Z].*[.!?]$/u']
    ];
    
    foreach ($patterns as $type => $typePatterns) {
        foreach ($typePatterns as $pattern) {
            if (preg_match($pattern, trim($text))) {
                return $type;
            }
        }
    }
    
    return 'paragraph';
}
```

### 3. Система оценки качества

```php
// Анализ качества ответа Claude
public function analyzeQuality(string $response, array $context): array
{
    return [
        'completeness' => $this->checkCompleteness($response, $context),
        'accuracy' => $this->checkAccuracy($response, $context),
        'readability' => $this->calculateReadability($response),
        'risk_coverage' => $this->checkRiskCoverage($response),
        'overall_score' => $this->calculateOverallScore($metrics)
    ];
}
```

## 🛡️ Безопасность

### Валидация содержимого

- **Проверка размера файлов**: Лимит 50MB
- **Валидация MIME-типов**: Только разрешенные форматы
- **Сканирование вредоносного кода**: Поиск скриптов и подозрительного контента
- **Rate limiting**: Ограничение запросов по IP и пользователю
- **Санитизация**: Очистка всех входных данных

### API безопасность

```php
// Безопасное выполнение regex с защитой от ReDoS
public static function safeRegexMatch(string $pattern, string $subject): array|false
{
    self::validateRegexPattern($pattern);
    
    // Ограничиваем PCRE limits
    ini_set('pcre.backtrack_limit', '100000');
    ini_set('pcre.recursion_limit', '100000');
    
    $result = @preg_match($pattern, $subject, $matches);
    
    if ($result === false || preg_last_error() !== PREG_NO_ERROR) {
        return false;
    }
    
    return $matches;
}
```

## 📈 Производительность

### Оптимизации Claude API

1. **Кеширование**: Стандартные формулировки кешируются в Redis
2. **Батчинг**: Мелкие секции объединяются в один запрос
3. **Адаптивный выбор модели**: Haiku для простых секций, Sonnet для сложных (экономия до 90%)
4. **Retry логика**: Умные повторы при временных сбоях

### Обработка больших документов

```php
// Асинхронная обработка через очереди
public function processLargeDocument(UploadedFile $file): string
{
    $jobId = Str::uuid();
    
    // Разбиваем на чанки и ставим в очередь
    ProcessDocumentJob::dispatch($file, $jobId)
        ->onQueue('document-processing')
        ->delay(now()->addSeconds(5));
        
    return $jobId;
}
```

## 🧪 Тестирование

### Покрытие тестами

- **Unit тесты**: 235+ тестов для всех сервисов
- **Feature тесты**: Полные сценарии обработки документов
- **PHPStan Level 9**: Максимальный уровень статического анализа
- **Continuous Integration**: Автоматические проверки в GitHub Actions

### Примеры тестов

```php
public function testAnalyzesDocumentStructure(): void
{
    $document = $this->createTestDocument();
    $result = $this->analyzer->analyze($document);
    
    $this->assertGreaterThan(0, $result->getSectionsCount());
    $this->assertGreaterThan(0, $result->averageConfidence);
    $this->assertNotEmpty($result->sections);
}

public function testValidatesRegexSafety(): void
{
    $unsafePattern = '/(.*)+(.*)+/'; // ReDoS vulnerable
    
    $this->expectException(InvalidArgumentException::class);
    InputValidator::validateRegexPattern($unsafePattern);
}
```

## 💰 Монетизация и бизнес-логика

### Тарифные планы

1. **Freemium**: 1 документ до 5 страниц бесплатно
2. **Разовая оплата**: от 199₽ за документ (в разработке)
3. **Basic**: 990₽/месяц - 20 документов (в разработке)
4. **Pro**: 2990₽/месяц - безлимит + API (в разработке)

### Расчет стоимости

```php
public function estimateCost(int $inputTokens, int $outputTokens, string $model): float
{
    // Актуальные цены на 2025 год (за 1M токенов)
    $pricing = [
        'claude-sonnet-4' => ['input' => 3.00, 'output' => 15.00],
        'claude-3-5-sonnet' => ['input' => 3.00, 'output' => 15.00], 
        'claude-3-5-haiku' => ['input' => 0.25, 'output' => 1.25],
    ];
    
    // По умолчанию используем наиболее актуальную модель
    $rates = $pricing[$model] ?? $pricing['claude-sonnet-4'];
    $inputCost = ($inputTokens / 1000000) * $rates['input'];
    $outputCost = ($outputTokens / 1000000) * $rates['output'];
    
    return round($inputCost + $outputCost, 6);
}
```

## 🚀 Развертывание

### Docker конфигурация

```yaml
version: '3.8'
services:
  app:
    build: ./docker/php
    volumes:
      - .:/var/www
    environment:
      - CLAUDE_API_KEY=${CLAUDE_API_KEY}
  
  nginx:
    build: ./docker/nginx
    ports:
      - "80:80"
    depends_on:
      - app
  
  postgres:
    image: postgres:16-alpine
    environment:
      - POSTGRES_DB=laravel
      - POSTGRES_USER=laravel
      - POSTGRES_PASSWORD=secret
  
  redis:
    image: redis:7.4-alpine
  
  minio:
    image: minio/minio
    ports:
      - "9000:9000"
    environment:
      - MINIO_ROOT_USER=minioadmin
      - MINIO_ROOT_PASSWORD=minioadmin

  # 🚀 Supervisor для управления очередями Laravel
  supervisor:
    build:
      dockerfile: docker/supervisor/Dockerfile
    ports:
      - "9001:9001"  # Web-интерфейс Supervisor
    depends_on:
      - postgres
      - redis
    environment:
      - QUEUE_CONNECTION=redis
      - REDIS_HOST=redis
```

### Переменные окружения

```env
# Claude API
CLAUDE_API_KEY=your-claude-api-key
CLAUDE_MODEL=claude-sonnet-4  # Можно переключать на claude-3-5-sonnet или claude-3-5-haiku
CLAUDE_MAX_TOKENS=4096

# Database
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_DATABASE=legal_translator

# Storage
FILESYSTEM_DISK=minio
MINIO_ENDPOINT=http://minio:9000

# Queue & Supervisor
QUEUE_CONNECTION=redis
REDIS_HOST=redis

# Очереди документообработки  
DOCUMENT_ANALYSIS_QUEUE=document-analysis
DOCUMENT_PROCESSING_QUEUE=document-processing
ANALYSIS_JOB_MAX_TRIES=3
ANALYSIS_JOB_TIMEOUT=300
```

### Управление очередями

```bash
# Управление через скрипт
./bin/queue-status.sh status    # Статус worker'ов
./bin/queue-status.sh restart   # Перезапуск всех worker'ов
./bin/queue-status.sh logs document-analysis  # Логи анализа
./bin/queue-status.sh web       # Supervisor web-интерфейс

# Laravel команды
php artisan queue:monitor       # Мониторинг очередей
php artisan queue:failed        # Неудачные задачи
php artisan queue:retry all     # Повтор всех неудачных задач
```

## 📚 Правила разработки

### Стандарты кода

- **PHPStan Level 9**: Максимальный уровень статического анализа
- **Laravel 11**: Актуальная версия фреймворка
- **PHP 8.3**: Современный синтаксис с типизацией
- **PSR-12**: Стандарт кодирования PHP

Все правила разработки ИИ агентом находятся в dev/agent-instruction.md

### DTO Guidelines

```php
// Правильный DTO
final readonly class DocumentProcessingRequest
{
    public function __construct(
        public string $filePath,
        public string $fileType,
        public array $options = []
    ) {}
    
    public static function fromRequest(ProcessDocumentRequest $request): self
    {
        return new self(
            filePath: $request->validated('file_path'),
            fileType: $request->validated('file_type'),
            options: $request->validated('options', [])
        );
    }
}
```

### API Endpoints

- **Роуты API**: `routes/api.php` с префиксом `/api`
- **Валидация**: Только через FormRequest классы
- **Ответы**: Только через JsonResource
- **Авторизация**: Через Laravel Policies

## 📊 Мониторинг и аналитика

### Ключевые метрики

- **Processing Time**: Время обработки документов
- **API Usage**: Расход токенов Claude API  
- **Success Rate**: Процент успешных обработок
- **User Satisfaction**: NPS и рейтинги качества
- **Revenue**: MRR, ARPU, Churn Rate

### Логирование

```php
Log::info('Document processing started', [
    'document_id' => $document->id,
    'file_size' => $document->size_bytes,
    'user_id' => $user->id,
    'processing_mode' => $mode
]);

Log::warning('High API costs detected', [
    'tokens_used' => $tokensUsed,
    'cost_usd' => $cost,
    'document_type' => $type
]);
```

## 🔄 CI/CD Pipeline

### GitHub Actions

```yaml
name: CI/CD
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: PHPStan Analysis
        run: ./vendor/bin/phpstan analyse --level=9
        
      - name: Run Tests
        run: php artisan test --coverage
        
      - name: Security Check
        run: composer audit
```

## 🎯 Roadmap

### Фаза 1 (MVP - 2 недели)
- ✅ Базовый парсинг PDF/DOCX
- ✅ Интеграция Claude API
- ✅ Система якорей
- [ ] Веб-интерфейс для загрузки
- [ ] Базовый функционал обработки

### Фаза 2 (Месяц 1-2)
- [ ] Поддержка разных типов договоров
- [ ] API для B2B клиентов  
- [ ] Telegram-бот интеграция
- [ ] Расширенная аналитика
- [ ] Система уведомлений

### Фаза 3 (Месяц 3-6)
- [ ] Мобильное приложение
- [ ] Монетизация и платежи
- [ ] ИИ-помощник составления договоров
- [ ] White-label решения
- [ ] Корпоративные интеграции

## 📞 Поддержка и разработка

### Команда проекта
- **Product Owner**: Глеб Берман
- **Backend**: Laravel 11 + Claude API
- **DevOps**: Docker + GitHub Actions
- **Testing**: PHPUnit + PHPStan Level 9

### Контакты и ресурсы
- **Проект**: [YouTrack RAS](https://glebberman.youtrack.cloud/projects/RAS) – для доступа к API используется значение переменной окружения `YOUTRACK_API_TOKEN`
- **Документация**: [Описание проекта](https://glebberman.youtrack.cloud/projects/RAS/articles/RAS-A-2)
- **Архитектура**: [Черновик архитектуры](https://glebberman.youtrack.cloud/projects/RAS/articles/RAS-A-1)

---

*Документация обновлена: Январь 2025*  
*Версия системы: MVP 1.0*
- Мне нужно для продолжения разработки без ивпользования реальной LLM сделать так, чтобы можно на время разработки переключаться на фейковую выдачу результата (перевода) в соответствующем формате, это уже можно сделать или нужно доработать?